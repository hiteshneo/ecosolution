<?php

namespace App;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use Illuminate\Support\Facades\Storage;
use App\Country;
use App\State;
use App\Models\City;

class User extends \TCG\Voyager\Models\User {

    use Notifiable, HasApiTokens;
   
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    // protected $fillable = [
    //     'name', 'phone_number', 'email', 'password', 'device_token', 'device_type', 'otp'
    // ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token', 'avatar', 'thumb_avatar', 'role_id', 'otp', 'email_verified_at', 'device_token', 'device_type', 'device_id', 'user_name', 'phone', 'address', 'status', 'is_otp_verified', 'country_id', 'state_id', 'city_id', 'created_at', 'updated_at', 'settings'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    protected $appends = ['full_image','thumb_image'];
    
    public function getFullImageAttribute()
    {
        if (!empty($this->avatar)) {
            //return Storage::disk('profile_upload')->url($this->image);
            return env('APP_URL').'/storage/app/public/'.$this->avatar;
        } else {
            return env('APP_URL').'/storage/app/public/users/default.png';
        }
    }

    public function getThumbImageAttribute()
    {
        if (!empty($this->thumb_avatar)) {
            return env('APP_URL').'/storage/app/public/'.$this->thumb_avatar;
            //return $this->thumb_avatar;
        } else {
            return env('APP_URL').'/storage/app/public/users/default.png';
        }
    }

    /**
     * @return string
     */
    public function getFullNameAttribute()
    {
        return $this->last_name
            ? $this->first_name.' '.$this->last_name
            : $this->first_name;
    }
    public function rules($method, $id = 0) {
        /* $user = User::find($this->users); */

        switch ($method) {
            case 'GET':
            case 'DELETE': {
                    return [];
                }
            case 'POST': {
                    return [
                        'name' => 'required|regex:/[a-zA-Z\s]+/|min:3|max:30',
                        'email' => 'required|email|unique:users,email,null,id,deleted_at,NULL',
                        'username' => 'required|alpha_num|max:255|unique:users,username,null,id,deleted_at,NULL',
                        'password' => 'required|string|min:6|confirmed',
                    ];
                }
            case 'PUT':
            case 'PATCH': {
                    return [
                        'name' => 'sometimes|required|regex:/[a-zA-Z\s]+/|min:3|max:30',
                        'email' => 'sometimes|required|email|unique:users,email,' . $id . ',id,deleted_at,NULL',
                        'username' => 'sometimes|required|alpha_num|max:255|unique:users,username,' . $id . ',id,deleted_at,NULL',
                        'password' => 'sometimes|required|string|min:6|confirmed',
                       
                    ];
                }
            default:break;
        }
    }

    public function messages($method) {
        /* $user = User::find($this->users); */

        switch ($method) {
            case 'GET':
            case 'DELETE': {
                    return [];
                }
            case 'POST': {
                    return [
                        'password.confirmed' => 'Password and confirm password doesn\'t match'
                    ];
                }
            case 'PUT': {
                    return [
                        'password.confirmed' => 'Password and confirm password doesn\'t match'
                    ];
                }
            case 'PATCH': {
                    return [
                        'name.required' => 'this fiels is required',
                        'image.image' => 'please upload a valid image file',
                        'password.confirmed' => 'Password and confirm password doesn\'t match'
                    ];
                }
            default:break;
        }
    }

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id', 'id');
    }

    public function states()
    {
        return $this->belongsTo(States::class, 'state_id', 'id');
    }

    public function city()
    {
        return $this->belongsTo(City::class, 'city_id', 'id');
    }
}