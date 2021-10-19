<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SettingController extends Controller
{
    //save and update settings
    public function save_and_update(Request $request)
    {
        $validator  = Validator::make($request->all(), [
            'setting_key'   => 'required|string',
            'setting_value' => 'required'
        ]);

        if ($validator->fails()) {
            return response([
                'status'    => false,
                'message'   => 'Some errors occured.',
                'error'     => $validator->errors()
            ], 400);
        }

        $setting = DB::table('settings')->where("setting_key", $request->setting_key)->first();
        if ($setting) {
            //update
            DB::table('settings')->where("setting_key", $request->setting_key)
                ->update(["setting_value" => $request->setting_value]);
        } else {
            //save
            DB::table('settings')
                ->insert(["setting_key" => $request->setting_key, "setting_value" => $request->setting_value]);
        }

        return response([
            'status'    => true,
            'message'   => 'Settings updated successfully.',
        ], 200);
    }

    //get settings
    public function get($key)
    {
        if ($key) {
            $setting = DB::table('settings')->where("setting_key", $key)->first();
            if ($setting) {
                return response([
                    'status'    => true,
                    'message'   => 'Setting fetched successfully.',
                    'data'      => $setting
                ], 200);
            }

            return response([
                'status'    => false,
                'message'   => 'Setting not found!',
            ], 404);
        }

        return response([
            'status'    => false,
            'message'   => 'Something went wrong!',
        ], 500);
    }
}
