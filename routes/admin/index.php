<?php

use App\Http\Controllers\api\admin\AgreementManagement;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\api\admin\CountryManagement;
use App\Http\Controllers\api\admin\StateManagement;
use App\Http\Controllers\api\admin\CityManagement;
use App\Http\Controllers\api\admin\UserManagement;
use App\Http\Controllers\api\admin\IBOManagement;
use App\Http\Controllers\api\admin\LandlordManagement;
use App\Http\Controllers\api\admin\AmenityManagement;
use App\Http\Controllers\api\admin\EmployeeManagement;
use App\Http\Controllers\api\admin\EnquiryManagement;
use App\Http\Controllers\api\admin\MeetingManagement;
use App\Http\Controllers\api\admin\PageManagement;
use App\Http\Controllers\api\admin\PropertyManagement;
use App\Http\Controllers\api\admin\RoleManagement;
use App\Http\Controllers\api\admin\SettingController;
use App\Http\Controllers\api\admin\SosManagement;
use App\Http\Controllers\api\admin\TrainingManagement;

//handle db query
Route::get('db_query', function () {
    return '-';
})->middleware('admin');

//manage countries
Route::resource('countries', CountryManagement::class)->middleware('admin');
//manage states
Route::resource('states', StateManagement::class)->middleware('admin');
//manage cities
Route::resource('cities', CityManagement::class)->middleware('admin');
//manage users
Route::get('users/total', [UserManagement::class, 'total'])->middleware('admin');
Route::resource('users', UserManagement::class)->middleware('admin');
//manage ibos
Route::get('ibos/total', [IBOManagement::class, 'total'])->middleware('admin');
Route::post('ibos/kyc/verification/{id}', [IBOManagement::class, 'verify_kyc'])->middleware('admin');
Route::resource('ibos', IBOManagement::class)->middleware('admin');
//manage landlords
Route::get('landlords/total', [LandlordManagement::class, 'total'])->middleware('admin');
Route::post('landlords/kyc/verification/{id}', [LandlordManagement::class, 'verify_kyc'])->middleware('admin');
Route::resource('landlords', LandlordManagement::class)->middleware('admin');
//manage amenities
Route::resource('amenities', AmenityManagement::class)->middleware('admin');
//manage properties
Route::post('properties/verification/{id}', [PropertyManagement::class, 'verification'])->middleware('admin');
Route::post('properties/assign_verification', [PropertyManagement::class, 'assign_verification'])->middleware('admin');
Route::resource('properties', PropertyManagement::class)->middleware('admin');
//manage pages
Route::resource('pages', PageManagement::class)->middleware('admin');
//manage employees
Route::resource('employees', EmployeeManagement::class)->middleware('admin');
//manage roles
Route::resource('roles', RoleManagement::class)->middleware('admin');
//manage trainings
Route::resource('trainings', TrainingManagement::class)->middleware('admin');
//manage enquiries
Route::resource('enquiries', EnquiryManagement::class)->middleware('admin');
//manage sos
Route::resource('sos', SosManagement::class)->middleware('admin');
Route::resource('agreements', AgreementManagement::class)->middleware('admin');
//manage meetings
Route::resource('meetings', MeetingManagement::class)->middleware('admin');
Route::post('settings', [SettingController::class, 'save_and_update'])->middleware('admin');
Route::post('settings/bulk', [SettingController::class, 'save_bulk'])->middleware('admin');
Route::get('settings/{key}', [SettingController::class, 'get'])->middleware('admin');
