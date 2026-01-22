<?php

namespace App\Services\Builder;

use App\Models\BuilderTemplate;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class TemplateRenderer
{
    /** @var string */
    private const CACHE_KEY_PREFIX = 'builder.template.resolved.';

    /** @var int */
    private const CACHE_TTL_SECONDS = 60;

    /**
     * Render a published template by key when override is enabled.
     */
    public function renderPublishedByKey(string $key, array $data = []): ?string
    {
        $template = $this->getGlobalTemplateByKey($key);

        if (!$template || !$template->is_override_enabled || !$template->published_html) {
            return null;
        }

        if (!$this->isTemplateSafe($template->published_html)) {
            return null;
        }

        $resolvedHtml = $this->getResolvedHtml($template);

        return Blade::render($resolvedHtml, $this->filterRenderData($data));
    }

    /**
     * Render a template for preview.
     */
    public function renderPreview(BuilderTemplate $template, string $version, array $data = []): string
    {
        $html = $this->getTemplateHtmlByVersion($template, $version);

        if (!$this->isTemplateSafe($html)) {
            return '';
        }

        $resolvedHtml = $this->resolveIncludeTokens($html, 0);

        return Blade::render($resolvedHtml, $this->filterRenderData($data));
    }

    /**
     * Get a global template by key.
     */
    public function getGlobalTemplateByKey(string $key): ?BuilderTemplate
    {
        return BuilderTemplate::query()
            ->where('scope', config('builder.scope'))
            ->where('key', $key)
            ->first();
    }

    /**
     * Resolve include tokens for published templates.
     */
    private function getResolvedHtml(BuilderTemplate $template): string
    {
        $hash = hash('sha256', $template->published_html ?? '');
        $cacheKey = self::CACHE_KEY_PREFIX.$template->key.'.'.$hash;

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($template) {
            return $this->resolveIncludeTokens($template->published_html ?? '', 0);
        });
    }

    /**
     * Resolve section/popup tokens recursively.
     */
    private function resolveIncludeTokens(string $html, int $depth): string
    {
        $maxDepth = (int) config('builder.max_include_depth', 5);

        if ($depth >= $maxDepth) {
            return '';
        }

        $pattern = '/\\[\\[(section|popup):([^\\]]+)\\]\\]/i';

        return (string) preg_replace_callback($pattern, function (array $matches) use ($depth) {
            $type = strtolower(trim($matches[1]));
            $key = trim($matches[2]);
            $resolvedKey = $this->resolveIncludeKey($type, $key);

            if ($resolvedKey === '') {
                return '';
            }

            $template = $this->getGlobalTemplateByKey($resolvedKey);

            if (!$template || !$template->published_html || !$template->is_override_enabled) {
                return '';
            }

            $childHtml = $this->resolveIncludeTokens($template->published_html, $depth + 1);

            return $childHtml;
        }, $html);
    }

    /**
     * Resolve token key to a template key.
     */
    private function resolveIncludeKey(string $type, string $key): string
    {
        if ($type === 'popup') {
            $prefix = (string) config('builder.popup_prefix');

            if (Str::startsWith($key, $prefix)) {
                return $key;
            }

            return $prefix.$key;
        }

        return $key;
    }

    /**
     * Get template HTML by version.
     */
    private function getTemplateHtmlByVersion(BuilderTemplate $template, string $version): string
    {
        if ($version === 'published') {
            return (string) ($template->published_html ?? '');
        }

        return (string) ($template->draft_html ?? '');
    }

    /**
     * Filter render data by allowlist.
     */
    private function filterRenderData(array $data): array
    {
        $allowed = (array) config('builder.allowed_template_variables', []);

        return array_intersect_key($data, array_flip($allowed));
    }

    /**
     * Check for unsafe PHP execution patterns.
     */
    public function isTemplateSafe(string $html): bool
    {
        $patterns = [
            '/<\\?php/i',
            '/<\\?=\\s*/i',
            '/@php\\b/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html) === 1) {
                return false;
            }
        }

        return true;
    }
}
