<?php

namespace App\Filament\Pages;

use App\Models\Classroom;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Schema;

class ClassroomSchema extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Classroom Data Schema';

    protected static ?string $navigationGroup = 'System';

    protected static string $view = 'filament.pages.classroom-schema';

    public ?int $selectedClassroomId = null;

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('selectedClassroomId')
                    ->label('Select Classroom')
                    ->options(Classroom::query()->with(['school', 'city'])->get()->mapWithKeys(function ($classroom) {
                        $label = $classroom->name;
                        if ($classroom->school) {
                            $label .= ' - ' . $classroom->school->name;
                        }
                        if ($classroom->city) {
                            $label .= ' (' . $classroom->city->name . ')';
                        }
                        return [$classroom->id => $label];
                    }))
                    ->searchable()
                    ->reactive()
                    ->afterStateUpdated(fn () => $this->resetTable()),
            ]);
    }

    public function getSchemaData(): array
    {
        if (!$this->selectedClassroomId) {
            return [];
        }

        $classroom = Classroom::with([
            'school',
            'city',
            'timetableEntries',
            'importantContacts',
            'children.contacts',
            'links',
            'holidays',
            'announcements',
        ])->find($this->selectedClassroomId);

        if (!$classroom) {
            return [];
        }

        return [
            'classroom' => $this->getTableSchema('classrooms', $classroom->getAttributes()),
            'timetable' => $this->getTableSchema('timetable_entries', $classroom->timetableEntries->first()?->getAttributes() ?? []),
            'contacts' => $this->getTableSchema('important_contacts', $classroom->importantContacts->first()?->getAttributes() ?? []),
            'children' => $this->getTableSchema('children', $classroom->children->first()?->getAttributes() ?? []),
            'child_contacts' => $this->getTableSchema('child_contacts', $classroom->children->first()?->contacts->first()?->getAttributes() ?? []),
            'links' => $this->getTableSchema('class_links', $classroom->links->first()?->getAttributes() ?? []),
            'holidays' => $this->getTableSchema('holidays', $classroom->holidays->first()?->getAttributes() ?? []),
            'announcements' => $this->getTableSchema('announcements', $classroom->announcements->first()?->getAttributes() ?? []),
        ];
    }

    protected function getTableSchema(string $tableName, array $sampleData): array
    {
        $columns = Schema::getColumnListing($tableName);
        $schema = [];

        try {
            $doctrineSchema = Schema::getConnection()->getDoctrineSchemaManager();
            $table = $doctrineSchema->introspectTable($tableName);

            foreach ($columns as $column) {
                try {
                    $doctrineColumn = $table->getColumn($column);
                    $schema[] = [
                        'name' => $column,
                        'type' => $doctrineColumn->getType()->getName(),
                        'sample' => $sampleData[$column] ?? null,
                        'nullable' => !$doctrineColumn->getNotnull(),
                    ];
                } catch (\Exception $e) {
                    $schema[] = [
                        'name' => $column,
                        'type' => 'unknown',
                        'sample' => $sampleData[$column] ?? null,
                        'nullable' => true,
                    ];
                }
            }
        } catch (\Exception $e) {
            // Fallback if doctrine schema fails
            foreach ($columns as $column) {
                $schema[] = [
                    'name' => $column,
                    'type' => 'unknown',
                    'sample' => $sampleData[$column] ?? null,
                    'nullable' => true,
                ];
            }
        }

        return $schema;
    }
}
