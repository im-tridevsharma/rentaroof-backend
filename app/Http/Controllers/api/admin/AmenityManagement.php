<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use App\Models\Amenity;
use Illuminate\Support\Facades\Storage;

class AmenityManagement extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $amenities = Amenity::all();
        return response([
            'status'  => true,
            'message' => 'Amenities fetched successfully.',
            'data'    => $amenities
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title'          => 'required|string|between:5,50',
            'description'    => 'max:200',
            'icon'           => 'required|mimes:jpeg,jpg,png|max:2048'
        ]);

        if ($validator->fails()) {
            return response([
                'status'    =>  false,
                'message'   => 'Some errors occured.',
                'error'     => $validator->errors()
            ], 400);
        }

        $icon_url = '';
        if ($request->hasfile('icon')) {
            $upload_dir = '/uploads/amenities';
            $name = Storage::disk('digitalocean')->put($upload_dir, $request->file('icon'), 'public');
            $icon_url = Storage::disk('digitalocean')->url($name);
        }

        $amenity = new Amenity;
        $amenity->title = $request->title;
        $amenity->description = isset($request->description) ? $request->description : '';
        $amenity->icon = $icon_url;

        if ($amenity->save()) {
            return response([
                'status'    => true,
                'message'   => 'New Amenity added successfully.',
                'data'      => $amenity
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Unable to save amenity data.'
        ], 500);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $amenity = Amenity::find($id);

        if ($amenity) {
            return response([
                'status'    => true,
                'message'   => 'Amenity fetched successfully.',
                'data'      => $amenity
            ]);
        }

        return response([
            'status'    => false,
            'message'   => 'Amenity not found.'
        ], 400);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $updateIcon = false;
        $validator = Validator::make($request->all(), [
            'title'          => 'required|string|between:5,50',
            'description'   => 'max:200',
            'icon'      => 'mimes:jpeg,jpg,png|max:2048'
        ]);

        if ($validator->fails()) {
            return response([
                'status'    =>  false,
                'message'   => 'Some errors occured.',
                'error'     => $validator->errors()
            ]);
        }

        $icon_url = '';
        if ($request->hasfile('icon')) {
            $upload_dir = '/uploads/amenities';
            $name = Storage::disk('digitalocean')->put($upload_dir, $request->file('icon'), 'public');
            $icon_url = Storage::disk('digitalocean')->url($name);
            $updateIcon = true;
        }

        $amenity = Amenity::find($id);

        if ($amenity) {
            $amenity->title = $request->title;
            $amenity->description = isset($request->description) ? $request->description : '';

            if ($updateIcon) {
                //remove old image
                $oldimg = $amenity->icon;
                if (Storage::disk('digitalocean')->exists($upload_dir . '/' . basename($oldimg))) {
                    Storage::disk('digitalocean')->delete($upload_dir . '/' . basename($oldimg));
                }

                $amenity->icon = !empty($icon_url) ? $icon_url : $amenity->icon;
            }

            if ($amenity->save()) {
                return response([
                    'status'    => true,
                    'message'   => 'Amenity updated successfully.',
                    'data'      => $amenity
                ], 200);
            }

            return response([
                'status'    => false,
                'message'   => 'Unable to update amenity.'
            ], 500);
        }

        return response([
            'status'    => false,
            'message'   => 'Amenity not found.'
        ], 404);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $amenity = Amenity::find($id);

        if ($amenity) {
            //remove images
            $upload_dir = '/uploads/amenities';
            $icon = $amenity->icon;
            if ($icon) {
                if (Storage::disk('digitalocean')->exists($upload_dir . '/' . basename($icon))) {
                    Storage::disk('digitalocean')->delete($upload_dir . '/' . basename($icon));
                }
                //make it blank
                $amenity->icon = '';
            }

            if ($amenity->delete()) {
                return response([
                    'status'    => true,
                    'message'   => 'Amenity deleted successfully.',
                    'data'      => $amenity
                ], 200);
            }

            return response([
                'status'    => false,
                'message'   => 'Unable to delete amenity.'
            ], 500);
        }

        return response([
            'status'    => false,
            'message'   => 'Amenity not found.'
        ], 404);
    }
}
