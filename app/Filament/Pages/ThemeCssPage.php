<?php

namespace App\Filament\Pages;

use App\Models\ThemeCssVersion;
use App\Services\Builder\CssPublisher;
use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;

class ThemeCssPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-paint-brush';

    protected static ?string $navigationGroup = 'Builder';

    protected static string $view = 'filament.pages.theme-css-page';

    /** @var array<string, mixed> */
    public array $data = [];

    /**
     * Determine if the user can access the page.
     */
    public static function canAccess(): bool
    {
        return Gate::allows('manage_theme_css');
    }

    /**
     * Define the form for theme CSS editing.
     */
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Placeholder::make('published_info')
                    ->label('Published')
                    ->content(function (): HtmlString {
                        $lastPublishedAt = ThemeCssVersion::query()
                            ->orderByDesc('created_at')
                            ->value('created_at');

                        $text = $lastPublishedAt
                            ? 'Last published at '.$lastPublishedAt
                            : 'Not published yet';

                        return new HtmlString($text);
                    }),
                Toggle::make('is_enabled')
                    ->label('Enable Global CSS'),
                CodeEditor::make('draft_css')
                    ->label('Draft CSS')
                    ->language('css')
                    ->rows(18)
                    ->columnSpanFull(),
                Placeholder::make('published_url')
                    ->label('Published URL')
                    ->content(function (): HtmlString {
                        $url = builder_theme_css_url();

                        return new HtmlString($url ?: '-');
                    }),
            ])
            ->statePath('data');
    }

    /**
     * Load the form state.
     */
    public function mount(): void
    {
        $themeCss = app(CssPublisher::class)->getThemeCss();

        $this->form->fill([
            'draft_css' => $themeCss->draft_css ?? '',
            'is_enabled' => $themeCss->is_enabled,
        ]);
    }

    /**
     * Save draft CSS and enablement state.
     */
    public function saveDraft(): void
    {
        $state = $this->form->getState();

        app(CssPublisher::class)->saveDraftCss((string) ($state['draft_css'] ?? ''));
        app(CssPublisher::class)->updateCssEnabled((bool) ($state['is_enabled'] ?? false));

        Notification::make()
            ->title('Draft saved')
            ->success()
            ->send();
    }

    /**
     * Publish the draft CSS.
     */
    public function publish(): void
    {
        app(CssPublisher::class)->publishDraftCss();

        Notification::make()
            ->title('CSS published')
            ->success()
            ->send();
    }

    /**
     * Reset draft CSS.
     */
    public function resetDraft(): void
    {
        app(CssPublisher::class)->resetDraftCss();

        $this->form->fill([
            'draft_css' => '',
            'is_enabled' => $this->form->getState()['is_enabled'] ?? false,
        ]);

        Notification::make()
            ->title('Draft reset')
            ->success()
            ->send();
    }
}
