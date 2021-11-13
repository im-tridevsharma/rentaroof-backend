<?php

use App\Http\Controllers\api\admin\PageManagement;
use App\Http\Controllers\api\admin\SettingController as AdminSettingController;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\api\AuthController;
use App\Http\Controllers\api\user\AgreementController;
use App\Http\Controllers\api\user\Enquiry;
use App\Http\Controllers\api\user\IboNotificationController;
use App\Http\Controllers\api\user\IboRatingController;
use App\Http\Controllers\api\user\LandlordNotificationController;
use App\Http\Controllers\api\user\LandlordRatingController;
use App\Http\Controllers\api\user\MeetingController;
use App\Http\Controllers\api\user\PropertyAddressController;
use App\Http\Controllers\api\user\PropertyController;
use App\Http\Controllers\api\user\PropertyGalleryController;
use App\Http\Controllers\api\user\RatingandReviewController;
use App\Http\Controllers\api\user\ReferralPointController;
use App\Http\Controllers\api\user\SaveSearches;
use App\Http\Controllers\api\user\SettingController;
use App\Http\Controllers\api\user\Sos;
use App\Http\Controllers\api\user\TenantNotificationController;
use App\Http\Controllers\api\user\TenantRatingController;
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

Route::get('profile/code/{code}', [AuthController::class, 'profileByCode']);
Route::post('user/signup', [AuthController::class, 'signup']);

Route::get('page', [PageManagement::class, 'getPage']);
Route::get('pages', [PageManagement::class, 'getPages']);
Route::get('website/initials/{key}', [AdminSettingController::class, 'get']);

Route::get("properties/search", [PropertyController::class, 'search']);
Route::get("properties/for_verification", [PropertyController::class, 'for_verification'])->middleware('jwt.verify');
Route::post("properties/change_verification_status/{id}", [PropertyController::class, 'change_verification_status'])->middleware('jwt.verify');
Route::get("properties/search_by_coords", [PropertyController::class, 'search_by_coords']);
Route::get("properties/similar/{code}/{limit}", [PropertyController::class, 'get_similar_properties']);
Route::get("properties/code/{id}", [PropertyController::class, 'code']);
Route::post("properties/appointment/{id}", [PropertyController::class, 'appointment']);
Route::get("properties/reviews/all/{id}", [RatingandReviewController::class, 'all']);
Route::resource("properties/reviews", RatingandReviewController::class);

Route::get("properties/ibo/{id}", [PropertyController::class, 'property_by_user']);
Route::get("properties/landlord/{id}", [PropertyController::class, 'property_by_user']);

Route::get('ratings/ibo/all/{id}', [IboRatingController::class, 'all']);
Route::resource('ratings/ibo', IboRatingController::class);
Route::get("ibo/notifications/seen/{id}", [IboNotificationController::class, 'seen']);
Route::resource('ibo/notifications', IboNotificationController::class);

Route::get('ratings/landlord/all/{id}', [LandlordRatingController::class, 'all']);
Route::resource('ratings/landlord', LandlordRatingController::class);
Route::get("landlord/notifications/seen/{id}", [LandlordNotificationController::class, 'seen']);
Route::resource('landlord/notifications', LandlordNotificationController::class);

Route::get("tenant/notifications/seen/{id}", [TenantNotificationController::class, 'seen']);
Route::resource('tenant/notifications', TenantNotificationController::class);
Route::get('ratings/tenant/all/{id}', [TenantRatingController::class, 'all']);
Route::resource('ratings/tenant', TenantRatingController::class);
Route::get('users/referrals', [ReferralPointController::class, 'getReferrals']);

Route::resource('agreements', AgreementController::class);

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

Route::group(['middleware' => 'jwt.verify'], function () {

    Route::put('users/password/{id}', [UserController::class, 'password']);
    Route::get('users/search', [UserController::class, 'search']);
    Route::resource('users/searches', SaveSearches::class);
    Route::post('users/savedproperties/search', [UserSavedPropertyController::class, 'search']);
    Route::resource('users/savedproperties', UserSavedPropertyController::class);
    Route::resource('users', UserController::class);
    Route::resource('sos', Sos::class);
    //meeting routes
    #update meeting status
    Route::post('meetings/update/{id}/status', [MeetingController::class, 'update_status']);
    Route::post('meetings/update/{id}/reschedule', [MeetingController::class, 'reschedule']);
    Route::get('meetings/landlord/{id}', [MeetingController::class, 'landlord_meetings']);
    Route::resource('meetings', MeetingController::class);

    //Trainning
    Route::get("trainings/videos/{id}", [TrainingController::class, 'videos']);
    Route::get("trainings/pdfs/{id}", [TrainingController::class, 'pdfs']);

    //settings
    Route::get("settings/{id}", [SettingController::class, 'get']);
    Route::post("settings/{id}", [SettingController::class, 'set']);
    Route::post("settings/account_status/{id}", [SettingController::class, 'change_account_status']);

    //properties
    Route::post("properties/pin/{id}", [PropertyController::class, 'addPin']);
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
