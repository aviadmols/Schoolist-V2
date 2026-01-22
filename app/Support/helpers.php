<?php

use App\Services\Builder\CssPublisher;
use App\Services\Builder\TemplateRenderer;
use Illuminate\Support\Str;

/**
 * Render a published popup template by key.
 */
function render_popup(string $key, array $data = []): string
{
    $prefix = (string) config('builder.popup_prefix');
    $resolvedKey = Str::startsWith($key, $prefix) ? $key : $prefix.$key;

    /** @var TemplateRenderer $renderer */
    $renderer = app(TemplateRenderer::class);

    return $renderer->renderPublishedByKey($resolvedKey, $data) ?? '';
}

/**
 * Get the published global CSS URL if enabled.
 */
function builder_theme_css_url(): ?string
{
    /** @var CssPublisher $publisher */
    $publisher = app(CssPublisher::class);

    return $publisher->getPublishedCssUrl();
}
