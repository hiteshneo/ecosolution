<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\SurveyAnswer;

class Survey extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'question',
        'description',
        'answer',
    ];

    function answer(){
        return $this->belongsToMany(SurveyAnswer::class);
    }
}
