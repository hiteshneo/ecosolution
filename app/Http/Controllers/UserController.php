<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\User;
use Illuminate\Http\Request;
use Validator as validator;

class UserController extends Controller
{
  public function getAllUsers()
  {
    $users = User::get();
    return response()->json($users)->setStatusCode(200);
  }

  public function login(Request $request)
  {
    $device_id = $request->device_id;
    $query = User::where('device_id', '=', $device_id);

    if ($query->count() > 0) {
      $user = $query->select('e_date')->first();
      return response()->json([
        'success' => true,
        'data' => $user
      ]);
    } else {
      return response()->json([
        'success' => false,
        'error' => true
      ], 404);
    }
    return response()->json($user, 200);
  }

  public function register(Request $request)
  {
    $rules = [
      'name' => 'required',
      'email' => 'required',
      'phone' => 'required',
      'state' => 'required',
      'org' => 'required',
      'device_id' => 'required|unique:users',
      'r_date' => 'required',
      'e_date' => 'required',
    ];
    $validator = validator::make($request->all(), $rules);
    if ($validator->fails()) {
      return response()->json([
        $validator->errors(),
        'success' => 'false',
        'error' => true
      ], 400);
    }
    $user = User::create($request->all());
    return response()->json([
      'data' => $user,
      'success' => true
    ], 200);
  }
  public function getStatus($id)
  {
    $query = User::where('device_id', '=', $id)->get();
    if ($query->count() > 0) {
      return response()->json(['success' => true])->setStatusCode(200);
    } else {
      return response()->json(['success' => false])->setStatusCode(404);
    }
  }
}
