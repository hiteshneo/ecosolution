<?php

namespace App\Models\Auth;

use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use App\Models\Auth\Traits\Access\UserAccess;
use App\Models\Auth\Traits\Scopes\UserScopes;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Auth\Traits\Methods\UserMethods;
use App\Models\Auth\Traits\Attributes\UserAttributes;
use App\Models\Auth\Traits\Relationships\UserRelationships;
use Illuminate\Support\Facades\Storage;

/**
 * Class User.
 */
class User extends BaseUser
{
    use HasApiTokens, Notifiable, SoftDeletes, UserAttributes, UserScopes, UserAccess, UserRelationships, UserMethods;

    protected $fillable = ['user_code', 'first_name', 'last_name', 'email', 'phone', 'password', 'gender', 'device_token', 'otp', 'referral_code', 'pan_card_number', 'pan_card_image'];
    protected $appends = ['full','full_name'];
    
    public function getFullAttribute()
    {
        if (!empty($this->avatar_location)) {
            //return Storage::disk('profile_upload')->url($this->image);
            return env('APP_URL').'/public'.Storage::disk('pic_profile')->url($this->avatar_location);
        } else {
            return env('APP_URL').'/public/images/no-image.png';
        }
    }

  
}
