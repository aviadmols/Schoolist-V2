<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait CreatesWithUser
{
    /**
     * Boot the trait.
     */
    protected static function bootCreatesWithUser(): void
    {
        static::creating(function (Model $model): void {
            if (!$model->getAttribute('created_by_user_id') && auth()->check()) {
                $model->setAttribute('created_by_user_id', auth()->id());
            }
        });
    }

    /**
     * The user who created the record.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
