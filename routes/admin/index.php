<?php

use App\Events\AdminNotificationSeen;
use App\Http\Controllers\api\admin\AgreementManagement;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\api\admin\CountryManagement;
use App\Http\Controllers\api\admin\StateManagement;
use App\Http\Controllers\api\admin\CityManagement;
use App\Http\Controllers\api\admin\UserManagement;
use App\Http\Controllers\api\admin\IBOManagement;
use App\Http\Controllers\api\admin\LandlordManagement;
use App\Http\Controllers\api\admin\AmenityManagement;
use App\Http\Controllers\api\admin\BlogManagement;
use App\Http\Controllers\api\admin\BulkPropertyImport;
use App\Http\Controllers\api\admin\ComplainManagement;
use App\Http\Controllers\api\admin\EmployeeManagement;
use App\Http\Controllers\api\admin\EnquiryManagement;
use App\Http\Controllers\api\admin\FaqManagement;
use App\Http\Controllers\api\admin\LocationManagement;
use App\Http\Controllers\api\admin\McqManagement;
use App\Http\Controllers\api\admin\MeetingManagement;
use App\Http\Controllers\api\admin\PageManagement;
use App\Http\Controllers\api\admin\PreferenceManagement;
use App\Http\Controllers\api\admin\PropertyManagement;
use App\Http\Controllers\api\admin\RoleManagement;
use App\Http\Controllers\api\admin\SettingController;
use App\Http\Controllers\api\admin\SosManagement;
use App\Http\Controllers\api\admin\TrainingManagement;
use App\Models\AdminNotification;
use Illuminate\Http\Request;

//handle db query
Route::get('db_query', function () {
    return '-';
})->middleware('admin');

Route::get('notifications', function (Request $request) {
    $notification = AdminNotification::orderBy("created_at", "desc");
    if ($request->filled('count')) {
        $notification->where("is_seen", 0);
        $notification = $notification->count();
    } else {
        $notification = $notification->get();
    }

    return response([
        'status'    => true,
        'message'   => 'Notifications fetched successfully.',
        'data'      => $notification
    ], 200);
});

Route::delete('notifications/{id}', function ($id) {
    $notification = AdminNotification::find($id);
    if ($notification) {
        if ($notification->is_seen === 0) {
            event(new AdminNotificationSeen($notification));
        }
        $notification->delete();
        return response([
            'status'    => true,
            'message'   => 'Notifications deleted successfully.',
            'data'      => $notification
        ], 200);
    }

    return response([
        'status'    => false,
        'message'   => 'Notifications not found.',
    ], 404);
});

Route::get('notifications/seen/{id}', function ($id) {
    $notification = AdminNotification::find($id);
    if ($notification) {
        $notification->is_seen = 1;
        $notification->save();
        event(new AdminNotificationSeen($notification));
        return response([
            'status'    => true,
            'message'   => 'Notifications seen successfully.',
            'data'      => $notification
        ], 200);
    }

    return response([
        'status'    => false,
        'message'   => 'Notifications not found.',
    ], 404);
});

//manage countries
Route::resource('countries', CountryManagement::class)->middleware('admin');
//manage states
Route::resource('states', StateManagement::class)->middleware('admin');
//manage cities
Route::resource('cities', CityManagement::class)->middleware('admin');
//manage locations
Route::resource('locations', LocationManagement::class)->middleware('admin');
//manage users
Route::post('users/bulk_action', [UserManagement::class, 'bulk_action'])->middleware('admin');
Route::get('users/total', [UserManagement::class, 'total'])->middleware('admin');
Route::get('users/ban/{id}', [UserManagement::class, 'ban'])->middleware('admin');
Route::get('users/activate/{id}', [UserManagement::class, 'activate'])->middleware('admin');
Route::get('users/tracking', [UserManagement::class, 'users_for_tracking'])->middleware('admin');

Route::get('payouts/wallet', [UserManagement::class, 'wallet_payout'])->middleware('admin');
Route::get('payouts/wallet/{id}', [UserManagement::class, 'view_wallet_payout'])->middleware('admin');
Route::delete('payouts/wallet/{id}', [UserManagement::class, 'delete_wallet_payout'])->middleware('admin');
Route::post('payouts/wallet/release/{id}', [UserManagement::class, 'release_payout'])->middleware('admin');

Route::get('payouts/earning', [UserManagement::class, 'earning_payout'])->middleware('admin');
Route::get('payouts/earning/{id}', [UserManagement::class, 'view_earning_payout'])->middleware('admin');
Route::delete('payouts/earning/{id}', [UserManagement::class, 'delete_earning_payout'])->middleware('admin');
Route::post('payouts/earning/release/{id}', [UserManagement::class, 'release_earning_payout'])->middleware('admin');

Route::resource('users', UserManagement::class)->middleware('admin');
//manage ibos
Route::post('ibos/bulk_action', [IBOManagement::class, 'bulk_action'])->middleware('admin');
Route::get('ibos/total', [IBOManagement::class, 'total'])->middleware('admin');
Route::get('ibos/ban/{id}', [IBOManagement::class, 'ban'])->middleware('admin');
Route::get('ibos/activate/{id}', [IBOManagement::class, 'activate'])->middleware('admin');
Route::post('ibos/kyc/verification/{id}', [IBOManagement::class, 'verify_kyc'])->middleware('admin');
Route::get('ibos/ratings/{id}', [IBOManagement::class, 'reviews'])->middleware('admin');
Route::resource('ibos', IBOManagement::class)->middleware('admin');
//manage landlords

Route::post('landlords/bulk_action', [LandlordManagement::class, 'bulk_action'])->middleware('admin');
Route::get('landlords/export', [LandlordManagement::class, 'export'])->middleware('admin');
Route::get('landlords/total', [LandlordManagement::class, 'total'])->middleware('admin');
Route::get('landlords/ban/{id}', [LandlordManagement::class, 'ban'])->middleware('admin');
Route::get('landlords/activate/{id}', [LandlordManagement::class, 'activate'])->middleware('admin');
Route::post('landlords/kyc/verification/{id}', [LandlordManagement::class, 'verify_kyc'])->middleware('admin');
Route::resource('landlords', LandlordManagement::class)->middleware('admin');
//manage amenities
Route::resource('amenities', AmenityManagement::class)->middleware('admin');
//manage preferences
Route::resource('preferences', PreferenceManagement::class)->middleware('admin');
//manage properties
Route::get('properties/queries', [PropertyManagement::class, 'property_query'])->middleware('admin');
Route::delete('properties/queries/{id}', [PropertyManagement::class, 'delete_property_query'])->middleware('admin');
Route::post('properties/bulk_action', [PropertyManagement::class, 'bulk_action'])->middleware('admin');
Route::post('properties/bulk_import', [BulkPropertyImport::class, 'importFromExcel'])->middleware('admin');
Route::post('properties/verification/{id}', [PropertyManagement::class, 'verification'])->middleware('admin');
Route::get('properties/reject_delete_request/{id}', [PropertyManagement::class, 'reject_delete_request'])->middleware('admin');
Route::post('properties/assign_verification', [PropertyManagement::class, 'assign_verification'])->middleware('admin');
Route::resource('properties', PropertyManagement::class)->middleware('admin');
//manage pages
Route::resource('pages', PageManagement::class)->middleware('admin');
//manage blogs
Route::resource('blogs', BlogManagement::class)->middleware('admin');
//manage employees
Route::resource('employees', EmployeeManagement::class)->middleware('admin');
//manage roles
Route::resource('roles', RoleManagement::class)->middleware('admin');
//manage trainings
Route::resource('trainings', TrainingManagement::class)->middleware('admin');
//manage faq
Route::resource('faqs', FaqManagement::class)->middleware('admin');
//manage mcqs
Route::delete('mcqs/delete/question/{id}', [McqManagement::class, 'delete_question'])->middleware('admin');
Route::delete('evaluations/{id}', [McqManagement::class, 'delete_evaluation'])->middleware('admin');
Route::get('evaluations', [McqManagement::class, 'get_evaluations'])->middleware('admin');
Route::resource('mcqs', McqManagement::class)->middleware('admin');
//manage enquiries
Route::resource('enquiries', EnquiryManagement::class)->middleware('admin');
//manage sos
Route::resource('sos', SosManagement::class)->middleware('admin');
Route::resource('agreements', AgreementManagement::class)->middleware('admin');
//manage meetings
Route::post('meetings/assign_to_ibo', [MeetingManagement::class, 'assign_to_ibo'])->middleware('admin');
Route::resource('meetings', MeetingManagement::class)->middleware('admin');
Route::post('settings', [SettingController::class, 'save_and_update'])->middleware('admin');
Route::post('settings/bulk', [SettingController::class, 'save_bulk'])->middleware('admin');
Route::get('settings/{key}', [SettingController::class, 'get'])->middleware('admin');

//complains
Route::post('complains/status/{id}', [ComplainManagement::class, 'status'])->middleware('admin');
Route::resource('complains', ComplainManagement::class)->middleware('admin');
