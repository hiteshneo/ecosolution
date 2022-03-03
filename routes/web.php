<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/
Route::get('page/{slug}', 'Controller@getCmsPage');
Route::get('verify/{token}', 'Controller@verify')->name('verify');


Route::get('/', function () {
    return redirect()->route('voyager.login');
   // return view('welcome');
});


Route::group(['prefix' => 'admin'], function () {
    Voyager::routes();
});