<?php

namespace App\Http\Controllers\api\user;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Models\User;
use Illuminate\Support\Facades\Validator;

class SettingController extends Controller
{

    //update_template
    public function update_template(Request $request)
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
    public function get($id)
    {
        $user = User::find($id);

        if ($user) {
            $settings = DB::table("user_settings")->orderBy('key',"asc")->where("user_id", $id)->get()->map(function ($s) {
                return [$s->key => $s->value];
            });
            return response([
                'status'    => true,
                'message'   => 'Settings fetched successfully',
                'data'      => $settings
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'User not found'
        ], 404);
    }

    //get settings alternate
    public function get_for_mobile($id)
    {
        $user = User::find($id);

        if ($user) {
            $settings = DB::table("user_settings")->orderBy("key","asc")->where("user_id", $id)->get()->map(function ($s) {
                return ["name" => $s->key, "value" => $s->value];
            });
            return response([
                'status'    => true,
                'message'   => 'Settings fetched successfully',
                'data'      => $settings
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'User not found'
        ], 404);
    }

    //set setting
    public function set($id, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'key'   => 'required|string',
            'value' => 'required'
        ]);

        if ($validator->fails()) {
            return response([
                'status'    => false,
                'message'   => 'Some errors occured',
                'error'     => $validator->errors()
            ], 403);
        }

        if (User::find($id)) {
            $data = ["user_id" => $id, "key" => $request->key, "value" => $request->value, "updated_at" => date("Y-m-d H:i:s")];
            if (DB::table("user_settings")->where("key", $request->key)->where("user_id", $id)->count() > 0) {
                DB::table("user_settings")->where("key", $request->key)->where("user_id", $id)->update($data);
                return response([
                    'status'    => true,
                    'message'   => 'Settings updated successfully.'
                ], 200);
            } else {
                $data['created_at'] = date("Y-m-d H:i:s");
                DB::table("user_settings")->insert($data);
                return response([
                    'status'    => true,
                    'message'   => 'Settings saved successfully.'
                ], 200);
            }
        }

        return response([
            'status'    => false,
            'message'   => 'User not found!'
        ], 404);
    }

    //change account status
    public function change_account_status($id, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'status'    => 'required|string'
        ]);

        if ($validator->fails()) {
            return response([
                'status'    => false,
                'message'   => 'Some errors occured',
                'error'     => $validator->errors()
            ], 403);
        }

        $user = User::find($id);
        if ($user) {
            $user->deactivate_reason = $request->reason ?? '';
            $user->account_status = $request->status;
            if ($user->save()) {
                return response([
                    'status'    => true,
                    'message'   => 'Account status changed successfully.'
                ], 200);
            }

            return response([
                'status'    => false,
                'message'   => 'Something went wrong.',
            ], 500);
        }

        return response([
            'status'    => false,
            'message'   => 'User not found!',
        ], 404);
    }
}
