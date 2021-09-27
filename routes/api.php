<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\api\AuthController;
use App\Http\Controllers\api\user\Enquiry;
use App\Http\Controllers\api\user\MeetingController;
use App\Http\Controllers\api\user\Sos;
use App\Http\Controllers\api\user\TrainingController;
use App\Http\Controllers\api\user\UserController;

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

Route::group(['prefix' => 'auth'], function ($router) {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('refresh', [AuthController::class, 'refresh']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('profile', [AuthController::class, 'profile']);
});

Route::post('user/signup', [AuthController::class, 'signup']);

Route::group(['middleware' => 'jwt.verify'], function () {

    Route::put('users/password/{id}', [UserController::class, 'password']);
    Route::get('users/search', [UserController::class, 'search']);
    Route::resource('users', UserController::class);
    Route::resource('sos', Sos::class);
    //meeting routes
    #update meeting status
    Route::post('meetings/update/{id}/status', [MeetingController::class, 'update_status']);
    Route::resource('meetings', MeetingController::class);
});

//enquiries
Route::resource('enquiries', Enquiry::class);

//Trainning
Route::get("trainings/videos/{id}", [TrainingController::class, 'videos']);

//admin routes
Route::prefix('admin')
    ->middleware('jwt.verify')
    ->group(__DIR__ . '/admin/index.php');
