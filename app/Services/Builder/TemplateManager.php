<?php

namespace App\Services\Builder;

use App\Models\BuilderTemplate;
use App\Models\BuilderTemplateVersion;
use Illuminate\Support\Str;

class TemplateManager
{
    /** @var string */
    private const POPUP_TYPE = BuilderTemplate::TYPE_SECTION;

    /** @var string */
    private const SCREEN_TYPE = BuilderTemplate::TYPE_SCREEN;

    /**
     * Validate template HTML for safety.
     */
    public function assertTemplateIsSafe(string $html, ?string $css = null, ?string $js = null): void
    {
        /** @var TemplateRenderer $renderer */
        $renderer = app(TemplateRenderer::class);

        if (!$renderer->isTemplateSafe($html, $css, $js)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'draft_html' => 'Unsafe PHP execution is not allowed.',
            ]);
        }
    }

    /**
     * Ensure required global templates exist.
     */
    public function ensureDefaultTemplates(): void
    {
        $defaultKeys = (array) config('builder.allowed_keys', []);

        foreach ($defaultKeys as $key) {
            $this->createOrUpdateDefaultTemplate($key, $this->getDefaultHtmlForKey($key), self::SCREEN_TYPE);
        }

        $this->ensureDefaultPopups();
    }

    /**
     * Ensure default popup templates exist.
     */
    private function ensureDefaultPopups(): void
    {
        $popups = (array) config('builder.default_popups', []);

        foreach ($popups as $popup) {
            $popupKey = (string) ($popup['key'] ?? '');
            $title = (string) ($popup['title'] ?? '');

            if (!$popupKey) {
                continue;
            }

            $fullKey = $this->resolvePopupKey($popupKey);
            $name = $title ?: Str::title(str_replace('-', ' ', $popupKey));
            $html = $this->getDefaultPopupHtml($name, $fullKey);

            $template = $this->createOrUpdateDefaultPopupTemplate($fullKey, $html, $name);
        }
    }

    /**
     * Create or update a default popup template with auto-publish.
     */
    private function createOrUpdateDefaultPopupTemplate(
        string $key,
        string $defaultHtml,
        string $name
    ): BuilderTemplate {
        $parts = $this->splitTemplateParts($defaultHtml);

        $template = BuilderTemplate::query()->firstOrCreate(
            ['scope' => config('builder.scope'), 'key' => $key],
            [
                'name' => $name,
                'type' => self::POPUP_TYPE,
                'draft_html' => $parts['html'],
                'draft_css' => $parts['css'],
                'draft_js' => $parts['js'],
                'published_html' => $parts['html'], // Auto-publish
                'published_css' => $parts['css'],
                'published_js' => $parts['js'],
                'is_override_enabled' => true, // Enable override so popups are shown
                'created_by' => auth()->id() ?? 1,
                'updated_by' => auth()->id() ?? 1,
            ]
        );

        // Always update popups to ensure they have the latest content
        // Check if content needs updating (contains placeholder or missing dynamic content)
        $publishedHtml = $template->published_html ?? '';
        $hasPlaceholder = str_contains($publishedHtml, 'Add your content here')
            || str_contains($publishedHtml, 'portal.example')
            || str_contains($publishedHtml, 'newsletter.example')
            || str_contains($publishedHtml, 'Class portal')
            || str_contains($publishedHtml, 'Weekly newsletter')
            || str_contains($publishedHtml, 'Helpful resources for students')
            || str_contains($publishedHtml, 'Math worksheet')
            || str_contains($publishedHtml, 'Reading pages');
        
        // Check if popup should have dynamic content but doesn't
        $shouldHaveDynamicContent = in_array($key, [
            $this->resolvePopupKey('whatsapp'),
            $this->resolvePopupKey('important-links'),
            $this->resolvePopupKey('holidays'),
            $this->resolvePopupKey('children'),
            $this->resolvePopupKey('contacts'),
            $this->resolvePopupKey('links'),
        ]);
        
        // Force update if template was manually edited but should have default content
        $needsUpdate = $this->shouldSeedTemplate($template) 
            || $this->shouldReplaceTemplateDraft($template, $key) 
            || !$template->published_html
            || $hasPlaceholder
            || ($shouldHaveDynamicContent && !str_contains($publishedHtml, '$page['))
            || ($shouldHaveDynamicContent && !str_contains($publishedHtml, '@if'));

        if ($needsUpdate && $defaultHtml) {
            $template->update([
                'draft_html' => $parts['html'],
                'draft_css' => $parts['css'],
                'draft_js' => $parts['js'],
                'published_html' => $parts['html'], // Auto-publish
                'published_css' => $parts['css'],
                'published_js' => $parts['js'],
                'is_override_enabled' => true, // Enable override
                'updated_by' => auth()->id() ?? 1,
            ]);
        }

        return $template;
    }

    /**
     * Create a popup template under the configured prefix.
     */
    public function createPopupTemplate(string $name): BuilderTemplate
    {
        $prefix = (string) config('builder.popup_prefix');
        $key = $prefix.Str::slug($name);

        return $this->createOrUpdateDefaultTemplate(
            $key,
            $this->getDefaultPopupHtml($name, $key),
            self::POPUP_TYPE,
            $name
        );
    }

    /**
     * Publish the draft HTML and record a version.
     */
    public function publishTemplate(BuilderTemplate $template): BuilderTemplate
    {
        $draftHtml = (string) ($template->draft_html ?? '');
        $draftCss = $template->draft_css;
        $draftJs = $template->draft_js;

        $template->update([
            'published_html' => $draftHtml,
            'published_css' => $draftCss,
            'published_js' => $draftJs,
            'updated_by' => auth()->id(),
        ]);

        BuilderTemplateVersion::query()->create([
            'template_id' => $template->id,
            'version_type' => BuilderTemplateVersion::VERSION_PUBLISHED,
            'html' => $draftHtml,
            'css' => $draftCss,
            'js' => $draftJs,
            'created_by' => auth()->id(),
        ]);

        return $template;
    }

    /**
     * Revert draft HTML to a previous version.
     */
    public function revertTemplateToVersion(
        BuilderTemplate $template,
        BuilderTemplateVersion $version,
        bool $publishAfterRevert
    ): BuilderTemplate {
        $template->update([
            'draft_html' => $version->html,
            'draft_css' => $version->css,
            'draft_js' => $version->js,
            'updated_by' => auth()->id(),
        ]);

        BuilderTemplateVersion::query()->create([
            'template_id' => $template->id,
            'version_type' => BuilderTemplateVersion::VERSION_DRAFT,
            'html' => $version->html,
            'css' => $version->css,
            'js' => $version->js,
            'created_by' => auth()->id(),
        ]);

        if ($publishAfterRevert) {
            return $this->publishTemplate($template);
        }

        return $template;
    }

    /**
     * Create or update a default template.
     */
    private function createOrUpdateDefaultTemplate(
        string $key,
        string $defaultHtml,
        string $type,
        ?string $nameOverride = null
    ): BuilderTemplate {
        $name = $nameOverride ?: Str::title(str_replace('.', ' ', $key));
        $parts = $this->splitTemplateParts($defaultHtml);

        $template = BuilderTemplate::query()->firstOrCreate(
            ['scope' => config('builder.scope'), 'key' => $key],
            [
                'name' => $name,
                'type' => $type,
                'published_css' => $parts['css'],
                'is_override_enabled' => false,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]
        );

        if (($this->shouldSeedTemplate($template) || $this->shouldReplaceTemplateDraft($template, $key)) && $parts['css']) {
            $template->update([
                'published_css' => $parts['css'],
                'updated_by' => auth()->id(),
            ]);
        }

        return $template;
    }

    /**
     * Build default HTML for a known key.
     */
    private function getDefaultHtmlForKey(string $key): string
    {
        return $this->getTemplateHtmlFromFile($key) ?? '';
    }

    /**
     * Get default screen HTML from the bundled design (file), not from DB.
     */
    public function getDefaultScreenHtml(string $key): string
    {
        return $this->getDefaultHtmlForKey($key);
    }

    /**
     * Load template HTML from a Git-backed file.
     */
    private function getTemplateHtmlFromFile(string $key): ?string
    {
        $path = resource_path('views/builder/templates/'.str_replace('.', '/', $key).'.blade.php');
        if (!is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        return $contents === false ? null : $contents;
    }


    /**
     * Build the default popup HTML.
     */
    public function getDefaultPopupHtml(string $title, string $key): string
    {
        $fileHtml = $this->getTemplateHtmlFromFile($key);
        if ($fileHtml !== null) {
            return $fileHtml;
        }

        return '';
    }

    /**
     * Resolve a popup key from a short key.
     */
    private function resolvePopupKey(string $key): string
    {
        $prefix = (string) config('builder.popup_prefix');

        if (Str::startsWith($key, $prefix)) {
            return $key;
        }

        return $prefix.$key;
    }

    /**
     * Build popup DOM id from a template key.
     */
    private function getPopupIdFromKey(string $key): string
    {
        $prefix = (string) config('builder.popup_prefix');
        $shortKey = Str::after($key, $prefix);

        return 'popup-'.Str::slug($shortKey);
    }

    /**
     * Determine if a template should be seeded with defaults.
     */
    private function shouldSeedTemplate(BuilderTemplate $template): bool
    {
        return !$template->draft_html && !$template->draft_css && !$template->draft_js;
    }

    /**
     * Determine if a template should be refreshed with new defaults.
     */
    private function shouldReplaceTemplateDraft(BuilderTemplate $template, string $key): bool
    {
        $draftHtml = (string) ($template->draft_html ?? '');

        if (str_contains($draftHtml, 'This is a sample popup template')) {
            return true;
        }

        if ($key === 'classroom.page' && str_contains($draftHtml, 'sb-page')) {
            return true;
        }

        if ($key === 'auth.login' && $this->shouldSeedTemplate($template)) {
            return true;
        }

        return false;
    }


    /**
     * Split HTML into HTML/CSS/JS parts.
     *
     * @return array{html: string, css: string|null, js: string|null}
     */
    public function splitTemplateParts(string $html): array
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
