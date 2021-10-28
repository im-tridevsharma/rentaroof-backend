<?php

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
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

Route::get('/', function () {
    $pdf = App::make('dompdf.wrapper');
    $s = DB::table("settings")->where("setting_key", "agreement_template")->first();
    $s = str_replace("[[LANDLORD_FULL_NAME]]", "Tridev Sharma", $s->setting_value);
    $pdf->loadHTML($s);
    return $pdf->stream();
});
