<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
            $setting = [];
            $keys = explode(",", $key);
            if (is_array($keys) && count($keys) > 0) {
                foreach ($keys as $k) {
                    $s = DB::table('settings')->where("setting_key", $k)->first();
                    if ($s) {
                        $setting[$s->setting_key] = $s->setting_value;
                    }
                }
            } else {
                $setting = DB::table('settings')->where("setting_key", $key)->first();
            }

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

    //save bulk settings
    public function save_bulk(Request $request)
    {
        foreach ($request->all() as $key => $value) {
            if ($key === 'logo' && gettype($value) === 'object') {
                if ($request->hasfile('logo')) {
                    $upload_dir = '/uploads/logo';
                    $name = Storage::disk('digitalocean')->put($upload_dir, $request->file('logo'), 'public');
                    $icon_url = Storage::disk('digitalocean')->url($name);
                    $this->update('logo', $icon_url);
                }
            } else {
                $this->update($key, $value);
            }
        }

        return response([
            'status'    => true,
            'message'   => 'Settings saved successfully.',
        ], 200);
    }

    //update or save module
    protected function update($key, $value)
    {
        $value = $value !== null ? $value : '';
        $setting = DB::table('settings')->where("setting_key", $key)->first();
        if ($setting) {
            if ($key === 'logo' && !empty($value)) {
                $upload_dir = '/uploads/logo';
                $icon = $setting->setting_value;
                if ($icon) {
                    if (Storage::disk('digitalocean')->exists($upload_dir . '/' . basename($icon))) {
                        Storage::disk('digitalocean')->delete($upload_dir . '/' . basename($icon));
                    }
                }
            }
            //update
            DB::table('settings')->where("setting_key", $key)
                ->update(["setting_value" => $value]);
        } else {
            //save
            DB::table('settings')
                ->insert(["setting_key" => $key, "setting_value" => $value]);
        }
    }
}
