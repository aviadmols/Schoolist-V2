<?php

namespace App\Services\Weather;

use App\Models\Classroom;
use App\Models\WeatherGlobalSetting;
use App\Models\WeatherSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WeatherService
{
    /** @var int */
    private const WEATHER_CACHE_TTL = 1800; // 30 minutes

    /**
     * Get weather data for a classroom.
     *
     * @param Classroom $classroom
     * @return array{text: string, icon: string, recommendation: string, temperature: float|null}
     */
    public function getWeatherForClassroom(Classroom $classroom): array
    {
        $globalSetting = WeatherGlobalSetting::getInstance();
        
        if (!$globalSetting->api_key) {
            return $this->getDefaultWeather();
        }

        // Get city name from classroom's city relationship
        $cityName = $classroom->city?->name;
        
        if (!$cityName) {
            return $this->getDefaultWeather();
        }

        $setting = $classroom->weatherSetting;

        $now = Carbon::now($classroom->timezone);
        $targetDate = $now->hour >= 16 ? $now->copy()->addDay() : $now->copy();
        $isToday = $targetDate->isToday();

        $cacheKey = "weather.data.{$classroom->id}." . ($isToday ? 'today' : 'tomorrow');

        return Cache::remember($cacheKey, self::WEATHER_CACHE_TTL, function () use ($classroom, $globalSetting, $setting, $cityName, $targetDate) {
            try {
                $weatherData = $this->fetchWeatherData($globalSetting, $cityName, $targetDate);

                if (!$weatherData) {
                    return $this->getDefaultWeather();
                }

                $temperature = $weatherData['temp'] ?? null;
                $isRaining = ($weatherData['condition'] ?? '') === 'rain' || str_contains(strtolower($weatherData['description'] ?? ''), 'rain');
                $iconMappingRaw = $setting?->icon_mapping ?? [];
                $temperatureRanges = $setting?->temperature_ranges ?? [];
                // Convert repeater array format to key-value mapping
                $iconMapping = array_reduce(
                    $iconMappingRaw,
                    function (array $carry, $item): array {
                        if (is_array($item) && isset($item['condition'], $item['icon'])) {
                            $carry[$item['condition']] = $item['icon'];
                        }
                        return $carry;
                    },
                    []
                );
                $icon = $this->getWeatherIcon($temperature, $isRaining, $iconMapping, $temperatureRanges);
                // Convert icon path to full URL if it's a file path
                if ($icon && !str_starts_with($icon, 'http') && !str_starts_with($icon, '/') && !preg_match('/^[\x{1F300}-\x{1F9FF}]/u', $icon)) {
                    $icon = asset('storage/' . $icon);
                }
                $recommendation = $this->getWeatherRecommendation($temperature ?? 20, $isRaining);
                $text = $this->formatWeatherText($temperature, $weatherData['description'] ?? '', $recommendation);

                return [
                    'text' => $text,
                    'icon' => $icon,
                    'recommendation' => $recommendation,
                    'temperature' => $temperature,
                ];
            } catch (\Throwable $exception) {
                Log::error('Weather fetch failed', [
                    'classroom_id' => $classroom->id,
                    'error' => $exception->getMessage(),
                ]);

                return $this->getDefaultWeather();
            }
        });
    }

    /**
     * Get weather recommendation based on temperature.
     *
     * @param float $temperature
     * @param bool $isRaining
     * @return string
     */
    public function getWeatherRecommendation(float $temperature, bool $isRaining = false): string
    {
        if ($isRaining) {
            return 'מעיל גשם, מגפיים ומטריה.';
        }

        if ($temperature >= 26) {
            return 'חולצה קצרה, כובע ובקבוק מים.';
        } elseif ($temperature >= 20) {
            return 'חולצה קצרה ומכנסיים דקים (אפשר סריג דק לשעות הבוקר).';
        } elseif ($temperature >= 15) {
            return 'חולצה ארוכה דקה, או חולצה קצרה עם קפוצ\'ון מעל.';
        } elseif ($temperature >= 10) {
            return 'מכנסיים ארוכים עבים, סווטשירט חם ומעיל.';
        } else {
            return 'התעטף היטב – מעיל חם, צעיף, כפפות ומכנסי פוטר.';
        }
    }

    /**
     * Fetch weather data from API.
     *
     * @param WeatherGlobalSetting $globalSetting
     * @param string $cityName
     * @param Carbon $targetDate
     * @return array{temp: float, condition: string, description: string}|null
     */
    private function fetchWeatherData(WeatherGlobalSetting $globalSetting, string $cityName, Carbon $targetDate): ?array
    {
        if ($globalSetting->api_provider === 'openweathermap') {
            return $this->fetchFromOpenWeatherMap($globalSetting, $cityName, $targetDate);
        }

        return null;
    }

    /**
     * Fetch weather from OpenWeatherMap API.
     *
     * @param WeatherGlobalSetting $globalSetting
     * @param string $cityName
     * @param Carbon $targetDate
     * @return array{temp: float, condition: string, description: string}|null
     */
    private function fetchFromOpenWeatherMap(WeatherGlobalSetting $globalSetting, string $cityName, Carbon $targetDate): ?array
    {
        $apiKey = $globalSetting->api_key;

        if (!$cityName || !$apiKey) {
            return null;
        }

        $isToday = $targetDate->isToday();
        $url = $isToday
            ? 'https://api.openweathermap.org/data/2.5/weather'
            : 'https://api.openweathermap.org/data/2.5/forecast';

        $params = [
            'q' => $cityName,
            'appid' => $apiKey,
            'units' => 'metric',
            'lang' => 'he',
        ];

        if (!$isToday) {
            $params['cnt'] = 8;
        }

        $response = Http::timeout(5)->get($url, $params);

        if (!$response->successful()) {
            return null;
        }

        $data = $response->json();

        if ($isToday) {
            return [
                'temp' => (float) ($data['main']['temp'] ?? 20),
                'condition' => $data['weather'][0]['main'] ?? 'clear',
                'description' => $data['weather'][0]['description'] ?? '',
            ];
        }

        $forecastItems = $data['list'] ?? [];
        $targetHour = 12;
        $closestItem = null;
        $minDiff = PHP_INT_MAX;

        foreach ($forecastItems as $item) {
            $itemTime = Carbon::parse($item['dt_txt'] ?? '');
            $hourDiff = abs($itemTime->hour - $targetHour);

            if ($hourDiff < $minDiff) {
                $minDiff = $hourDiff;
                $closestItem = $item;
            }
        }

        if (!$closestItem) {
            return null;
        }

        return [
            'temp' => (float) ($closestItem['main']['temp'] ?? 20),
            'condition' => $closestItem['weather'][0]['main'] ?? 'clear',
            'description' => $closestItem['weather'][0]['description'] ?? '',
        ];
    }

    /**
     * Get weather icon based on temperature and conditions.
     *
     * @param float|null $temperature
     * @param bool $isRaining
     * @param array<string, string> $iconMapping
     * @param array<string, string> $temperatureRanges
     * @return string
     */
    private function getWeatherIcon(?float $temperature, bool $isRaining, array $iconMapping, array $temperatureRanges): string
    {
        if ($isRaining && isset($iconMapping['rain'])) {
            return $iconMapping['rain'];
        }

        if ($temperature === null) {
            return $iconMapping['default'] ?? '☀️';
        }

        // If temperature ranges are configured, use them
        if (!empty($temperatureRanges)) {
            foreach ($temperatureRanges as $rangeConfig) {
                if (is_array($rangeConfig) && isset($rangeConfig['range']) && isset($rangeConfig['condition_key'])) {
                    if ($this->temperatureInRange($temperature, $rangeConfig['range'])) {
                        $conditionKey = $rangeConfig['condition_key'];
                        if (isset($iconMapping[$conditionKey])) {
                            return $iconMapping[$conditionKey];
                        }
                    }
                }
            }
            return $iconMapping['default'] ?? '☀️';
        }

        // Fallback to default ranges if no custom ranges are set
        if ($temperature >= 26) {
            return $iconMapping['hot'] ?? '☀️';
        } elseif ($temperature >= 20) {
            return $iconMapping['warm'] ?? '☀️';
        } elseif ($temperature >= 15) {
            return $iconMapping['mild'] ?? '⛅';
        } elseif ($temperature >= 10) {
            return $iconMapping['cool'] ?? '☁️';
        } else {
            return $iconMapping['cold'] ?? '❄️';
        }
    }

    /**
     * Check if temperature falls within a range.
     *
     * @param float $temperature
     * @param string $range Format: "min-max" or "min+" or "-max"
     * @return bool
     */
    private function temperatureInRange(float $temperature, string $range): bool
    {
        $range = trim($range);
        
        // Handle "min+" format (e.g., "25+")
        if (str_ends_with($range, '+')) {
            $min = (float) rtrim($range, '+');
            return $temperature >= $min;
        }
        
        // Handle "-max" format (e.g., "-10")
        if (str_starts_with($range, '-')) {
            $max = (float) ltrim($range, '-');
            return $temperature <= $max;
        }
        
        // Handle "min-max" format (e.g., "20-25")
        if (str_contains($range, '-')) {
            [$min, $max] = explode('-', $range, 2);
            $min = (float) trim($min);
            $max = (float) trim($max);
            return $temperature >= $min && $temperature <= $max;
        }
        
        return false;
    }

    /**
     * Format weather text for display.
     *
     * @param float|null $temperature
     * @param string $description
     * @param string $recommendation
     * @return string
     */
    private function formatWeatherText(?float $temperature, string $description, string $recommendation): string
    {
        if ($temperature === null) {
            return 'מזג אוויר נוח.';
        }

        $tempText = round($temperature).'°';
        $descText = $description ? ' - '.$description : '';

        return $tempText.$descText;
    }

    /**
     * Get default weather data when API is unavailable.
     *
     * @return array{text: string, icon: string, recommendation: string, temperature: float|null}
     */
    private function getDefaultWeather(): array
    {
        return [
            'text' => '16-20° - מזג אוויר נוח.',
            'icon' => '☀️',
            'recommendation' => 'חולצה ארוכה דקה, או חולצה קצרה עם קפוצ\'ון מעל.',
            'temperature' => 18.0,
        ];
    }
}
