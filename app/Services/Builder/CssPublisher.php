<?php

namespace App\Services\Builder;

use App\Models\ThemeCss;
use App\Models\ThemeCssVersion;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class CssPublisher
{
    /** @var string */
    private const CSS_PATH = 'admin-theme.css';

    /**
     * Get the singleton ThemeCss row.
     */
    public function getThemeCss(): ThemeCss
    {
        if (!Schema::hasTable('theme_css')) {
            return new ThemeCss([
                'draft_css' => '',
                'published_css' => null,
                'is_enabled' => false,
            ]);
        }

        $themeCss = ThemeCss::query()->first();

        if ($themeCss) {
            return $themeCss;
        }

        return ThemeCss::query()->create([
            'draft_css' => '',
            'published_css' => null,
            'is_enabled' => false,
            'updated_by' => auth()->id(),
        ]);
    }

    /**
     * Save draft CSS.
     */
    public function saveDraftCss(string $css): ThemeCss
    {
        $themeCss = $this->getThemeCss();

        $themeCss->update([
            'draft_css' => $css,
            'updated_by' => auth()->id(),
        ]);

        return $themeCss;
    }

    /**
     * Publish draft CSS.
     */
    public function publishDraftCss(): ThemeCss
    {
        $themeCss = $this->getThemeCss();
        $draftCss = (string) ($themeCss->draft_css ?? '');

        $themeCss->update([
            'published_css' => $draftCss,
            'updated_by' => auth()->id(),
        ]);

        ThemeCssVersion::query()->create([
            'css' => $draftCss,
            'created_by' => auth()->id(),
        ]);

        $this->writePublishedCssFile($draftCss);

        return $themeCss;
    }

    /**
     * Reset draft CSS.
     */
    public function resetDraftCss(): ThemeCss
    {
        return $this->saveDraftCss('');
    }

    /**
     * Toggle CSS enablement.
     */
    public function updateCssEnabled(bool $isEnabled): ThemeCss
    {
        $themeCss = $this->getThemeCss();

        $themeCss->update([
            'is_enabled' => $isEnabled,
            'updated_by' => auth()->id(),
        ]);

        return $themeCss;
    }

    /**
     * Get public URL for published CSS.
     */
    public function getPublishedCssUrl(): ?string
    {
        if (!Schema::hasTable('theme_css')) {
            return null;
        }

        $themeCss = ThemeCss::query()->first();

        if (!$themeCss || !$themeCss->is_enabled || !$themeCss->published_css) {
            return null;
        }

        if (!Storage::disk('public')->exists(self::CSS_PATH)) {
            $this->writePublishedCssFile($themeCss->published_css);
        }

        $hash = hash('sha256', $themeCss->published_css);
        $url = Storage::disk('public')->url(self::CSS_PATH);

        return $url.'?v='.$hash;
    }

    /**
     * Write published CSS to storage.
     */
    private function writePublishedCssFile(string $css): void
    {
        Storage::disk('public')->put(self::CSS_PATH, $css);
    }
}
