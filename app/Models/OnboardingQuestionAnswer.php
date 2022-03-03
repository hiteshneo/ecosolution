<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OnboardingQuestionAnswer extends Model
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

    function question(){
        
    }
}
