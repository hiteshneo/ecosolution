<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\OnboardingQuestionAnswer;

class OnboardingQuestion extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'question',
        'answer_type',
    ];

    function answer(){
        return $this->belongsToMany(OnboardingQuestionAnswer::class);
    }
}
