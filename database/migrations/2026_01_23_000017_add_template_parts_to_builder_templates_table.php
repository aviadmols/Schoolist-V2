<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('builder_templates', function (Blueprint $table) {
            $table->longText('draft_css')->nullable()->after('draft_html');
            $table->longText('draft_js')->nullable()->after('draft_css');
            $table->longText('published_css')->nullable()->after('published_html');
            $table->longText('published_js')->nullable()->after('published_css');
        });

        Schema::table('builder_template_versions', function (Blueprint $table) {
            $table->longText('css')->nullable()->after('html');
            $table->longText('js')->nullable()->after('css');
        });

        $this->splitTemplatePartsInTables();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('builder_template_versions', function (Blueprint $table) {
            $table->dropColumn(['css', 'js']);
        });

        Schema::table('builder_templates', function (Blueprint $table) {
            $table->dropColumn(['draft_css', 'draft_js', 'published_css', 'published_js']);
        });
    }

    /**
     * Split existing HTML into separate parts.
     */
    private function splitTemplatePartsInTables(): void
    {
        $templates = DB::table('builder_templates')
            ->select('id', 'draft_html', 'published_html')
            ->get();

        foreach ($templates as $template) {
            $draftParts = $this->splitTemplateParts((string) ($template->draft_html ?? ''));
            $publishedParts = $this->splitTemplateParts((string) ($template->published_html ?? ''));

            DB::table('builder_templates')
                ->where('id', $template->id)
                ->update([
                    'draft_html' => $draftParts['html'],
                    'draft_css' => $draftParts['css'],
                    'draft_js' => $draftParts['js'],
                    'published_html' => $publishedParts['html'],
                    'published_css' => $publishedParts['css'],
                    'published_js' => $publishedParts['js'],
                ]);
        }

        $versions = DB::table('builder_template_versions')
            ->select('id', 'html')
            ->get();

        foreach ($versions as $version) {
            $parts = $this->splitTemplateParts((string) ($version->html ?? ''));

            DB::table('builder_template_versions')
                ->where('id', $version->id)
                ->update([
                    'html' => $parts['html'],
                    'css' => $parts['css'],
                    'js' => $parts['js'],
                ]);
        }
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
};
