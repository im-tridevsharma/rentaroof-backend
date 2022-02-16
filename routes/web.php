<?php

use App\Models\User;
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
    return time();
});

Route::get('verify-email/{id}', function ($id) {
    $system_id = base64_decode($id, true);
    if ($system_id) {
        $user = User::where("system_userid", $system_id)->first();
        $user->email_verified = 1;
        if ($user->mobile_verified) {
            $user->account_status = 'activated';
        }
        $user->save();
        return '<h2>Email Verified Successfully.</h2>';
    } else {
        return 'Invalid verification token!';
    }
});

Route::get('verify-mobile/{id}', function ($id) {
    $system_id = base64_decode($id, true);
    if ($system_id) {
        $user = User::where("system_userid", $system_id)->first();
        $user->mobile_verified = 1;
        if ($user->email_verified) {
            $user->account_status = 'activated';
        }
        $user->save();
        return '<h2>Mobile Verified Successfully.</h2>';
    } else {
        return 'Invalid verification token!';
    }
});
