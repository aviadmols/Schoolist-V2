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
        $parts = $this->renderPublishedPartsByKey($key, $data);

        if (!$parts) {
            return null;
        }

        return $this->buildInlineTemplate($parts['html'], $parts['css'], $parts['js']);
    }

    /**
     * Render a template for preview.
     */
    public function renderPreview(BuilderTemplate $template, string $version, array $data = []): string
    {
        $parts = $this->renderPreviewParts($template, $version, $data);

        return $this->buildInlineTemplate($parts['html'], $parts['css'], $parts['js']);
    }

    /**
     * Render a published template by key as HTML/CSS/JS parts.
     *
     * @return array{html: string, css: string|null, js: string|null}|null
     */
    public function renderPublishedPartsByKey(string $key, array $data = []): ?array
    {
        $template = $this->getGlobalTemplateByKey($key);

        if (!$template || !$template->is_override_enabled || !$this->hasPublishedContent($template)) {
            return null;
        }

        $parts = $this->getTemplatePartsByVersion($template, 'published');

        if (!$this->isTemplateSafe($parts['html'], $parts['css'], $parts['js'])) {
            return null;
        }

        $parts['html'] = $this->getResolvedHtml($template);

        return $this->renderTemplateParts($parts, $data);
    }

    /**
     * Render a template for preview as HTML/CSS/JS parts.
     *
     * @return array{html: string, css: string|null, js: string|null}
     */
    public function renderPreviewParts(BuilderTemplate $template, string $version, array $data = []): array
    {
        $parts = $this->getTemplatePartsByVersion($template, $version);

        if (!$this->isTemplateSafe($parts['html'], $parts['css'], $parts['js'])) {
            return [
                'html' => '',
                'css' => null,
                'js' => null,
            ];
        }

        $parts['html'] = $this->resolveIncludeTokens($parts['html'], 0);

        return $this->renderTemplateParts($parts, $data);
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
        $hash = hash('sha256', ($template->published_html ?? '').'|'.($template->published_css ?? '').'|'.($template->published_js ?? ''));
        $cacheKey = self::CACHE_KEY_PREFIX.$template->key.'.'.$hash;

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($template) {
            $parts = $this->getTemplatePartsByVersion($template, 'published');

            return $this->resolveIncludeTokens($parts['html'], 0);
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

            if (!$template || !$template->is_override_enabled || !$this->hasPublishedContent($template)) {
                return '';
            }

            $parts = $this->getTemplatePartsByVersion($template, 'published');

            if (!$this->isTemplateSafe($parts['html'], $parts['css'], $parts['js'])) {
                return '';
            }

            $childHtml = $this->resolveIncludeTokens($parts['html'], $depth + 1);

            return $this->buildInlineTemplate($childHtml, $parts['css'], $parts['js']);
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
    public function isTemplateSafe(string $html, ?string $css = null, ?string $js = null): bool
    {
        $patterns = [
            '/<\\?php/i',
            '/<\\?=\\s*/i',
            '/@php\\b/i',
        ];

        $payload = $html.($css ? ' '.$css : '').($js ? ' '.$js : '');

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $payload) === 1) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get HTML/CSS/JS parts for a template version.
     *
     * @return array{html: string, css: string|null, js: string|null}
     */
    private function getTemplatePartsByVersion(BuilderTemplate $template, string $version): array
    {
        $html = $this->getTemplateHtmlByVersion($template, $version);
        $css = $version === 'published' ? $template->published_css : $template->draft_css;
        $js = $version === 'published' ? $template->published_js : $template->draft_js;

        if (!$css && !$js && $this->hasInlineAssets($html)) {
            $parts = $this->splitTemplateParts($html);
            $html = $parts['html'];
            $css = $parts['css'];
            $js = $parts['js'];
        }

        return [
            'html' => $html,
            'css' => $css,
            'js' => $js,
        ];
    }

    /**
     * Render parts with Blade and filtered data.
     *
     * @param array{html: string, css: string|null, js: string|null} $parts
     * @return array{html: string, css: string|null, js: string|null}
     */
    private function renderTemplateParts(array $parts, array $data): array
    {
        $filteredData = $this->filterRenderData($data);

        return [
            'html' => Blade::render($parts['html'], $filteredData),
            'css' => $parts['css'] ? Blade::render($parts['css'], $filteredData) : null,
            'js' => $parts['js'] ? Blade::render($parts['js'], $filteredData) : null,
        ];
    }

    /**
     * Build inline HTML with optional CSS/JS.
     */
    private function buildInlineTemplate(string $html, ?string $css, ?string $js): string
    {
        $output = '';

        if ($css) {
            $output .= "<style>\n".$css."\n</style>\n";
        }

        $output .= $html;

        if ($js) {
            $output .= "\n<script>\n".$js."\n</script>";
        }

        return $output;
    }

    /**
     * Determine if a template has published content.
     */
    private function hasPublishedContent(BuilderTemplate $template): bool
    {
        return (bool) (($template->published_html ?? '') || ($template->published_css ?? '') || ($template->published_js ?? ''));
    }

    /**
     * Check if HTML has inline style or script tags.
     */
    private function hasInlineAssets(string $html): bool
    {
        return preg_match('/<(style|script)\\b/i', $html) === 1;
    }

    /**
     * Split HTML into HTML/CSS/JS parts.
     *
     * @return array{html: string, css: string|null, js: string|null}
     */
    private function splitTemplateParts(string $html): array
    {
        $css = null;
        $js = null;

        preg_match_all('/<style\\b[^>]*>(.*?)<\\/style>/is', $html, $cssMatches);
        if (!empty($cssMatches[1])) {
            $css = trim(implode("\n\n", $cssMatches[1]));
            $html = preg_replace('/<style\\b[^>]*>.*?<\\/style>/is', '', $html) ?? $html;
        }

        preg_match_all('/<script\\b[^>]*>(.*?)<\\/script>/is', $html, $jsMatches);
        if (!empty($jsMatches[1])) {
            $js = trim(implode("\n\n", $jsMatches[1]));
            $html = preg_replace('/<script\\b[^>]*>.*?<\\/script>/is', '', $html) ?? $html;
        }

        return [
            'html' => trim($html),
            'css' => $css ?: null,
            'js' => $js ?: null,
        ];
    }
}
