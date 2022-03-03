<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Laws;
use Illuminate\Http\Request;
use Validator as validator;

class LawsController extends Controller
{
  public function getLaws()
  {
    $laws = Laws::orderBy('laws', 'asc')->get();
    return response()->json($laws)->setStatusCode(200);
  }
  public function getSingleLaw(Request $request)
  {
    $filename = $request->url;
    $text = file_get_contents($filename);
    return response()->json($text)->setStatusCode(200);
  }
}
