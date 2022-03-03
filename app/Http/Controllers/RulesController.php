<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Rules;
use Illuminate\Http\Request;
use Validator as validator;

class RulesController extends Controller
{
  public function getLaws()
  {
      
    $rules = Rules::orderBy('rules', 'asc')->get();
    return response()->json($rules)->setStatusCode(200);
  }
}
