<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\SurveyAnswer;

class LevelAction extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $table ='level_actions';

    protected $fillable = [
        'level',
        'action_text',
        'reward_point',
    ];

}
