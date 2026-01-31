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
    private const CACHE_TTL_SECONDS = 300; // 5 minutes - increased for better performance

    /** @var array<string, BuilderTemplate|null> */
    private static array $templateCache = [];

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
     * For classroom.page we always use the default design from TemplateManager (file/code), not DB.
     *
     * @return array{html: string, css: string|null, js: string|null}|null
     */
    public function renderPublishedPartsByKey(string $key, array $data = []): ?array
    {
        $template = $this->getGlobalTemplateByKey($key);

        if (!$template || !$template->is_override_enabled) {
            return null;
        }

        // classroom.page: load only from the 3 files (Blade, CSS, JS) — never from DB.
        if ($key === 'classroom.page') {
            $fileHtml = $this->getTemplateFileHtml('classroom.page');
            if ($fileHtml === null || $fileHtml === '') {
                return null;
            }
            $fileCss = $this->getTemplateFileCss('classroom.page');
            $fileJs = $this->getTemplateFileJs('classroom.page');
            $contentHash = hash('sha256', $fileHtml.($fileCss ?? '').($fileJs ?? ''));
            $resolvedCacheKey = self::CACHE_KEY_PREFIX.'classroom.page.files.'.$contentHash;
            $parts = Cache::remember($resolvedCacheKey, self::CACHE_TTL_SECONDS, function () use ($fileHtml, $fileCss, $fileJs) {
                $this->preloadPopupTemplates();
                $parts = [
                    'html' => $fileHtml,
                    'css' => $fileCss,
                    'js' => $fileJs,
                ];
                $parts['html'] = $this->resolveIncludeTokens($parts['html'], 0);

                return $parts;
            });
            $parts = $this->ensureClassroomTabs($template, $parts);
            if (!$this->isTemplateSafe($parts['html'], $parts['css'], $parts['js'])) {
                return null;
            }

            return $this->renderTemplateParts($parts, $data);
        }

        if (!$this->hasPublishedContent($template)) {
            return null;
        }

        $parts = $this->getTemplatePartsByVersion($template, 'published');
        $parts = $this->ensureClassroomTabs($template, $parts);

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
        $parts = $this->ensureClassroomTabs($template, $parts);

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
        if (array_key_exists($key, self::$templateCache)) {
            return self::$templateCache[$key];
        }

        $template = BuilderTemplate::query()
            ->where('scope', config('builder.scope'))
            ->where('key', $key)
            ->first();

        return self::$templateCache[$key] = $template;
    }

    /**
     * Preload all popup templates in one query to avoid N+1 when resolving [[popup:...]] tokens.
     */
    private function preloadPopupTemplates(): void
    {
        $prefix = (string) config('builder.popup_prefix');
        if ($prefix === '') {
            return;
        }

        $templates = BuilderTemplate::query()
            ->where('scope', config('builder.scope'))
            ->where('key', 'like', $prefix.'%')
            ->get();

        foreach ($templates as $template) {
            self::$templateCache[$template->key] = $template;
        }
    }

    /**
     * Resolve include tokens for published templates.
     */
    private function getResolvedHtml(BuilderTemplate $template): string
    {
        $fileHtml = $this->getTemplateFileHtml($template->key) ?? '';
        $fileJs = $this->getTemplateFileJs($template->key) ?? '';
        $hash = hash(
            'sha256',
            $fileHtml.'|'.$fileJs.'|'.($template->published_html ?? '').'|'.($template->published_css ?? '').'|'.($template->published_js ?? '')
        );
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

            // For popups: if no template or override disabled, use default popup HTML
            if ($type === 'popup' && (!$template || !$template->is_override_enabled || !$this->hasPublishedContent($template))) {
                return $this->getDefaultPopupHtml($resolvedKey);
            }

            // For sections: return empty if no template
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
     * Get default popup HTML when no template override exists.
     */
    private function getDefaultPopupHtml(string $key): string
    {
        $fileHtml = $this->getTemplateFileHtml($key);
        if ($fileHtml !== null) {
            return $fileHtml;
        }

        return '';
    }

    /**
     * Get template HTML by version.
     */
    private function getTemplateHtmlByVersion(BuilderTemplate $template, string $version): string
    {
        $fileHtml = $this->getTemplateFileHtml($template->key);
        if ($fileHtml !== null) {
            return $fileHtml;
        }

        if ($version === 'published') {
            return (string) ($template->published_html ?? '');
        }

        return (string) ($template->draft_html ?? '');
    }

    /**
     * Load template HTML from a Git-backed file.
     */
    private function getTemplateFileHtml(string $key): ?string
    {
        $path = resource_path('views/builder/templates/'.str_replace('.', '/', $key).'.blade.php');
        if (!is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        return $contents === false ? null : $contents;
    }

    /**
     * Load template CSS from a Git-backed file.
     */
    private function getTemplateFileCss(string $key): ?string
    {
        $path = resource_path('builder/styles/'.str_replace('.', '/', $key).'.css');
        if (!is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        return $contents === false ? null : $contents;
    }

    /**
     * Load template JS from a Git-backed file.
     */
    private function getTemplateFileJs(string $key): ?string
    {
        $path = resource_path('builder/scripts/'.str_replace('.', '/', $key).'.js');
        if (!is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        return $contents === false ? null : $contents;
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
        $payload = $html.($css ? ' '.$css : '').($js ? ' '.$js : '');

        if ($this->containsUnsafePhpBlock($payload)) {
            return false;
        }

        $patterns = [
            '/<\\?php/i',
            '/<\\?=\\s*/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $payload) === 1) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate @php blocks for safe assignments only.
     */
    private function containsUnsafePhpBlock(string $payload): bool
    {
        if (preg_match('/@php\\b/i', $payload) !== 1) {
            return false;
        }

        if (preg_match_all('/@php\\b(.*?)@endphp/si', $payload, $matches) !== 1) {
            return true;
        }

        foreach ($matches[1] as $block) {
            if (!$this->isPhpBlockSafe($block)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ensure a PHP block contains only simple assignments.
     */
    private function isPhpBlockSafe(string $block): bool
    {
        $code = trim($block);

        if ($code === '') {
            return false;
        }

        if (preg_match('/<\\?php|<\\?=|\\?>/i', $code) === 1) {
            return false;
        }

        if (preg_match('/\\b(function|class|new|eval|include|require|include_once|require_once|shell_exec|exec|system|passthru|proc_open|popen|curl_exec|file_get_contents|file_put_contents|fopen|fwrite|unlink|chmod|chown|sleep|usleep)\\b/i', $code) === 1) {
            return false;
        }

        if (preg_match('/\\b[a-zA-Z_][a-zA-Z0-9_]*\\s*\\(/', $code) === 1) {
            return false;
        }

        $statements = array_filter(array_map('trim', explode(';', $code)));

        foreach ($statements as $statement) {
            if (!preg_match('/^\\$[A-Za-z_][A-Za-z0-9_]*\\s*=.+$/s', $statement)) {
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
        $fileJs = $this->getTemplateFileJs($template->key);
        if ($fileJs !== null) {
            $js = $fileJs;
        }

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
            'css' => ($parts['css'] && str_contains($parts['css'], '{{')) ? Blade::render($parts['css'], $filteredData) : $parts['css'],
            'js' => ($parts['js'] && str_contains($parts['js'], '{{')) ? Blade::render($parts['js'], $filteredData) : $parts['js'],
        ];
    }

    /**
     * Ensure classroom templates render day tabs when missing.
     *
     * @param array{html: string, css: string|null, js: string|null} $parts
     * @return array{html: string, css: string|null, js: string|null}
     */
    private function ensureClassroomTabs(BuilderTemplate $template, array $parts): array
    {
        if ($template->key !== 'classroom.page') {
            return $parts;
        }

        $tabsHtml = <<<'HTML'
<div class="day-tabs-container">
  @foreach (($page['day_labels'] ?? ['א','ב','ג','ד','ה','ו','ש']) as $dayIndex => $dayLabel)
    <button type="button" class="day-tab {{ (int) ($page['selected_day'] ?? 0) === $dayIndex ? 'active' : '' }}">
      {{ $dayLabel }}
    </button>
  @endforeach
</div>
HTML;

        $tabsCss = <<<'CSS'
.day-tabs-container { display: flex !important; gap: 8px; overflow-x: auto; padding: 12px 16px; }
.day-tab { display: inline-flex; align-items: center; justify-content: center; border: none; background: #f1f5f9; color: #0f172a; border-radius: 999px; padding: 6px 10px; font-size: 12px; }
.day-tab.active { background: #e0f2fe; color: #2563eb; font-weight: 700; }
CSS;

        if (!str_contains($parts['html'], 'day-tabs-container')) {
            $parts['html'] = $tabsHtml."\n".$parts['html'];
        }

        if (!$parts['css'] || !str_contains($parts['css'], '.day-tabs-container')) {
            $parts['css'] = $parts['css'] ? ($tabsCss."\n".$parts['css']) : $tabsCss;
        }

        return $parts;
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
        $fileHtml = $this->getTemplateFileHtml($template->key);
        $fileJs = $this->getTemplateFileJs($template->key);

        return (bool) (
            $fileHtml ||
            $fileJs ||
            ($template->published_html ?? '') ||
            ($template->published_css ?? '') ||
            ($template->published_js ?? '')
        );
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
