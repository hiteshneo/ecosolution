<?php

namespace App\Models;

use App\Models\Traits\ModelAttributes;

class ApiLogs extends BaseModel
{
    use ModelAttributes;

    /**
     * Fillable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'api_logs',
        'type'
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
}
