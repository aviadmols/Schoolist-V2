<?php

namespace App\Forms\Components;

use Filament\Forms\Components\Field;

class CodeEditor extends Field
{
    protected string $view = 'filament.forms.components.code-editor';

    /** @var int */
    protected int $rows = 18;

    /**
     * Set the number of rows for the editor.
     */
    public function rows(int $rows): static
    {
        $this->rows = $rows;

        return $this;
    }

    /**
     * Get the number of rows for the editor.
     */
    public function getRows(): int
    {
        return $this->rows;
    }
}
