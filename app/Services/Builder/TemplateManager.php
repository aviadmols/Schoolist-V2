<?php

namespace App\Services\Builder;

use App\Models\BuilderTemplate;
use App\Models\BuilderTemplateVersion;
use Illuminate\Support\Str;

class TemplateManager
{
    /** @var string */
    private const POPUP_TYPE = BuilderTemplate::TYPE_SECTION;

    /**
     * Validate template HTML for safety.
     */
    public function assertTemplateIsSafe(string $html): void
    {
        /** @var TemplateRenderer $renderer */
        $renderer = app(TemplateRenderer::class);

        if (!$renderer->isTemplateSafe($html)) {
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
            $this->createTemplateIfMissing($key);
        }
    }

    /**
     * Create a popup template under the configured prefix.
     */
    public function createPopupTemplate(string $name): BuilderTemplate
    {
        $prefix = (string) config('builder.popup_prefix');
        $key = $prefix.Str::slug($name);

        return BuilderTemplate::query()->firstOrCreate(
            ['scope' => config('builder.scope'), 'key' => $key],
            [
                'name' => $name,
                'type' => self::POPUP_TYPE,
                'draft_html' => '',
                'published_html' => null,
                'is_override_enabled' => false,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]
        );
    }

    /**
     * Publish the draft HTML and record a version.
     */
    public function publishTemplate(BuilderTemplate $template): BuilderTemplate
    {
        $draftHtml = (string) ($template->draft_html ?? '');

        $template->update([
            'published_html' => $draftHtml,
            'updated_by' => auth()->id(),
        ]);

        BuilderTemplateVersion::query()->create([
            'template_id' => $template->id,
            'version_type' => BuilderTemplateVersion::VERSION_PUBLISHED,
            'html' => $draftHtml,
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
            'updated_by' => auth()->id(),
        ]);

        BuilderTemplateVersion::query()->create([
            'template_id' => $template->id,
            'version_type' => BuilderTemplateVersion::VERSION_DRAFT,
            'html' => $version->html,
            'created_by' => auth()->id(),
        ]);

        if ($publishAfterRevert) {
            return $this->publishTemplate($template);
        }

        return $template;
    }

    /**
     * Create a required template if missing.
     */
    private function createTemplateIfMissing(string $key): void
    {
        $type = BuilderTemplate::TYPE_SCREEN;
        $name = Str::title(str_replace('.', ' ', $key));

        BuilderTemplate::query()->firstOrCreate(
            ['scope' => config('builder.scope'), 'key' => $key],
            [
                'name' => $name,
                'type' => $type,
                'draft_html' => '',
                'published_html' => null,
                'is_override_enabled' => false,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]
        );
    }
}
