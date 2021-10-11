<?php

namespace App\Http\Controllers\api\user;

use App\Http\Controllers\Controller;
use App\Models\PropertyGallery;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class PropertyGalleryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return response([
            'status'    => false,
            'message'   => 'Action is not allowed.'
        ], 401);
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
            'exterior.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
            'living_room.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
            'bedrooms.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
            'bathrooms.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
            'kitchen.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
            'floor_plan.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
            'master_plan.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
            'location_map.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        if ($validator->fails()) {
            return response([
                'status'    => false,
                'message'   => 'Some errors occured.',
                'error'     => $validator->errors()
            ], 400);
        }

        $property_cover = '';
        $gallery = new PropertyGallery;
        $gallery->property_id = $request->propertyId;

        if ($request->hasFile('exterior')) {
            $exteriors = [];
            $upload_dir = "/uploads/property_gallery/" . $request->propertyId . "/exterior";
            foreach ($request->exterior as $file) {
                //upload and get links
                $name = Storage::disk('digitalocean')->put($upload_dir, $file, 'public');
                array_push($exteriors, Storage::disk('digitalocean')->url($name));
                if ($file->getClientOriginalName() == $request->cover->getClientOriginalName()) {
                    $property_cover = Storage::disk('digitalocean')->url($name);
                }
            }

            $gallery->exterior_view = json_encode($exteriors);
        } else {
            $gallery->exterior_view = json_encode([]);
        }

        if ($request->hasFile('living_room')) {
            $living_room = [];
            $upload_dir = "/uploads/property_gallery/" . $request->propertyId . "/living_room";
            foreach ($request->living_room as $file) {
                //upload and get links
                $name = Storage::disk('digitalocean')->put($upload_dir, $file, 'public');
                array_push($living_room, Storage::disk('digitalocean')->url($name));
                if ($file->getClientOriginalName() == $request->cover->getClientOriginalName()) {
                    $property_cover = Storage::disk('digitalocean')->url($name);
                }
            }

            $gallery->living_room = json_encode($living_room);
        } else {
            $gallery->living_room = json_encode([]);
        }

        if ($request->hasFile('bedrooms')) {
            $bedrooms = [];
            $upload_dir = "/uploads/property_gallery/" . $request->propertyId . "/bedrooms";
            foreach ($request->bedrooms as $file) {
                //upload and get links
                $name = Storage::disk('digitalocean')->put($upload_dir, $file, 'public');
                array_push($bedrooms, Storage::disk('digitalocean')->url($name));
                if ($file->getClientOriginalName() == $request->cover->getClientOriginalName()) {
                    $property_cover = Storage::disk('digitalocean')->url($name);
                }
            }

            $gallery->bedrooms = json_encode($bedrooms);
        } else {
            $gallery->bedrooms = json_encode([]);
        }

        if ($request->hasFile('bathrooms')) {
            $bathrooms = [];
            $upload_dir = "/uploads/property_gallery/" . $request->propertyId . "/bathrooms";
            foreach ($request->bathrooms as $file) {
                //upload and get links
                $name = Storage::disk('digitalocean')->put($upload_dir, $file, 'public');
                array_push($bathrooms, Storage::disk('digitalocean')->url($name));
                if ($file->getClientOriginalName() == $request->cover->getClientOriginalName()) {
                    $property_cover = Storage::disk('digitalocean')->url($name);
                }
            }

            $gallery->bathrooms = json_encode($bathrooms);
        } else {
            $gallery->bathrooms = json_encode([]);
        }

        if ($request->hasFile('kitchen')) {
            $kitchen = [];
            $upload_dir = "/uploads/property_gallery/" . $request->propertyId . "/kitchen";
            foreach ($request->kitchen as $file) {
                //upload and get links
                $name = Storage::disk('digitalocean')->put($upload_dir, $file, 'public');
                array_push($kitchen, Storage::disk('digitalocean')->url($name));
                if ($file->getClientOriginalName() == $request->cover->getClientOriginalName()) {
                    $property_cover = Storage::disk('digitalocean')->url($name);
                }
            }

            $gallery->kitchen = json_encode($kitchen);
        } else {
            $gallery->kitchen = json_encode([]);
        }

        if ($request->hasFile('floor_plan')) {
            $floor_plan = [];
            $upload_dir = "/uploads/property_gallery/" . $request->propertyId . "/floor_plan";
            foreach ($request->floor_plan as $file) {
                //upload and get links
                $name = Storage::disk('digitalocean')->put($upload_dir, $file, 'public');
                array_push($floor_plan, Storage::disk('digitalocean')->url($name));
                if ($file->getClientOriginalName() == $request->cover->getClientOriginalName()) {
                    $property_cover = Storage::disk('digitalocean')->url($name);
                }
            }

            $gallery->floor_plan = json_encode($floor_plan);
        } else {
            $gallery->floor_plan = json_encode([]);
        }

        if ($request->hasFile('master_plan')) {
            $master_plan = [];
            $upload_dir = "/uploads/property_gallery/" . $request->propertyId . "/master_plan";
            foreach ($request->master_plan as $file) {
                //upload and get links
                $name = Storage::disk('digitalocean')->put($upload_dir, $file, 'public');
                array_push($master_plan, Storage::disk('digitalocean')->url($name));
                if ($file->getClientOriginalName() == $request->cover->getClientOriginalName()) {
                    $property_cover = Storage::disk('digitalocean')->url($name);
                }
            }

            $gallery->master_plan = json_encode($master_plan);
        } else {
            $gallery->master_plan = json_encode([]);
        }

        if ($request->hasFile('location_map')) {
            $location_map = [];
            $upload_dir = "/uploads/property_gallery/" . $request->propertyId . "/location_map";
            foreach ($request->location_map as $file) {
                //upload and get links
                $name = Storage::disk('digitalocean')->put($upload_dir, $file, 'public');
                array_push($location_map, Storage::disk('digitalocean')->url($name));
                if ($file->getClientOriginalName() == $request->cover->getClientOriginalName()) {
                    $property_cover = Storage::disk('digitalocean')->url($name);
                }
            }
            $gallery->location_map = json_encode($location_map);
        } else {
            $gallery->location_map = json_encode([]);
        }

        $gallery->others = json_encode([]);

        if ($gallery->save()) {

            $p = Property::find($request->propertyId);
            $p->front_image = $property_cover;
            $p->gallery_id = $gallery->id;
            $p->save();

            return response([
                'status'    => true,
                'message'   => 'Gallery saved successfully.',
                'data'      => $gallery
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Unable to save data'
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\PropertyGallery  $propertyGallery
     * @return \Illuminate\Http\Response
     */
    public function show(PropertyGallery $propertyGallery)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\PropertyGallery  $propertyGallery
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, PropertyGallery $propertyGallery)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\PropertyGallery  $propertyGallery
     * @return \Illuminate\Http\Response
     */
    public function destroy(PropertyGallery $propertyGallery)
    {
        //
    }
}
