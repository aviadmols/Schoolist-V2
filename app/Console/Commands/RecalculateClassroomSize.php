<?php

namespace App\Console\Commands;

use App\Models\Classroom;
use App\Models\File;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RecalculateClassroomSize extends Command
{
    /** @var string */
    protected $signature = 'classroom:recalculate-size {classroom_id?}';

    /** @var string */
    protected $description = 'Recalculate media_size_bytes for one or all classrooms based on files table.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $classroomId = $this->argument('classroom_id');

        $query = Classroom::query();
        if ($classroomId) {
            $query->where('id', $classroomId);
        }

        $classrooms = $query->get();

        if ($classrooms->isEmpty()) {
            $this->error('No classrooms found.');
            return 1;
        }

        foreach ($classrooms as $classroom) {
            $actualSize = File::where('classroom_id', $classroom->id)->sum('size_bytes');
            
            $classroom->update(['media_size_bytes' => $actualSize]);
            
            $this->info("Classroom #{$classroom->id} ({$classroom->name}): Recalculated size to {$actualSize} bytes.");
        }

        $this->info('Recalculation complete.');

        return 0;
    }
}
