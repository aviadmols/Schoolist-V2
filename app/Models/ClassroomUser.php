<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ClassroomUser extends Pivot
{
    /** @var string */
    protected $table = 'classroom_user';

    /** @var bool */
    public $incrementing = true;
}
