<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\api\AuthController;
use App\Http\Controllers\api\user\Enquiry;
use App\Http\Controllers\api\user\MeetingController;
use App\Http\Controllers\api\user\PropertyAddressController;
use App\Http\Controllers\api\user\PropertyController;
use App\Http\Controllers\api\user\PropertyGalleryController;
use App\Http\Controllers\api\user\SaveSearches;
use App\Http\Controllers\api\user\SettingController;
use App\Http\Controllers\api\user\Sos;
use App\Http\Controllers\api\user\TrainingController;
use App\Http\Controllers\api\user\UserController;
use App\Http\Controllers\api\user\UserSavedPropertyController;
use App\Models\Amenity;
use App\Models\City;
use App\Models\Country;
use App\Models\State;

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


Route::get("properties/search", [PropertyController::class, 'search']);
Route::get("properties/code/{id}", [PropertyController::class, 'code']);
Route::group(['middleware' => 'jwt.verify'], function () {

    Route::get("countries", function () {
        return response([
            'status'    => true,
            'message'   => 'Countries fecthed successfully.',
            'data'      => Country::all()
        ]);
    });

    Route::get("states", function () {
        return response([
            'status'    => true,
            'message'   => 'States fecthed successfully.',
            'data'      => State::all()
        ]);
    });

    Route::get("cities", function () {
        return response([
            'status'    => true,
            'message'   => 'Cities fecthed successfully.',
            'data'      => City::all()
        ]);
    });

    Route::get("amenities", function () {
        return response([
            'status'    => true,
            'message'   => 'Amenities fecthed successfully.',
            'data'      => Amenity::all()
        ]);
    });

    Route::put('users/password/{id}', [UserController::class, 'password']);
    Route::get('users/search', [UserController::class, 'search']);
    Route::resource('users/searches', SaveSearches::class);
    Route::resource('users/savedproperties', UserSavedPropertyController::class);
    Route::resource('users', UserController::class);
    Route::resource('sos', Sos::class);
    //meeting routes
    #update meeting status
    Route::post('meetings/update/{id}/status', [MeetingController::class, 'update_status']);
    Route::resource('meetings', MeetingController::class);

    //Trainning
    Route::get("trainings/videos/{id}", [TrainingController::class, 'videos']);
    Route::get("trainings/pdfs/{id}", [TrainingController::class, 'pdfs']);

    //settings
    Route::get("settings/{id}", [SettingController::class, 'get']);
    Route::post("settings/{id}", [SettingController::class, 'set']);
    Route::post("settings/account_status/{id}", [SettingController::class, 'change_account_status']);

    //properties
    Route::post("properties/amenities", [PropertyController::class, 'amenity']);
    Route::post("properties/essentials", [PropertyController::class, 'essential']);
    Route::put("properties/essentials/{id}", [PropertyController::class, 'essentialUpdate']);
    Route::post("properties/total", [PropertyController::class, 'total']);
    Route::resource('properties', PropertyController::class);
    Route::resource('properties/galleries', PropertyGalleryController::class);
    Route::resource('properties/addresses', PropertyAddressController::class);
});

//enquiries
Route::resource('enquiries', Enquiry::class);

//admin routes
Route::prefix('admin')
    ->middleware('jwt.verify')
    ->group(__DIR__ . '/admin/index.php');
