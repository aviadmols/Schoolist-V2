<?php

namespace App\Services\Weather;

use App\Models\Classroom;
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
        $setting = $classroom->weatherSetting;

        if (!$setting || !$setting->api_key || !$setting->city_name) {
            return $this->getDefaultWeather();
        }

        $now = Carbon::now($classroom->timezone);
        $targetDate = $now->hour >= 16 ? $now->copy()->addDay() : $now->copy();
        $isToday = $targetDate->isToday();

        $cacheKey = "weather.data.{$setting->id}." . ($isToday ? 'today' : 'tomorrow');

        return Cache::remember($cacheKey, self::WEATHER_CACHE_TTL, function () use ($classroom, $setting, $targetDate) {
            try {
                $weatherData = $this->fetchWeatherData($setting, $targetDate);

                if (!$weatherData) {
                    return $this->getDefaultWeather();
                }

                $temperature = $weatherData['temp'] ?? null;
                $isRaining = ($weatherData['condition'] ?? '') === 'rain' || str_contains(strtolower($weatherData['description'] ?? ''), 'rain');
                $icon = $this->getWeatherIcon($temperature, $isRaining, $setting->icon_mapping ?? []);
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
     * @param WeatherSetting $setting
     * @param Carbon $targetDate
     * @return array{temp: float, condition: string, description: string}|null
     */
    private function fetchWeatherData(WeatherSetting $setting, Carbon $targetDate): ?array
    {
        if ($setting->api_provider === 'openweathermap') {
            return $this->fetchFromOpenWeatherMap($setting, $targetDate);
        }

        return null;
    }

    /**
     * Fetch weather from OpenWeatherMap API.
     *
     * @param WeatherSetting $setting
     * @param Carbon $targetDate
     * @return array{temp: float, condition: string, description: string}|null
     */
    private function fetchFromOpenWeatherMap(WeatherSetting $setting, Carbon $targetDate): ?array
    {
        $cityName = $setting->city_name;
        $apiKey = $setting->api_key;

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
     * @return string
     */
    private function getWeatherIcon(?float $temperature, bool $isRaining, array $iconMapping): string
    {
        if ($isRaining && isset($iconMapping['rain'])) {
            return $iconMapping['rain'];
        }

        if ($temperature === null) {
            return $iconMapping['default'] ?? '☀️';
        }

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
