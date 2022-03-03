<?php

namespace App\Http\Controllers\API\V1;

use Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Image;
use App\User;
use Illuminate\Support\Facades\DB;
use App\Models\Countries;
use App\Models\States;
use Illuminate\Support\Str;
use App\Models\Page;
use App\Models\Notification;
use App\Models\ApiLogs;
use TCG\Voyager\Facades\Voyager;
use App\Models\Language;
use Twilio\Rest\Client;
use Mail;
/**
 * @group Authentication
 *
 * Class AuthController
 *
 * Fullfills all aspects related to authenticate a user.
 */
class AuthController extends APIController
{

    use AuthenticatesUsers;
    public function register(Request $request)
    {
        $time = date('Y-m-d H:i:s');
        $validation = Validator::make($request->all(), [
            'community_code' => 'required',
            'community_code' => 'required',
            'community_code' => 'required',
            'email' => 'required|email',
            'password' => 'required',
            'country_id' => 'required',
            'city_id' => 'required',
            
        ]);
        $data = $request->all();
        if ($validation->fails()) {
            //return $this->throwValidation($validation->messages()->first());
            $resp = [
                'status' => false,
                'data' => '',
                'message' => $validation->messages()->first(),
                'error' => true,
                'errors' => '',
            ];
            return response()->json($resp, $this->statusCode);
        }

        $checkuser = User::where(['email' => $request->get('email'), 'role_id' => USER_ROLE])->count();
        if($checkuser == 1){
            $resp = [
                'status' => false,
                'data' => '',
                'message' => 'The email has already been taken',
                'error' => true,
                'errors' => '',
            ];
            return response()->json($resp, $this->statusCode);
        } 


        $otp = randomOTP();
        //print_r($data);die;
        $token ='';
        //$otp = '1234';
        $arr = [
            'role_id' => USER_ROLE,
            'community_code'=> $data['community_code'],
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'country_id' => 230,
            'state_id' => $data['country_id'],
            'city_id' => $data['city_id'],
            'device_token' => $data['device_token'],
            'device_type' => $data['device_type'],
            'otp' => $otp
        ];
            //dd($arr);
        try {

            $user = User::create($arr);

            $notify_message = $otp . ' is the OTP for accessing your account. PLEASE DO NOT SHARE IT WITH ANYONE.';
            $notification_data = ['type' => 'otp', 'message' => $notify_message, 'user_id' => $user->id];
            
            // $passportToken = $user->createToken('API Access Token');
            // $passportToken->token->save();
            // $token = $passportToken->accessToken;

        } catch (\Exception $e) {
            return $this->respondInternalError($e->getMessage());
        }

        $resp = [
            'status' => true,
            'data' => $user,
            'otp' => $otp,
            'message' => 'Verification code send successfully.',
            'error' => false,
            'errors' => '',
            //'user_path' => env('APP_URL').'/public/images/profile/',
        ];
        return response()->json($resp, $this->statusCode);
    }


    /**
     * Attempt to login the user.
     *
     * If login is successfull, you get an api_token in response. Use that api_token to authenticate yourself for further api calls.
     *
     * @bodyParam email string required Your email id. Example: "user@test.com"
     * @bodyParam password string required Your Password. Example: "abc@123_4"
     *
     * @responseFile status=401 scenario="api_key not provided" responses/unauthenticated.json
     * @responseFile responses/auth/login.json
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'email' => 'required',
            'password' => 'required'
        ]);

        if ($validation->fails()) {
            $resp = [
                'status' => false,
                'data' => '',
                'message' => $validation->messages()->first(),
                'error' => true,
                'errors' => '',
            ];
            return response()->json($resp, $this->statusCode);
        }

        $resposeArray = $countries = array();

        $checkuseractive = User::where(['email' => $request->get('email'), 'is_otp_verified' => 0])->count();
        if($checkuseractive > 0){
            return $this->respond([
                'status' => false,
                'message' => 'Your account is not active.',
                'data' => '',
                'country' => '',
                'error' => true,
                'errors' => '',
            ]);
        }
     
        if(Auth::attempt(['email' => request('email'), 'password' => request('password')])){ 
            $user = Auth::user(); 

            User::where('email', $request->get('email'))->update(['device_token' => $request->get('device_token'),'device_type' => $request->get('device_type')]);

            $passportToken = $user->createToken('API Access Token');
            $passportToken->token->save();
            $token = $passportToken->accessToken;
            $resp = [
                'status' => true,
                'data' => $user,
                'is_register' => 1,
                'token' => $token,
                'message' => 'Logged in successfully',
                'error' => false,
                'errors' => '',
            ];
        } 
        else{ 
            $resp = [
                'status' => false,
                'data' => '',
                'message' => 'Otp not verified.',
                'error' => true,
                'errors' => '',
            ];
        }
       
        return response()->json($resp, $this->statusCode);
    }

    public function verifyOtp(Request $request) {

        $validation = Validator::make($request->all(), [
            'email' => 'required',
            'otp' => 'required',
        ]);

        if ($validation->fails()) {
            //return $this->throwValidation($validation->messages()->first());
            $resp = [
                'status' => true,
                'data' => '',
                'message' => $validation->messages()->first(),
                'error' => true,
                'errors' => '',
            ];
            return response()->json($resp, $this->statusCode);
        }
        $resposeArray = array(
            'email' => $request->get('email'),
            'otp' => $request->get('otp')
        );

        $user = User::where(['email'=> $request->get('email'), 'otp' => $request->otp])->first();
        if($user){
            DB::table('users')->where('id', $user->id)->update(['status' => 1, 'is_otp_verified' => 1]);
            //$user = User::where('email', $request->get('email'))->first();
            $resp = [
                'status' => true,
                'data' => '',
                'message' => 'Otp verified successfully',
                'error' => false,
                'errors' => '',
            ];
        }else{
            $resp = [
                'status' => false,
                'data' => $resposeArray,
                'message' => 'Otp not verified.',
                'error' => true,
                'errors' => '',
            ];
        }

       
        return response()->json($resp, $this->statusCode);

    }

    public function resendOtp(Request $request)
    {
        $data = $request->all();
        $validation = Validator::make($request->all(), [
            'email' => 'required',
        ]);

        if ($validation->fails()) {
            //return $this->throwValidation($validation->messages()->first());
            $resp = [
                'status' => true,
                'data' => '',
                'message' => $validation->messages()->first(),
                'error' => true,
                'errors' => '',
            ];
            return response()->json($resp, $this->statusCode);
        }
        $otp = randomOTP();
        $resposeArray = array(
            'email' => $request->get('email'),
            'otp' => $otp
        );
        $user = User::where(['users.email' => $data['email'], 'role_id' => USER_ROLE])->count();
        if($user > 0){
           
            DB::table('users')->where('email', $data['email'])->update(['otp' => $otp]);
            $user = User::where('users.email', $data['email'])->first();
            
            $notify_message = $otp . ' is the OTP for accessing your account. PLEASE DO NOT SHARE IT WITH ANYONE.';
            
            $resp = [
                'status' => true,
                'data' => $resposeArray,
                'message' => 'OTP sent successfully.',
                'error' => false,
                'errors' => '',
            ];
            
        }else{
            $resp = [
                'status' => false,
                'data' => $resposeArray,
                'message' => 'User not found.',
                'is_register' => 0,
                'error' => true,
                'errors' => '',
            ];
            
        }
        return response()->json($resp, $this->statusCode);

    }

    public function socialLogin(Request $request){
        $validation = Validator::make($request->all(), [
            //'email' => 'required|email',
            'social_id' => 'required',
            'social_type' => 'required',
            //'device_token' => 'required',
            'device_type' => 'required',
        ]);
        $data = $request->all();
        if ($validation->fails()) {
            //return $this->throwValidation($validation->messages()->first());
            $resp = [
                'status' => false,
                'data' => '',
                'message' => $validation->messages()->first(),
                'error' => true,
                'errors' => '',
            ];
            return response()->json($resp, $this->statusCode);
        }
        $checkuser = User::where(['social_id' => $request->get('social_id')])->count();
        if($checkuser == 0){
            if($request->language_id !== null){
                $language_id = $request->language_id;
            }else{
                $language_id = NULL;
            }
            $arr = [
                'role_id' => USER_ROLE,
                'name' => $request->get('name') != null ? $request->get('name') : '',
                'email' => $request->get('email') != null && $request->get('email') != '' ? $request->get('email') : 'voxo_'.$request->get('social_id').'voxo.ca',
                'user_name' => rand(111111111111,999999999999),
                'mobile_number' => $request->get('mobile_number') != null ? $request->get('mobile_number') : '',
                'password' => Hash::make('123456'),
                'status' => 1,
                'language_id'=> $language_id,
                'social_id' => $request->get('social_id'),
                'login_type' => $request->get('social_type'),
                'device_token' => $request->get('device_token') != null ? $request->get('device_token') : '',
                'device_type' => $request->get('device_type'),
            ];
            $user = User::create($arr);
            $user = User::where(['id'=> $user->id])->first();
        }else{
            $user = User::where(['social_id'=> $request->get('social_id')])->first();    
            $arr = [
                'device_token' => $request->get('device_token') != null ? $request->get('device_token') : '',
                'device_type' => $request->get('device_type'),
            ];
            User::where('id', $user->id)->update($arr);
        }
        
       
        if($user){
            $passportToken = $user->createToken('API Access Token');
            $passportToken->token->save();
            $token = $passportToken->accessToken;
            $langData = Language::get();
            $dataArray[] = $user;
            $resp = [
                'status' => true,
                'data' => $user,
                'language' => $langData,
                'country' => '',
                'is_register' => 1,
                'token' => $token,
                'message' => 'success',
                'error' => false,
                'errors' => '',
            ];
        }else{
            $resp = [
                'status' => false,
                'data' => [],
                'message' => 'Something went wrong.',
                'error' => true,
                'errors' => '',
            ];
        }

        return response()->json($resp, $this->statusCode);
    }

    public function getCountries()
    {
        $countries = Countries::get();
       
        $resp = [
            'status' => true,
            'data' => $countries,
            'message' => 'success',
            'error' => false,
            'errors' => '',
        ];
        return response()->json($resp, $this->statusCode);
    }

    public function getStates(Request $request)
    {

        // $validation = Validator::make($request->all(), [
        //     'country_id' => 'required',
        // ]);

        // if ($validation->fails()) {
        //     $resp = [
        //         'status' => false,
        //         'data' => '',
        //         'message' => $validation->messages()->first(),
        //         'error' => true,
        //         'errors' => '',
        //     ];
        //     return response()->json($resp, $this->statusCode);
        // }

        $data = $request->all();
        $states = array();
        
        $states = DB::table('states')->where('country_id', 230)->get();
        
        $resp = [
            'status' => true,
            'data' => $states ,
            'message' => 'success',
            'error' => false,
            'errors' => '',
        ];
        return response()->json($resp, $this->statusCode);
    }

    public function getCity(Request $request)
    {

        $validation = Validator::make($request->all(), [
            'state_id' => 'required',
        ]);

        if ($validation->fails()) {
            $resp = [
                'status' => false,
                'data' => '',
                'message' => $validation->messages()->first(),
                'error' => true,
                'errors' => '',
            ];
            return response()->json($resp, $this->statusCode);
        }

        $data = $request->all();
        $cities = DB::table('cities')->where('state_id', $data['state_id'])->get();
        
        $resp = [
            'status' => true,
            'data' => $cities ,
            'message' => 'success',
            'error' => false,
            'errors' => '',
        ];
        return response()->json($resp, $this->statusCode);
    }
    
    /**
     * Get the authenticated User.
     *
     * @responseFile status=401 scenario="api_key not provided" responses/unauthenticated.json
     * @responseFile responses/auth/me.json
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function me()
    {
        
        $userID = Auth::user();
        $user = User::select('id', 'user_name as nick_name', 'name','email', 'mobile_number', 'dob', 'user_bio', 'avatar', 'thumb_avatar', 'gender', 'language_id')->where('users.id', $userID->id)->first();

        $totalFollower = UserFollower::where('follower_id', $userID->id)->count();
        $totalFollowing = UserFollower::where('user_id', $userID->id)->count();

        $dataRow['id'] = $user->id;
        $dataRow['nick_name'] = $user->nick_name;
        $dataRow['name'] = $user->name;
        $dataRow['email'] = $user->email;
        $dataRow['country_code'] = $user->country_code;
        $dataRow['mobile_number'] = $user->mobile_number;
        $dataRow['gender'] = $user->gender;
        $dataRow['dob'] = $user->dob;
        $dataRow['followers'] = $totalFollower;
        $dataRow['following'] = $totalFollowing;
        $dataRow['likes'] = 0;
        $dataRow['user_bio'] = $user->user_bio;
        //$dataRow['language_id'] = $user->language_id;
        $dataRow['full_image'] = $user->avatar;
        $dataRow['thumb_image'] = $user->thumb_avatar;
        $lanData = Language::where('id', $user->language_id)->first();
        $dataRow['language'] = $lanData;
        
        $dataArray[] = $dataRow;
        $countries = Countries::select('id', 'name', 'iso2', 'dialcode')->where('dialcode', $user->country_code)->first();
        $this->userSetHidden($user);
        $resp = [
            'status' => true,
            'data' => $dataRow,
            'country' => $countries,
            'message' => 'success',
            'error' => false,
            'errors' => '',
        ];
        return response()->json($resp, $this->statusCode);
    }

    public function userProfile(Request $request)
    {
        $userID = Auth::user();
        $data = $request->all();
        $user = User::where('users.id', $data['user_id'])->first();

        $this->userSetHidden($user);
        $resp = [
            'status' => true,
            'data' => $user,
            'message' => 'success',
            'error' => false,
            'errors' => '',
            //'user_path' => env('APP_URL').'/public/images/profile/',
        ];
        return response()->json($resp, $this->statusCode);
    }

    
    /**
     * Attempt to logout the user.
     *
     * After successfull logut the token get invalidated and can not be used further.
     *
     * @responseFile status=401 scenario="api_key not provided" responses/unauthenticated.json
     * @responseFile responses/auth/logout.json
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        try {
            $request->user()->token()->revoke();
        } catch (\Exception $e) {
            return $this->respondInternalError($e->getMessage());
        }

        $resp = [
            'status' => true,
            'data' => '',
            'message' => 'Logout successfully',
            'error' => true,
            'errors' => '',
        ];
        return response()->json($resp, $this->statusCode);
       
    }

    public function updatePassword(Request $request)
    {
        $errors = '';
        
        if (!(Hash::check($request->get('old_password'), Auth::user()->password))) {
            $message = 'Old password is wrong.';
            $errors = ['Old password is wrong.'];
            $resp = [
                'status' => false,
                'data' => '',
                'message' => $message,
                'error' => true,
                'errors' => $errors,
            ];
            return response()->json($resp, $this->statusCode);
        } else if (strcmp($request->get('old_password'), $request->get('new_password')) == 0) {
            $message = 'New password cannot be same as old password.';
            $errors = ['New password cannot be same as old password.'];
            $resp = [
                'status' => false,
                'data' => '',
                'message' => $message,
                'error' => true,
                'errors' => $errors,
            ];
            return response()->json($resp, $this->statusCode);
        } else {
            $validation = Validator::make($request->all(), [
                'old_password' => 'required',
                'new_password' => 'string|min:8|max:25|required_with:confirm_password|same:confirm_password',
            ]);
           
            $data = $request->all();
            if ($validation->fails()) {
                //return $this->throwValidation($validation->messages()->first());
                $resp = [
                    'status' => false,
                    'data' => '',
                    'message' => $validation->messages()->first(),
                    'error' => true,
                    'errors' => '',
                ];
                return response()->json($resp, $this->statusCode);
            }
            $user = Auth::user();
            $user->password = bcrypt($request->get('new_password'));
            $user->save();
            $message = 'Password changed successfully';
            
        }
        $resp = [
            'status' => true,
            'data' => '',
            'message' => $message,
            'error' => false,
            'errors' => $errors,
        ];
        return response()->json($resp, $this->statusCode);
    }

    public function forgotPassword(Request $request)
    {
        $data = $request->all();
        $validation = Validator::make($request->all(), [
            'email' => 'required',
        ]);

        if ($validation->fails()) {
            //return $this->throwValidation($validation->messages()->first());
            $resp = [
                'status' => true,
                'data' => '',
                'message' => $validation->messages()->first(),
                'error' => true,
                'errors' => '',
            ];
            return response()->json($resp, $this->statusCode);
        }

        $user = User::where('users.email', $data['email'])->count();
        if($user > 0){
            $password = randomPassword();
            $userProfileArr = array(
                'password' => Hash::make($password),
            );

            DB::table('users')->where('email', $data['email'])->update($userProfileArr);
            $user = User::where('users.email', $data['email'])->first();
            
            $notify_message = $password . ' is the password for accessing your account. Please change your password after login.';
            
            $resp = [
                'status' => true,
                'data' => $password,
                'message' => 'Password sent to your registred email.',
                'error' => false,
                'errors' => '',
            ];
            
        }else{
            $resp = [
                'status' => true,
                'data' => '',
                'message' => 'User not found.',
                'error' => true,
                'errors' => '',
            ];
            
        }
        return response()->json($resp, $this->statusCode);

    }

    public function updateProfile(Request $request)
    {
        $user = Auth::user();
        $saveavatarImage = $user->avatar;
        $saveavatarThumbImage = $user->thumb_avatar;
        $data = $request->all();
        
        $validation = Validator::make($request->all(), [
            'type' => 'required',
        ]);

        if ($validation->fails()) {
            //return $this->throwValidation($validation->messages()->first());
            $resp = [
                'status' => true,
                'data' => '',
                'message' => $validation->messages()->first(),
                'error' => true,
                'errors' => '',
            ];
            return response()->json($resp, $this->statusCode);
        }
        
        if($request->type == 'image'){
            
            if($request->file('avatar')!=null) {
                $files = $request->file('avatar');
              
                $storage = Storage::disk('public');

                $file_name = 'img_'.time() . "." . $files->getClientOriginalExtension();
                $storagefilePath = 'users/'.$file_name;
                $storage->put($storagefilePath, file_get_contents($files), 'public');
                $saveavatarImage = $storagefilePath;
                
                $imgThumb = Image::make($files->getRealPath())->resize(300, 300)->stream(); ##create thumbnail
                $thumb_file_name = 'thumb_img_'.time() . "." . $files->getClientOriginalExtension();
                $storagefilePath = 'users/'.$thumb_file_name;
                $storage->put($storagefilePath, $imgThumb->__toString(),'public');
                $saveavatarThumbImage = $storagefilePath;

                $userProfileArr = array(
                    'avatar' => $saveavatarImage,
                    'thumb_avatar' => $saveavatarThumbImage,
                );
        
                DB::table('users')->where('id', $user->id)->update($userProfileArr);
                $message = 'Profile image updated successfully';
                $status = true;
            }else{
                $validation = Validator::make($request->all(), [
                    'avatar' => 'required',
                ]);
        
                if ($validation->fails()) {
                    //return $this->throwValidation($validation->messages()->first());
                    $resp = [
                        'status' => true,
                        'data' => '',
                        'message' => $validation->messages()->first(),
                        'error' => true,
                        'errors' => '',
                    ];
                    return response()->json($resp, $this->statusCode);
                }
            }
        }elseif($request->type == 'detail'){
            $userProfileArr = array(
                'first_name' => isset($data['first_name']) && $data['first_name'] != '' ? $data['first_name'] : $user->first_name,
                'last_name' => isset($data['last_name']) && $data['last_name'] != '' ? $data['last_name'] : $user->last_name,
                'state_id' => isset($data['country_id']) && $data['country_id'] != '' ? $data['country_id'] : $user->state_id,
                'city_id' => isset($data['city_id']) && $data['city_id'] != '' ? $data['city_id'] : $user->city_id,
                'updated_at' => date('Y-m-d H:i:s'),
            );
    
            DB::table('users')->where('id', $user->id)->update($userProfileArr);
            $message = 'Updated successfully';
            $status = true;
        }elseif($request->type == 'email'){
            if($data['existing_email'] != ''){
                if($data['email'] != ''){
            
                    $userEmail = User::where(['email' => $data['existing_email'], 'id' => $user->id])->count();
                    if($userEmail > 0){
                        $userProfileArr = array(
                            'email' => isset($data['email']) && $data['email'] != '' ? $data['email'] : $user->email,
                        );
                        DB::table('users')->where('id', $user->id)->update($userProfileArr);
                        $message = 'Email changed successfully';
                        $status = true;
                        $error = false;
                    }else{
                        $message = 'Existing email doesn\'t exist.';
                        $status = false;
                        $error = true;
                    }
                }else{
                    $message = 'Please enter email.';
                    $status = false;
                    $error = true;
                }
            }else{
                $message = 'Please enter existing email.';
                $status = false;
                $error = true;
            }
        }elseif($request->type == 'password'){

            $errors = '';
            if (!(Hash::check($request->get('old_password'), Auth::user()->password))) {
                $message = 'Old password is wrong.';
                $errors = ['Old password is wrong.'];

                $message = 'Old password is wrong.';
                $status = false;
                $error = true;

                // $resp = [
                //     'status' => false,
                //     'data' => '',
                //     'message' => $message,
                //     'error' => true,
                //     'errors' => $errors,
                // ];
                // return response()->json($resp, $this->statusCode);
            } else if (strcmp($request->get('old_password'), $request->get('new_password')) == 0) {
                $message = 'New password cannot be same as old password.';
                $errors = ['New password cannot be same as old password.'];

                $message = 'New password cannot be same as old password.';
                $status = false;
                $error = true;

                // $resp = [
                //     'status' => false,
                //     'data' => '',
                //     'message' => $message,
                //     'error' => true,
                //     'errors' => $errors,
                // ];
                // return response()->json($resp, $this->statusCode);
            } else {
                $validation = Validator::make($request->all(), [
                    'old_password' => 'required',
                    'new_password' => 'string|min:8|max:25|required_with:confirm_password|same:confirm_password',
                ]);
            
                $data = $request->all();
                if ($validation->fails()) {
                    $resp = [
                        'status' => false,
                        'data' => '',
                        'message' => $validation->messages()->first(),
                        'error' => true,
                        'errors' => $errors,
                    ];
                    return response()->json($resp, $this->statusCode);
                }
                $user = Auth::user();
                $user->password = bcrypt($request->get('new_password'));
                $user->save();
                $message = 'Password changed successfully';
                $status = true;
                $error = false;
            }
        }


        //$user = User::with('country', 'states', 'city')->where('users.id', $user->id)->first();
        //$this->userSetHidden($user);
        
        $resp = [
            'status' => $status,
            'data' => '',
            'message' => $message,
            'error' => $error,
            //'image_path' => env('ASSET_URL') . '/images/profile'
        ];
        return response()->json($resp, $this->statusCode);
    }

    /**
     * Create a thumbnail of specified size
     *
     * @param string $path path of thumbnail
     * @param int $width
     * @param int $height
     */
    public function createThumbnail($path, $width, $height)
    {
        $img = Image::make($path)->resize($width, $height, function ($constraint) {
            $constraint->aspectRatio();
        });
        $img->save($path);
    }
 
    public function cmsPage(Request $request)
    {
        $userID = Auth::user();
        $data = $request->all();
        $validation = Validator::make($data, [
            'slug' => 'required',
        ]);

        if ($validation->fails()) {
            $resp = [
                'status' => true,
                'data' => '',
                'message' => $validation->messages()->first(),
                'error' => true,
                'errors' => '',
            ];
            return response()->json($resp, $this->statusCode);
        }

        $data = Page::where('slug', $data['slug'])->first();
        $resp = [
            'status' => true,
            'data' => $data,
            'message' => 'Success.',
            'error' => false,
            'errors' => '',
        ];
        return response()->json($resp, $this->statusCode);

    }

    public function notificationList(Request $request){

        $data = DB::table('notifications')->orderBy('id', 'desc')->paginate(20);
        $resp = [
            'status' => true,
            'message' => 'success',
            'data' => $data,
            'image_url' => ENV('APP_URL').'/storage/app/public/',
            'error' => false,
            'errors' => '',
        ];
        return response()->json($resp, $this->statusCode);
    }
}