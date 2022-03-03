<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\SurveyAnswer;

class LevelQuestion extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */

    protected $fillable = [
        'level_id',
        'question',
        'description',
    ];

}
