<?php

namespace App\Models;

use App\Models\Traits\ModelAttributes;
use Illuminate\Database\Eloquent\SoftDeletes;

class Notification extends BaseModel
{
    use ModelAttributes;

    /**
     * Fillable.
     *
     * @var array
     */
    protected $fillable = [
        'title',
        'content',
        // 'image',
        'created_by',
        'updated_by',
    ];

    /**
     * Dates.
     *
     * @var array
     */
    protected $dates = [
        'created_at',
        'updated_at',
    ];

    /**
     * Statuses.
     *
     * @var array
     */
    protected $statuses = [
        0 => 'InActive',
        1 => 'Published',
        2 => 'Draft',
        3 => 'Scheduled',
    ];

}
