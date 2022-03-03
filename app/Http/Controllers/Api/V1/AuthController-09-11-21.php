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
use App\Models\Post;
use App\Models\Comment;
use App\Models\VoxoMediaLike;
use Illuminate\Support\Str;
use App\Models\Page;
use App\Models\Notification;
use App\Models\UserFollower;
use App\Models\ApiLogs;
use TCG\Voyager\Facades\Voyager;
use App\Models\VoxoMedias;
use App\Models\UserSaveMedias;
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
            'name' => 'required',
            'mobile_number' => 'required|regex:/^[0-9]{9,12}$/',
            'email' => 'required|email',
            'password' => 'required|min:8',
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
        $checkuser = User::where(['mobile_number' => $request->get('mobile_number'), 'role_id' => USER_ROLE])->count();
        if($checkuser == 1){
            $resp = [
                'status' => false,
                'data' => '',
                'message' => 'The phone number has already been taken',
                'error' => true,
                'errors' => '',
            ];
            return response()->json($resp, $this->statusCode);
        } 

        $checkuser = User::where(['email' => $request->get('email'), 'role_id' => USER_ROLE])->count();
        if($checkuser == 1){
            $resp = [
                'status' => false,
                'data' => $resposeArray,
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
            'name' => $data['name'],
            'mobile_number' => $data['mobile_number'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'device_token' => $data['device_token'],
            'device_type' => $data['device_type'],
            'otp' => $otp
        ];
            //dd($arr);
        try {

            $user = User::create($arr);

            $notify_message = $otp . ' is the OTP for accessing your Mealox account. PLEASE DO NOT SHARE IT WITH ANYONE.';
            $notification_data = ['type' => 'otp', 'message' => $notify_message, 'user_id' => $user->id];
            $sendSms = sendGCMUser($user->device_token, 'Verify OTP', $notify_message, $user->device_type, $notification_data);
            $this->sendSms2($notify_message, $data['mobile_number']);
            //$this->sendSms($notify_message, $data['phone']);

            $passportToken = $user->createToken('API Access Token');
            $passportToken->token->save();
            $token = $passportToken->accessToken;

        } catch (\Exception $e) {
            return $this->respondInternalError($e->getMessage());
        }

        $resp = [
            'status' => true,
            'data' => $user,
            'token' => $token,
            'message' => 'Otp send successfully.',
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
            'mobile_number' => 'required',
            'country_code' => 'required'
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

        
        $checkuser = User::where(['mobile_number' => $request->get('mobile_number'), 'role_id' => USER_ROLE])->count();
        $otp = randomOTP();
        $resposeArray = array(
            'country_code' => '+'.$request->get('country_code'),
            'mobile_number' => $request->get('mobile_number'),
            'otp' => $otp
        );
        
        if($checkuser == 0){
            $arr = [
                'role_id' => USER_ROLE,
                'country_code' => '+'.$request->get('country_code'),
                'mobile_number' => $request->get('mobile_number'),
                'user_name' => rand(1111111111,9999999999),
                'password' => Hash::make('123456'),
                'status' => 1,
                'device_token' => $request->get('device_token'),
                'device_type' => $request->get('device_type'),
                'otp' => $otp
            ];
            $user = User::create($arr);
        }else{
            $checkuseractive = User::where(['mobile_number' => $request->get('mobile_number'), 'status' => 0])->count();
            if($checkuseractive > 0){
                return $this->respond([
                    'status' => false,
                    'message' => 'User not active.',
                    'data' => '',
                    'country' => '',
                    'error' => true,
                    'errors' => '',
                ]);
            }

            User::where('mobile_number', $request->get('mobile_number'))->update(['otp'=>$otp, 'country_code'=>'+'.$request->get('country_code')]);
        }   
        
        try {
            $user = User::where('mobile_number', $request->get('mobile_number'))->first();
            // Save generated token
            $notify_message = $otp . ' is the OTP for accessing your VOXO account. PLEASE DO NOT SHARE IT WITH ANYONE.';
            $notification_data = ['type' => 'otp', 'message' => $notify_message, 'user_id' => $user->id];
          
            $account_sid = 'ACe492e295a064aafb79968a887631bf8d';
            $auth_token = '424e4e85e58b819bd993a5d278b8fe13';
            $twilio_number = 'Voxo';

            //$receiverNumber = '+'.$request->get('country_code').$request->get('mobile_number');
            //$client = new Client($account_sid, $auth_token);
            //$client->messaging->v1->services->create($receiverNumber, ['from' => $twilio_number, 'body' => $notify_message]);

            // $message = $client->messages
            //       ->create($receiverNumber, // to
            //                [
            //                    "body" => $notify_message,
            //                    "messagingServiceSid" => "MGa388db1ef267b5b095140b3f8c4e35f5"
            //                ]
            //       );

            //$sendSms = sendGCMUser($user->device_token, 'Verify OTP', $notify_message, $user->device_type, $notification_data);
            $countries = Countries::select('id', 'name', 'iso2', 'dialcode')->where('dialcode', $request->get('country_code'))->first();
            //$this->sendSms($notify_message, $user->phone);

        } catch (\Exception $e) {
            return $this->respondInternalError($e->getMessage());
        }

        return $this->respond([
            'status' => true,
            'message' => 'OTP sent successfully.',
            'data' => $resposeArray,
            'country' => $countries,
            'error' => false,
            'errors' => '',
        ]);
    }

    public function verifyOtp(Request $request) {

        $validation = Validator::make($request->all(), [
            'mobile_number' => 'required',
            'otp' => 'required',
            'country_code' => 'required'
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
        $user = User::where(['mobile_number'=> $request->get('mobile_number'), 'otp' => $request->otp])->first();
       
        if($user){
            if($request->language_id !== null){
                $language_id = $request->language_id;
            }else{
                $language_id = NULL;
            }
            DB::table('users')->where('id', $user->id)->update(['status' => 1, 'language_id' => $language_id, 'is_otp_verified' => 1]);
            $passportToken = $user->createToken('API Access Token');
            $passportToken->token->save();
            $token = $passportToken->accessToken;
            //$dataArray[] = $user;
            $langData = Language::get();
            $countries = Countries::select('id', 'name', 'iso2', 'dialcode')->where('dialcode', $request->get('country_code'))->first();
            $resp = [
                'status' => true,
                'data' => $user,
                'language' => $langData,
                'country' => $countries,
                'is_register' => 1,
                'token' => $token,
                'message' => 'Logged in successfully',
                'error' => false,
                'errors' => '',
            ];
        }else{
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

    public function resendOtp(Request $request)
    {
        $data = $request->all();
        $validation = Validator::make($request->all(), [
            'mobile_number' => 'required',
            'country_code' => 'required'
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
            'mobile_number' => $request->get('mobile_number'),
            'otp' => $otp
        );
        $user = User::where(['users.mobile_number' => $data['mobile_number'], 'role_id' => USER_ROLE])->count();
        if($user > 0){
           
            DB::table('users')->where('mobile_number', $data['mobile_number'])->update(['otp' => $otp]);
            $user = User::where('users.mobile_number', $data['mobile_number'])->first();
            
            $notify_message = $otp . ' is the OTP for accessing your VOXO account. PLEASE DO NOT SHARE IT WITH ANYONE.';
            $notification_data = ['type' => 'otp', 'message' => $notify_message, 'user_id' => $user->id];
            
            $account_sid = 'ACe492e295a064aafb79968a887631bf8d';
            $auth_token = '424e4e85e58b819bd993a5d278b8fe13';
            $twilio_number = 'Voxo';

            $receiverNumber = '+'.$request->get('country_code').$request->get('mobile_number');
            $client = new Client($account_sid, $auth_token);
            //$client->messaging->v1->services->create($receiverNumber, ['from' => $twilio_number, 'body' => $notify_message]);

            $message = $client->messages
                  ->create($receiverNumber, // to
                           [
                               "body" => $notify_message,
                               "messagingServiceSid" => "MGa388db1ef267b5b095140b3f8c4e35f5"
                           ]
                  );
            //$sendSms = sendGCMUser($user->device_token, 'Verify OTP', $notify_message, $user->device_type, $notification_data);
            $countries = Countries::select('id', 'name', 'iso2', 'dialcode')->where('dialcode', $request->get('country_code'))->first();
            $resp = [
                'status' => true,
                'data' => $resposeArray,
                'country' => $countries,
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
                'user_name' => rand(1111111111,9999999999),
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
        $rule = [
            'country_id' => 'required',
        ];
        $data = $request->all();
        $states = array();
        if ($this->validateData($data, $rule)) {
            $states = States::where('country_id', $data['country_id'])->get();
        }
        $resp = [
            'status' => true,
            'data' => $states ,
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

    public function changePassword(Request $request)
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
            'phone' => 'required',
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

        $user = User::where('users.mobile_number', $data['phone'])->count();
        if($user > 0){
            $password = randomPassword();
            $userProfileArr = array(
                'password' => Hash::make($password),
            );

            DB::table('users')->where('mobile_number', $data['phone'])->update($userProfileArr);
            $user = User::where('users.mobile_number', $data['phone'])->first();
            
            $notify_message = $password . ' is the password for accessing your MLM account';
            $notification_data = ['type' => 'forgot', 'message' => $notify_message, 'user_id' => $user->id];
            $sendSms = sendGCMUser($user->device_token, 'Forgot password', $notify_message, $user->device_type, $notification_data);
            $this->sendSms2($notify_message, $data['phone']);
            $resp = [
                'status' => true,
                'data' => '',
                'message' => 'Password sent to your registred phone number.',
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
        
        if($request->hasFile('image')) {
            $files = $request->file('image');
            
            $s3 = Storage::disk('s3');
            $file_name = 'img_'.time() . "." . $files->getClientOriginalExtension();
            $s3filePath = '/users/'.$file_name;
            $s3->put($s3filePath, file_get_contents($files), 'public');
            $saveavatarImage = ENV('AWS_URL').$s3filePath;
            
            $thumb_file_name = 'thumb_img_'.time() . "." . $files->getClientOriginalExtension();
            $s3thumbfilePath = '/userthumbs/'.$thumb_file_name;

            $imgThumb = Image::make($files->getRealPath())->resize(300, 300)->stream(); ##create thumbnail
            $path = Storage::disk('s3');
            $path->put($s3thumbfilePath, $imgThumb->__toString(),'public');
            $saveavatarThumbImage = ENV('AWS_URL').$s3thumbfilePath;
        }

        $userProfileArr = array(
            'name' => isset($data['name']) && $data['name'] != '' ? $data['name'] : $user->name,
            'user_name' => isset($data['nick_name']) && $data['nick_name'] != '' ? $data['nick_name'] : $user->user_name,
            'user_bio' => isset($data['user_bio']) && $data['user_bio'] != '' ? $data['user_bio'] : $user->user_bio,
            'mobile_number' => isset($data['mobile_number']) && $data['mobile_number'] != '' ? $data['mobile_number'] : $user->mobile_number,
            //'email' => isset($data['email']) && $data['email'] != '' ? $data['email'] : $user->email,
            'gender' => isset($data['gender']) && $data['gender'] != '' ? $data['gender'] : $user->gender,
            'dob' => isset($data['dob']) && $data['dob'] != '' ? date('Y-m-d', strtotime($data['dob'])) : $user->dob,
            'avatar' => $saveavatarImage,
            'thumb_avatar' => $saveavatarThumbImage,
            'language_id' => isset($data['language_id']) && $data['language_id'] != '' ? $data['language_id'] : $user->language_id,
            'updated_at' => date('Y-m-d H:i:s'),
        );

        DB::table('users')->where('id', $user->id)->update($userProfileArr);


        $user = User::select('id', 'user_name as nick_name', 'name','email', 'mobile_number', 'user_bio', 'avatar', 'thumb_avatar', 'dob')->where('users.id', $user->id)->first();

        $dataRow['id'] = $user->id;
        $dataRow['nick_name'] = $user->nick_name;
        $dataRow['name'] = $user->name;
        $dataRow['email'] = $user->email;
        $dataRow['mobile_number'] = $user->mobile_number;
        $dataRow['dob'] = $user->dob;
        $dataRow['followers'] = 0;
        $dataRow['following'] = 0;
        $dataRow['likes'] = 0;
        $dataRow['user_bio'] = $user->user_bio;
        $dataRow['full_image'] = $user->avatar;
        $dataRow['thumb_image'] = $user->thumb_avatar;

        $dataArray[] = $dataRow;
        $this->userSetHidden($user);
        
        $resp = [
            'status' => true,
            'data' => $dataRow,
            'message' => 'Profile updated successfully.',
            'error' => false,
            'errors' => '',
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
 
    public function checkNickName(Request $request){

        $user = Auth::user();

        $validation = Validator::make($request->all(), [
            'nick_name' => 'required',
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

        $checkUserName = User::where('user_name', $data['nick_name'])->where('id', '<>', $user->id)->count();
        if($checkUserName > 0){
            $resp = [
                'status' => false,
                'data' => '',
                'message' => 'Nick name already exist',
                'error' => true,
                'errors' => '',
            ];
        }else{
            $resp = [
                'status' => true,
                'data' => '',
                'message' => 'Nick name available',
                'error' => false,
                'errors' => '',
            ];
        }

        return response()->json($resp, $this->statusCode);
        
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

    public function notification(Request $request)
    {
        $userID = Auth::user();
        $this->data = Notification::select('title', 'content', 'created_at')->where('user_id', $userID->id)->get();
        $input = array(
            'is_read' => 1,
        );
        Notification::where('user_id', $userID->id)->update($input);
        $resp = [
            'status' => true,
            'data' => $this->data,
            'message' => 'Success.',
            'error' => false,
            'errors' => '',
            //'user_path' => env('APP_URL').'/public/images/profile/',
        ];
        return response()->json($resp, $this->statusCode);
    }

    public function sendSms2($message, $mobile){
        

        $url = 'http://msg.ampleinfosoft.biz/submitsms.jsp?user=ayuvahel&key=c79d1b0438XX&mobile='.$mobile.'&message='.urlencode($message).'&senderid=AYUVAL&accusage=1';

        //echo $url;die;
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);
        return $response;
        //print_r($response);die;
    }
    
    public function sendSms($message, $mobile)
    {
        
        $fields = array(
            "sender_id" => "FSTSMS",
            "message" => $message,
            "language" => "english",
            "route" => "p",
            "numbers" => $mobile,
            "flash" => "1"
        );

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://www.fast2sms.com/dev/bulk",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode($fields),
            CURLOPT_HTTPHEADER => array(
                "authorization: 6pAFulWXI7UwkjGn8dyE4QgJeD5R0q2cimazLxTvOs3C1NfHbhcMhZ1EH0ib8tGxYFQayAfCOPUKgp73",
                "accept: */*",
                "cache-control: no-cache",
                "content-type: application/json"
            ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);
        return $response;
       // print_r($response);die;
        if ($err) {
            echo "cURL Error #:" . $err;
        } else {
            echo $response;
        }
    }

    public function getHomeList(Request $request){
       
        $query = Post::select('posts.*', DB::raw('(select count(*) from voxo_media_comments where posts.id = voxo_media_comments.voxo_media_id) as comments_count'), DB::raw('(select count(*) from voxo_media_likes where posts.id = voxo_media_likes.voxo_media_id) as likes_count'), DB::raw('(select count(*) from voxo_media_likes where posts.author_id = 0 and voxo_media_likes.voxo_media_id = posts.id) as islike_count'), DB::raw('(select count(*) from user_save_medias where posts.author_id = 0 and user_save_medias.voxo_media_id = posts.id) as isfav_count'))->with('postMedia', 'users')->where('post_type', 'WALL');
        if(isset($request->language_id) && $request->language_id != ''){
            $query->where('language_id', $request->language_id);
        }
        $getVoxoMedia = $query->orderBy('id', 'DESC')->paginate(10);

        if($getVoxoMedia){
            foreach ($getVoxoMedia as $value) {
                Post::where(['is_read' => 0, 'id' => $value->id])->update(['is_read' => 1]);
            }
        }
        $resp = [
            'status' => true,
            'data' => $getVoxoMedia,
            'message' => 'success',
            'error' => false,
            'errors' => '',
        ];
        return response()->json($resp, $this->statusCode);
    }

    public function getReelsList(Request $request){

        $query = Post::select('posts.*', DB::raw('(select count(*) from voxo_media_comments where posts.id = voxo_media_comments.voxo_media_id) as comments_count'), DB::raw('(select count(*) from voxo_media_likes where posts.id = voxo_media_likes.voxo_media_id) as likes_count'), DB::raw('(select count(*) from voxo_media_likes where posts.author_id = 0 and voxo_media_likes.voxo_media_id = posts.id) as islike_count'), DB::raw('(select count(*) from user_save_medias where posts.author_id = 0 and user_save_medias.voxo_media_id = posts.id) as isfav_count'))->with('postMedia', 'users')->where('post_type', 'REEL')->where('is_local', '0');

        if(isset($request->language_id) && $request->language_id != ''){
            $query->where('language_id', $request->language_id);
        }
        $getVoxoMedia = $query->orderBy('id', 'DESC')->paginate(10);

        if($getVoxoMedia){
            foreach ($getVoxoMedia as $value) {
                Post::where(['is_read' => 0, 'id' => $value->id])->update(['is_read' => 1]);
            }
        }
        
        $resp = [
            'status' => true,
            'data' => $getVoxoMedia,
            'message' => 'success',
            'error' => false,
            'errors' => '',
        ];
        return response()->json($resp, $this->statusCode);
    }

    public function getAuthHomeList(Request $request){
        $user = Auth::user();
        $query = Post::select('posts.*', DB::raw('(select count(*) from voxo_media_comments where posts.id = voxo_media_comments.voxo_media_id) as comments_count'), DB::raw('(select count(*) from voxo_media_likes where posts.id = voxo_media_likes.voxo_media_id) as likes_count'), DB::raw('(select count(*) from voxo_media_likes where voxo_media_likes.user_id = '.$user->id.' and voxo_media_likes.voxo_media_id = posts.id) as islike_count'), DB::raw('(select count(*) from user_save_medias where user_save_medias.user_id = '.$user->id.' and user_save_medias.voxo_media_id = posts.id) as isfav_count'))->with('postMedia', 'users')->where('post_type', 'WALL');

        if(isset($request->language_id) && $request->language_id != ''){
            $query->where('language_id', $request->language_id);
        }
        $getVoxoMedia = $query->orderBy('id', 'DESC')->paginate(10);

        $resp = [
            'status' => true,
            'data' => $getVoxoMedia,
            'message' => 'success',
            'error' => false,
            'errors' => '',
        ];
        return response()->json($resp, $this->statusCode);
    }

    public function getAuthReelsList(Request $request){
        $user = Auth::user();
        $query = Post::select('posts.*', DB::raw('(select count(*) from voxo_media_comments where posts.id = voxo_media_comments.voxo_media_id) as comments_count'), DB::raw('(select count(*) from voxo_media_likes where posts.id = voxo_media_likes.voxo_media_id) as likes_count'), DB::raw('(select count(*) from voxo_media_likes where voxo_media_likes.user_id = '.$user->id.' and voxo_media_likes.voxo_media_id = posts.id) as islike_count'), DB::raw('(select count(*) from user_save_medias where user_save_medias.user_id = '.$user->id.' and user_save_medias.voxo_media_id = posts.id) as isfav_count'))->with('postMedia', 'users')->where('post_type', 'REEL')->where('is_local', '0');
        
        if(isset($request->language_id) && $request->language_id != ''){
            $query->where('language_id', $request->language_id);
        }
        $getVoxoMedia = $query->orderBy('id', 'DESC')->paginate(10);

        $resp = [
            'status' => true,
            'data' => $getVoxoMedia,
            'message' => 'success',
            'error' => false,
            'errors' => '',
        ];
        return response()->json($resp, $this->statusCode);
    }
    
    public function getMyMedia(Request $request){
        $user = Auth::user();
        
        if($request->get('type') != null && $request->get('type') == 'myReels'){
            $getVoxoMedia = Post::select('posts.*', DB::raw('(select count(*) from voxo_media_comments where posts.id = voxo_media_comments.voxo_media_id) as comments_count'), DB::raw('(select count(*) from voxo_media_likes where posts.id = voxo_media_likes.voxo_media_id) as likes_count'), DB::raw('(select count(*) from voxo_media_likes where posts.author_id = voxo_media_likes.user_id and voxo_media_likes.voxo_media_id = posts.id) as islike_count'), DB::raw('(select count(*) from user_save_medias where posts.author_id = user_save_medias.user_id and user_save_medias.voxo_media_id = posts.id) as isfav_count'))->with('postMedia', 'users')->where('author_id', $user->id)->where('post_type', 'REEL')->where('is_local', '0')->orderBy('id', 'DESC')->paginate(10);
            
        }elseif($request->get('type') != null && $request->get('type') == 'savedReels'){
            $getVoxoMedia = Post::select('posts.*', DB::raw('(select count(*) from voxo_media_comments where posts.id = voxo_media_comments.voxo_media_id) as comments_count'), DB::raw('(select count(*) from voxo_media_likes where posts.id = voxo_media_likes.voxo_media_id) as likes_count'), DB::raw('(select count(*) from voxo_media_likes where posts.author_id = voxo_media_likes.user_id and voxo_media_likes.voxo_media_id = posts.id) as islike_count'), DB::raw('(select count(*) from user_save_medias where user_save_medias.user_id = '.$user->id.' and user_save_medias.voxo_media_id = posts.id) as isfav_count'))->with('postMedia', 'users')->leftJoin('user_save_medias', 'user_save_medias.voxo_media_id', '=', 'posts.id')->where('user_save_medias.user_id', $user->id)->where('post_type', 'REEL')->orderBy('user_save_medias.id', 'DESC')->paginate(10);

        }elseif($request->get('type') != null && $request->get('type') == 'savedWalls'){
            $getVoxoMedia = Post::select('posts.*', DB::raw('(select count(*) from voxo_media_comments where posts.id = voxo_media_comments.voxo_media_id) as comments_count'), DB::raw('(select count(*) from voxo_media_likes where posts.id = voxo_media_likes.voxo_media_id) as likes_count'), DB::raw('(select count(*) from voxo_media_likes where posts.author_id = voxo_media_likes.user_id and voxo_media_likes.voxo_media_id = posts.id) as islike_count'), DB::raw('(select count(*) from user_save_medias where user_save_medias.user_id = '.$user->id.' and user_save_medias.voxo_media_id = posts.id) as isfav_count'))->with('postMedia', 'users')->leftJoin('user_save_medias', 'user_save_medias.voxo_media_id', '=', 'posts.id')->where('user_save_medias.user_id', $user->id)->where('post_type', 'WALL')->orderBy('user_save_medias.id', 'DESC')->paginate(10);
        }else{
            $getVoxoMedia = Post::select('posts.*', DB::raw('(select count(*) from voxo_media_comments where posts.id = voxo_media_comments.voxo_media_id) as comments_count'), DB::raw('(select count(*) from voxo_media_likes where posts.id = voxo_media_likes.voxo_media_id) as likes_count'), DB::raw('(select count(*) from voxo_media_likes where posts.author_id = voxo_media_likes.user_id and voxo_media_likes.voxo_media_id = posts.id) as islike_count'), DB::raw('(select count(*) from user_save_medias where posts.author_id = user_save_medias.user_id and user_save_medias.voxo_media_id = posts.id) as isfav_count'))->with('postMedia', 'users')->where('author_id', $user->id)->where('post_type', 'WALL')->orderBy('id', 'DESC')->paginate(10);
        }
        
        $resp = [
            'status' => true,
            'message' => 'success',
            'data' => $getVoxoMedia,
            'error' => false,
            'errors' => '',
        ];
        return response()->json($resp, $this->statusCode);
    }

   
    public function followUnfollow(Request $request){
        $user = Auth::user();

        $validation = Validator::make($request->all(), [
            'user_id' => 'required',
            'is_follow' => 'required',
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

        if($data['is_follow'] == 1){
            $checkFollow = UserFollower::where(['user_id' => $user->id, 'follower_id' => $data['user_id']])->count();
            if($checkFollow == 0){
                UserFollower::insert(['user_id' => $user->id, 'follower_id' => $data['user_id'], 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
                $message = 'Follow successfully.';
                return $this->respond([
                    'status' => true,
                    'message' => $message,
                    'data' => '',
                    'is_follow' => $data['is_follow'],
                    'error' => false,
                    'errors' => '',
                ]);
            }else{
                $message = 'Already Followed.';
                return $this->respond([
                    'status' => false,
                    'message' => $message,
                    'data' => '',
                    'error' => true,
                    'errors' => '',
                ]);
            }
        }else{
            UserFollower::where(['user_id' => $user->id, 'follower_id' => $data['user_id']])->delete();
            $message = 'Unfollow successfully.';
            return $this->respond([
                'status' => true,
                'message' => $message,
                'data' => '',
                'is_follow' => $data['is_follow'],
                'error' => false,
                'errors' => '',
            ]);
        }
        

    }

    public function followingFollowerList(Request $request){
        $user = Auth::user();
        if($request->get('limit') !== null){
            $limit = $request->get('limit');
        }else{
            $limit = 10;
        }
        if($request->get('type') == 'follower'){
            $getFollowFollowingList = UserFollower::select('users.id', 'users.name', 'users.user_name', 'users.thumb_avatar as thumb_image', DB::raw('(select count(*) from user_follow where user_follow.follower_id = users.id) as totalfoller'))->leftJoin('users', 'users.id', '=', 'user_follow.user_id')->where('follower_id', $user->id)->paginate($limit);
        }else{
            $getFollowFollowingList = UserFollower::select('users.id', 'users.name', 'users.user_name', 'users.thumb_avatar as thumb_image', DB::raw('(select count(*) from user_follow where user_follow.follower_id = users.id) as totalfoller'))->leftJoin('users', 'users.id', '=', 'user_follow.follower_id')->where('user_id', $user->id)->paginate($limit);
        }
        
        // $data['followers'] = $getFollowerList;
        // $data['followings'] = $getFollowingList;
        return $this->respond([
            'status' => true,
            'message' => $request->get('type').' List',
            'data' => $getFollowFollowingList,
            'error' => false,
            'errors' => '',
        ]);

    }

    public function getOtherUserProfile(Request $request)
    {
        $userID = Auth::user();
        $user_id = $request->get('user_id');
        $user = User::select('id', 'user_name as nick_name', 'name','email', 'mobile_number', 'user_bio', 'avatar', 'thumb_avatar', 'gender')->where('users.id', $user_id)->first();
        $totalFollower = UserFollower::where('follower_id', $user_id)->count();
        $totalFollowing = UserFollower::where('user_id', $user_id)->count();
        $isFollow = UserFollower::where(['user_id' => $userID->id, 'follower_id' => $user_id])->count();
        
        $getVoxoMedia = Post::select('posts.*', DB::raw('(select count(*) from voxo_media_comments where posts.id = voxo_media_comments.voxo_media_id) as comments_count'), DB::raw('(select count(*) from voxo_media_likes where posts.id = voxo_media_likes.voxo_media_id) as likes_count'), DB::raw('(select count(*) from voxo_media_likes where posts.author_id = voxo_media_likes.user_id and voxo_media_likes.voxo_media_id = posts.id) as islike_count'), DB::raw('(select count(*) from user_save_medias where posts.author_id = user_save_medias.user_id and user_save_medias.voxo_media_id = posts.id) as isfav_count'))->with('postMedia')->where('author_id', $user_id)->where('post_type', 'REEL')->orderBy('id', 'DESC')->paginate(10);

        $dataRow['id'] = $user->id;
        $dataRow['nick_name'] = $user->nick_name;
        $dataRow['name'] = $user->name;
        $dataRow['email'] = $user->email;
        $dataRow['mobile_number'] = $user->mobile_number;
        $dataRow['gender'] = $user->gender;
        $dataRow['dob'] = $user->dob;
        $dataRow['followers'] = $totalFollower;
        $dataRow['following'] = $totalFollowing;
        $dataRow['isFollow'] = $isFollow;
        $dataRow['likes'] = 0;
        $dataRow['user_bio'] = $user->user_bio;
        $dataRow['full_image'] = $user->avatar;
        $dataRow['thumb_image'] = $user->thumb_avatar;
        $dataRow['posts'] = $getVoxoMedia;

        $dataArray[] = $dataRow;
        $this->userSetHidden($user);
        $resp = [
            'status' => true,
            'data' => $dataRow,
            'message' => 'success',
            'error' => false,
            'errors' => '',
        ];
        return response()->json($resp, $this->statusCode);
    }

    public function getLanguages(Request $request){
        $data = Language::get();
        $resp = [
            'status' => true,
            'data' => $data,
            'message' => 'success',
            'error' => false,
            'errors' => '',
        ];
        return response()->json($resp, $this->statusCode);
    }

    public function saveLanguage(Request $request){
        $user = Auth::user();
        $validation = Validator::make($request->all(), [
            'language_id' => 'required'
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

        User::where('id', $user->id)->update(['language_id' => $request->language_id]);
        $data = Language::where('id', $request->language_id)->first();
        $resp = [
            'status' => true,
            'data' => $data,
            'message' => 'success',
            'error' => false,
            'errors' => '',
        ];
        return response()->json($resp, $this->statusCode);
    }

    public function userLanguages(Request $request){
        $user = Auth::user();
        $data = DB::select("SELECT languages.id, languages.language, (select COUNT(*) from users where users.language_id= languages.id and users.id=$user->id) as is_select_count FROM languages");
       
        $resp = [
            'status' => true,
            'data' => $data,
            'message' => 'success',
            'error' => false,
            'errors' => '',
        ];
        return response()->json($resp, $this->statusCode);
    }

    public function serarchUser(Request $request){
        $userID = Auth::user();
        if($userID){
            $getUserData = User::select('users.*', DB::raw('(select count(*) from user_follow where user_follow.follower_id = users.id) as totalFollower'), DB::raw('(select count(*) from user_follow where user_follow.user_id = '.$userID->id.' AND user_follow.follower_id =  users.id) as isFollow'))->where('name', 'LIKE', "%{$request->get('keyword')}%")->orWhere('email', 'LIKE', "%{$request->get('keyword')}%")->orWhere('user_name', 'LIKE', "%{$request->get('keyword')}%")->paginate(10);
        }else{
            $getUserData = User::select('users.*', DB::raw('(select count(*) from user_follow where user_follow.follower_id = users.id) as totalFollower'))->where('name', 'LIKE', "%{$request->get('keyword')}%")->orWhere('email', 'LIKE', "%{$request->get('keyword')}%")->orWhere('user_name', 'LIKE', "%{$request->get('keyword')}%")->paginate(10);
        }

        return $this->respond([
            'status' => true,
            'message' => 'success',
            'data' => $getUserData,
            'error' => false,
            'errors' => '',
        ]);
    }

    public function checkNewPost(Request $request)
    {
        $formatted_date =date('Y-m-d H:i:s', strtotime("-1 min"));
        $result = DB::table('posts')->where('is_read', 0)->where('created_at','>=',$formatted_date)->count();
        return $this->respond([
            'status' => true,
            'message' => 'success',
            'data' => $result > 0 ? 1 : 0,
            'error' => false,
            'errors' => '',
        ]);

    }

    public function changeEmail(Request $request)
    {
        
        $user = Auth::user();

        $validation = Validator::make($request->all(), [
            'email' => 'required|email|unique:users,email,'.$user->id.',id' 
        ]);
        
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
        $email = $request->email;
        $token = Str::random(64);
        $checkTempEmail = DB::table('temp_email')->where('email', $email)->count();
        if($checkTempEmail > 0){
            DB::table('temp_email')->where('email', $email)->update(['user_id' => $user->id, 'email' => $email, 'is_verify' => 0, 'token' => $token]);
        }else{
            DB::table('temp_email')->insert(['user_id' => $user->id, 'email' => $email, 'is_verify' => 0, 'token' => $token]);
        }

        $toName = $user->name;

        Mail::send('emailVerificationEmail', ['token' => $token], function($message) use($request){
              $message->to($request->email);
              $message->subject('Email Verification Mail');
          });
       
        $resp = [
            'status' => true,
            'data' => '',
            'message' => 'Please check your inbox and active your email address.',
            'error' => false,
            'errors' => '',
            //'user_path' => env('APP_URL').'/public/images/profile/',
        ];
        return response()->json($resp, $this->statusCode);

    }
}