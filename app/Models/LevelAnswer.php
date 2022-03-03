<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\SurveyAnswer;

class LevelAnswer extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */

    protected $fillable = [
        'question_id',
        'answer',
    ];

}
