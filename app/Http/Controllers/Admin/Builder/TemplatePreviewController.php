<?php

namespace App\Http\Controllers\Admin\Builder;

use App\Models\BuilderTemplate;
use App\Services\Builder\TemplateRenderer;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TemplatePreviewController
{
    /**
     * Render a template preview.
     */
    public function __invoke(
        BuilderTemplate $template,
        Request $request,
        TemplateRenderer $renderer
    ): View {
        $version = $request->string('version')->lower()->value() ?: 'draft';
        $mockData = $this->getMockData($template);

        $data = array_merge([
            'user' => $request->user(),
            'locale' => app()->getLocale(),
            'page' => [],
        ], $mockData);

        $html = $renderer->renderPreview($template, $version, $data);

        return view('builder.screen', [
            'html' => $html,
        ]);
    }

    /**
     * Get mock data from the template.
     */
    private function getMockData(BuilderTemplate $template): array
    {
        if (is_array($template->mock_data_json)) {
            return $template->mock_data_json;
        }

        return [];
    }
}
