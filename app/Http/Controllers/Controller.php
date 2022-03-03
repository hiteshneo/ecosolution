<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use App\Models\Page;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;
use App\User;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    public function getCmsPage(Request $request, $slug)
    {
        $data = Page::where('slug', $slug)->first();
        return view('page', compact('data'));
    }

    public function verify(Request $request, $token)
    {
        if($token != ''){
            $checkToken = DB::table('temp_email')->where('token', $token)->count();
            if($checkToken > 0){
                $getData = DB::table('temp_email')->where('token', $token)->first();
                User::where(['id' => $getData->user_id])->update(['email' => $getData->email]);
                DB::table('temp_email')->where(['user_id' => $getData->user_id])->delete();
            }
            $message = '<h1>Thank you!</h1> <br><p>Your Email verified successfully.</p>';
        }else{
            $message = '<p>Email verification link is expired or not valid.</p>';
        }

        return view('verifyemail', compact('message'));
        
    }
}