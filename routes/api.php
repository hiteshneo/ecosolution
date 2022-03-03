<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

/*Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});*/

Route::group(['namespace' => 'Api\V1', 'prefix' => 'v1', 'as' => 'v1.'], function () {
    Route::group(['prefix' => 'auth', 'middleware' => ['guest']], function () {
        Route::post('register', 'AuthController@register');
        Route::post('login', 'AuthController@login');
        Route::post('verifyOtp', 'AuthController@verifyOtp');
        Route::post('forgotPassword', 'AuthController@forgotPassword');
        Route::post('resendOtp', 'AuthController@resendOtp');
        Route::post('socialLogin', 'AuthController@socialLogin');
        // Password Reset
        Route::post('password/email', 'ForgotPasswordController@sendResetLinkEmail');
        Route::get('countries', 'AuthController@getCountries');
        Route::post('state', 'AuthController@getStates');
        Route::post('city', 'AuthController@getCity');
    });

    Route::group(['middleware' => ['auth:api']], function () {
        Route::group(['prefix' => 'auth'], function () {
            Route::post('logout', 'AuthController@logout');
            
            Route::get('getProfile', 'AuthController@me');
            Route::post('userProfile', 'AuthController@userProfile');
            Route::post('updatePassword', 'AuthController@updatePassword')->name('updatePassword');
            Route::post('getOtherUserProfile', 'AuthController@getOtherUserProfile');
            Route::post('updateDetail', 'AuthController@updateDetail')->name('updateDetail');
            
            Route::post('updateProfile', 'AuthController@updateProfile');
            Route::post('change-email', 'AuthController@changeEmail');
            Route::post('notification-list', 'AuthController@notificationList');
        });
        //cmsPage
        Route::post('cmsPage', 'AuthController@cmsPage');

        
    });

    // Page
    Route::post('cmsPage', 'AuthController@cmsPage');
    Route::apiResource('privacy-policy', 'AuthController@privacyPolicy');
});