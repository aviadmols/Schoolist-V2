<?php

namespace App\Models;

use App\Models\Concerns\CreatesWithUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiSetting extends Model
{
    use CreatesWithUser;

    /** @var array<int, string> */
    protected $fillable = [
        'classroom_id',
        'provider',
        'token',
        'model',
        'timetable_prompt',
        'content_analyzer_model',
        'content_analyzer_prompt',
        'builder_template_prompt',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    /**
     * The classroom this setting belongs to.
     */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }
}
