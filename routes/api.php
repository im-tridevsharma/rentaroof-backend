<?php

use App\Http\Controllers\api\admin\BlogManagement;
use App\Http\Controllers\api\admin\PageManagement;
use App\Http\Controllers\api\admin\SettingController as AdminSettingController;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\api\AuthController;
use App\Http\Controllers\api\user\AgreementController;
use App\Http\Controllers\api\user\chat\ConversationController;
use App\Http\Controllers\api\user\ComplainController;
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
use App\Http\Controllers\api\user\RazorpayController;
use App\Http\Controllers\api\user\ReferralPointController;
use App\Http\Controllers\api\user\SaveSearches;
use App\Http\Controllers\api\user\SettingController;
use App\Http\Controllers\api\user\Sos;
use App\Http\Controllers\api\user\TenantNotificationController;
use App\Http\Controllers\api\user\TenantRatingController;
use App\Http\Controllers\api\user\TrainingController;
use App\Http\Controllers\api\user\UserController;
use App\Http\Controllers\api\user\UserSavedPropertyController;
use App\Http\Controllers\api\user\WalletController;
use App\Models\Amenity;
use App\Models\City;
use App\Models\Country;
use App\Models\Location;
use App\Models\Preference;
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
    Route::post('otp', [AuthController::class, 'sendOtp']);
    Route::post('email-otp', [AuthController::class, 'sendOtpEmail']);
    Route::post('email-verification', [AuthController::class, 'emailVerify']);
    Route::post('mobile-verification', [AuthController::class, 'mobileVerify']);
    Route::post('create-new-password', [AuthController::class, 'createNewPassword']);
    Route::post('refresh', [AuthController::class, 'refresh']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('profile', [AuthController::class, 'profile']);
});

Route::get('profile/code/{code}', [AuthController::class, 'profileByCode']);
Route::post('user/signup', [AuthController::class, 'signup']);

Route::get('page', [PageManagement::class, 'getPage']);
Route::get('blog', [BlogManagement::class, 'getBlog']);
Route::get('pages', [PageManagement::class, 'getPages']);
Route::get('blogs', [BlogManagement::class, 'getBlogs']);
Route::get('website/initials/{key}', [AdminSettingController::class, 'get']);


Route::get("properties/top", [PropertyController::class, 'top_properties']);
Route::get("properties/search", [PropertyController::class, 'search']);
Route::get("properties/search_by_coords", [PropertyController::class, 'search_by_coords']);
Route::get("properties/similar/{code}/{limit}", [PropertyController::class, 'get_similar_properties']);
Route::get("properties/code/{id}", [PropertyController::class, 'code']);
Route::post('properties/requirement', [PropertyController::class, 'save_requirement']);
Route::get("properties/reviews/all/{id}", [RatingandReviewController::class, 'all']);
Route::resource("properties/reviews", RatingandReviewController::class);

Route::get('ibo/properties_by_type', [PropertyController::class, 'ibo_properties_by_type']);
Route::get('landlord/properties_by_type', [PropertyController::class, 'landlord_properties_by_type']);
Route::get("properties/ibo/{id}", [PropertyController::class, 'property_by_user']);
Route::get("properties/landlord/{id}", [PropertyController::class, 'property_by_user']);
Route::get('properties/featured', [PropertyController::class, 'getFeaturedProperties']);

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


Route::get("locations", function () {
    return response([
        'status'    => true,
        'message'   => 'Locations fecthed successfully.',
        'data'      => Location::all()
    ]);
});


Route::get("amenities", function () {
    return response([
        'status'    => true,
        'message'   => 'Amenities fecthed successfully.',
        'data'      => Amenity::all()
    ]);
});

Route::get("preferences", function () {
    return response([
        'status'    => true,
        'message'   => 'Preferences fecthed successfully.',
        'data'      => Preference::all()
    ]);
});


//store and login
Route::post('/store_and_login', [PropertyController::class, 'storeAndLogin']);

Route::group(['middleware' => 'jwt.verify'], function () {

    Route::get('send-otp', [UserController::class, 'sendOtp']);
    Route::post('verify-otp', [UserController::class, 'verifyOtp']);

    Route::get('/get-my-landlords', [UserController::class, 'getLandlords']);
    Route::post('register-new-landlord', [UserController::class, 'newLandlord']);

    Route::get('is-sos', [AuthController::class, 'isSOS']);
    Route::get("properties/for_verification", [PropertyController::class, 'for_verification'])->middleware('jwt.verify');
    Route::post("properties/change_verification_status/{id}", [PropertyController::class, 'change_verification_status'])->middleware('jwt.verify');
    Route::post("properties/accept_or_reject_verification/{id}", [PropertyController::class, 'accept_or_reject_verification'])->middleware('jwt.verify');

    Route::get('earnings/ibo', [UserController::class, 'get_ibo_earnings']);
    Route::get('earnings/ibo/cards', [UserController::class, 'income_cards']);
    Route::get('earnings/ibo/deals', [UserController::class, 'ibo_deals_earning']);
    Route::get('earnings/ibo/for_year', [UserController::class, 'earning_for_year']);

    Route::get('earnings/landlord', [UserController::class, 'get_landlord_earnings']);
    Route::get('earnings/landlord/cards', [UserController::class, 'landlord_income_cards']);
    Route::get('earnings/landlord/deals', [UserController::class, 'landlord_deals_earning']);
    Route::get('earnings/landlord/for_year', [UserController::class, 'landlord_earning_for_year']);

    Route::get('ratings/ibo/all/{id}', [IboRatingController::class, 'all']);
    Route::resource('ratings/ibo', IboRatingController::class);
    Route::get('ibo/notifications/unseen', [IboNotificationController::class, 'unseenNotification']);
    Route::get("ibo/notifications/seen/{id}", [IboNotificationController::class, 'seen']);

    Route::get('ratings/landlord/all/{id}', [LandlordRatingController::class, 'all']);
    Route::resource('ratings/landlord', LandlordRatingController::class);
    Route::get('landlord/notifications/unseen', [LandlordNotificationController::class, 'unseenNotification']);
    Route::get("landlord/notifications/seen/{id}", [LandlordNotificationController::class, 'seen']);

    Route::get('tenant/notifications/unseen', [TenantNotificationController::class, 'unseenNotification']);
    Route::get("tenant/notifications/seen/{id}", [TenantNotificationController::class, 'seen']);
    Route::get('ratings/tenant/all/{id}', [TenantRatingController::class, 'all']);
    Route::resource('ratings/tenant', TenantRatingController::class);
    Route::get('users/referrals', [ReferralPointController::class, 'getReferrals']);
    Route::get('tenant/upcoming_payments', [AgreementController::class, 'upcoming_payments']);
    Route::get('police-verification/{id}', [AgreementController::class, 'police_verification']);
    Route::resource('agreements', AgreementController::class);


    Route::resource('ibo/notifications', IboNotificationController::class);
    Route::resource('landlord/notifications', LandlordNotificationController::class);
    Route::resource('tenant/notifications', TenantNotificationController::class);


    //payment
    Route::post('payment/order', [RazorpayController::class, 'createOrder']);
    Route::post('payment/success', [RazorpayController::class, 'successPayment']);
    Route::get('payment/transactions', [RazorpayController::class, 'getAllTransactions']);
    Route::get('payment/recent', [RazorpayController::class, 'getRecentTransactions']);
    Route::get('properties/rent/transactions/{code}', [RazorpayController::class, 'getPropertyRentTxn']);
    Route::get('properties/awaiting', [PropertyController::class, 'getAwaitingClosedDeal']);
    Route::post("properties/appointment/{id}", [PropertyController::class, 'appointment']);

    //wallet
    Route::get('users/wallet', [WalletController::class, 'getWallet']);
    Route::get('users/wallet/transactions', [WalletController::class, 'getAllTransactions']);

    //complain
    Route::resource('users/complains', ComplainController::class);

    //deal
    Route::get("properties/deals/close/{id}", [PropertyController::class, 'closeDeal']);
    Route::post("properties/deals/status/{id}", [PropertyController::class, 'updateDealStatus']);
    Route::get("properties/deals/{id}", [PropertyController::class, 'getDeal']);

    //chat message
    Route::get('chat/users_for_conversation', [ConversationController::class, 'users_for_conversation']);
    Route::get('chat/conversations', [ConversationController::class, 'index']);
    Route::post('chat/conversations', [ConversationController::class, 'store']);
    Route::delete('chat/conversations/{id}', [ConversationController::class, 'destroy']);
    Route::get('chat/messages/{conversationId}', [ConversationController::class, 'getMessages']);
    Route::get('chat/messages/mobile/{conversationId}', [ConversationController::class, 'getMessagesForMobile']);
    Route::post('chat/messages', [ConversationController::class, 'sendMessage']);
    Route::post('chat/conversations/status', [ConversationController::class, 'change_status']);

    Route::get('users/points_and_payment', [RazorpayController::class, 'points_and_amounts']);
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
    Route::get('meetings/for_mobile', [MeetingController::class, 'meeting_count_for_mobile']);
    Route::get('meetings/for_mobile/details', [MeetingController::class, 'meeting_count_for_mobile_all']);
    Route::get('meetings/for_mobile/landlord/{id}', [MeetingController::class, 'landlord_meetings_mobile']);
    Route::get('meetings/for_mobile/landlord/{id}/details', [MeetingController::class, 'landlord_meetings_mobile_all']);
    Route::post('meetings/vvc/status', [MeetingController::class, 'update_vvc']);
    Route::resource('meetings', MeetingController::class);

    //Trainning
    Route::get("trainings/videos/{id}", [TrainingController::class, 'videos']);
    Route::get("trainings/pdfs/{id}", [TrainingController::class, 'pdfs']);
    Route::get("trainings/faqs", [TrainingController::class, 'getFaqs']);
    Route::get("trainings/mcqs", [TrainingController::class, 'getMcqsIBO']);
    Route::post("trainings/submit_answer", [TrainingController::class, 'saveAnswer']);

    //settings
    Route::post('settings/template', [SettingController::class, 'update_template']);
    Route::get("settings/{id}", [SettingController::class, 'get']);
    Route::get("settings/for_mobile/{id}", [SettingController::class, 'get_for_mobile']);
    Route::post("settings/{id}", [SettingController::class, 'set']);
    Route::post("settings/account_status/{id}", [SettingController::class, 'change_account_status']);
    //properties
    Route::get('deals', [UserController::class, 'getDeals']);
    //payout request
    Route::get('payout/request', [UserController::class, 'requestWidthraw']);
    Route::post('deals/property', [PropertyController::class, 'dealableProperty']);
    Route::get('properties/visited', [PropertyController::class, 'visitedProperties']);
    Route::get("properties/closed/{code}", [PropertyController::class, 'closeProperty']);
    Route::get("properties/open/{code}", [PropertyController::class, 'openProperty']);
    Route::post("properties/pin/{id}", [PropertyController::class, 'addPin']);
    Route::post("properties/amenities", [PropertyController::class, 'amenity']);
    Route::post("properties/essentials", [PropertyController::class, 'essential']);
    Route::put("properties/essentials/{id}", [PropertyController::class, 'essentialUpdate']);
    Route::get("properties/gallery/{id}", [PropertyGalleryController::class, 'getPropertyGallery']);
    Route::post("properties/total", [PropertyController::class, 'total']);
    Route::resource('properties', PropertyController::class);
    Route::resource('properties/galleries', PropertyGalleryController::class);
    Route::resource('properties/addresses', PropertyAddressController::class);
});

//find an agent
Route::get('/find-agent', [Enquiry::class, 'findAnAgent']);
//enquiries
Route::resource('enquiries', Enquiry::class);


//admin routes
Route::prefix('admin')
    ->middleware('jwt.verify')
    ->group(__DIR__ . '/admin/index.php');
