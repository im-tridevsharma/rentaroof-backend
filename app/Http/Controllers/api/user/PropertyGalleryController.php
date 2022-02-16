<?php

namespace App\Http\Controllers\api\user;

use App\Http\Controllers\Controller;
use App\Models\PropertyGallery;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

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
            'propertyId' => 'required',
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
        $is_gallery = PropertyGallery::where("property_id", $request->propertyId)->first();
        $gallery = $is_gallery ? $is_gallery : new PropertyGallery;
        $gallery->property_id = $request->propertyId;


        $p = Property::find($request->propertyId);

        //check is he authorized to edit this property
        $l_user = JWTAuth::user();
        if ($l_user->id !== $p->posted_by && $p->ibo !== $l_user->id) {
            $pv = DB::table('property_verifications')->where("property_id", $p->id)->where("ibo_id", $l_user->id)->first();
            if ($pv) {
                if ($pv->property_id !== $p->id || $pv->status !== 'accepted') {
                    return response([
                        'status'    => false,
                        'message'   => 'Not Authorized to edit this property.'
                    ], 401);
                }
            } else {
                return response([
                    'status'    => false,
                    'message'   => 'Not Authorized to edit this property.'
                ], 401);
            }
        }

        if ($request->hasFile('exterior')) {
            $exteriors = [];
            $upload_dir = "/uploads/property_gallery/" . $request->propertyId . "/exterior";
            foreach ($request->exterior as $file) {
                //upload and get links
                $name = Storage::disk('digitalocean')->put($upload_dir, $file, 'public');
                array_push($exteriors, Storage::disk('digitalocean')->url($name));
                if ($request->cover && $file->getClientOriginalName() == $request->cover->getClientOriginalName()) {
                    $property_cover = Storage::disk('digitalocean')->url($name);
                }
            }

            $uploaded = json_decode($gallery->exterior_view);
            //remove if to be removed
            if ($request->filled('remove_exterior')) {
                $arr = explode(",", $request->remove_exterior);
                $uploaded = array_diff($uploaded, $arr);
                //remove each
                foreach ($arr as $img) {
                    if (Storage::disk('digitalocean')->exists($upload_dir . '/' . basename($img))) {
                        Storage::disk('digitalocean')->delete($upload_dir . '/' . basename($img));
                    }
                }
            }

            if (isset($uploaded) && is_array($uploaded)) {
                $exteriors = array_merge($uploaded, $exteriors);
            }

            $gallery->exterior_view = json_encode($exteriors);
        } else {
            $upload_dir = "/uploads/property_gallery/" . $request->propertyId . "/exterior";
            $uploaded = json_decode($gallery->exterior_view);
            //remove if to be removed
            if ($request->filled('remove_exterior')) {
                $arr = explode(",", $request->remove_exterior);
                $uploaded = array_diff($uploaded, $arr);
                //remove each
                foreach ($arr as $img) {
                    if (Storage::disk('digitalocean')->exists($upload_dir . '/' . basename($img))) {
                        Storage::disk('digitalocean')->delete($upload_dir . '/' . basename($img));
                    }
                }
            }

            $gallery->exterior_view = $uploaded ?  json_encode($uploaded) : json_encode([]);
        }

        if ($request->hasFile('living_room')) {
            $living_room = [];
            $upload_dir = "/uploads/property_gallery/" . $request->propertyId . "/living_room";
            foreach ($request->living_room as $file) {
                //upload and get links
                $name = Storage::disk('digitalocean')->put($upload_dir, $file, 'public');
                array_push($living_room, Storage::disk('digitalocean')->url($name));
                if ($request->cover && $file->getClientOriginalName() == $request->cover->getClientOriginalName()) {
                    $property_cover = Storage::disk('digitalocean')->url($name);
                }
            }

            $uploaded = json_decode($gallery->living_room);
            //remove if to be removed
            if ($request->filled('remove_living_room')) {
                $arr = explode(",", $request->remove_living_room);
                $uploaded = array_diff($uploaded, $arr);
                //remove each
                foreach ($arr as $img) {
                    if (Storage::disk('digitalocean')->exists($upload_dir . '/' . basename($img))) {
                        Storage::disk('digitalocean')->delete($upload_dir . '/' . basename($img));
                    }
                }
            }

            if (isset($uploaded) && is_array($uploaded)) {
                $living_room = array_merge($uploaded, $living_room);
            }

            $gallery->living_room = json_encode($living_room);
        } else {
            $upload_dir = "/uploads/property_gallery/" . $request->propertyId . "/living_room";
            $uploaded = json_decode($gallery->living_room);
            //remove if to be removed
            if ($request->filled('remove_living_room')) {
                $arr = explode(",", $request->remove_living_room);
                $uploaded = array_diff($uploaded, $arr);
                //remove each
                foreach ($arr as $img) {
                    if (Storage::disk('digitalocean')->exists($upload_dir . '/' . basename($img))) {
                        Storage::disk('digitalocean')->delete($upload_dir . '/' . basename($img));
                    }
                }
            }

            $gallery->living_room = $uploaded ?  json_encode($uploaded) : json_encode([]);
        }

        if ($request->hasFile('bedrooms')) {
            $bedrooms = [];
            $upload_dir = "/uploads/property_gallery/" . $request->propertyId . "/bedrooms";
            foreach ($request->bedrooms as $file) {
                //upload and get links
                $name = Storage::disk('digitalocean')->put($upload_dir, $file, 'public');
                array_push($bedrooms, Storage::disk('digitalocean')->url($name));
                if ($request->cover && $file->getClientOriginalName() == $request->cover->getClientOriginalName()) {
                    $property_cover = Storage::disk('digitalocean')->url($name);
                }
            }

            $uploaded = json_decode($gallery->bedrooms);
            //remove if to be removed
            if ($request->filled('remove_bedrooms')) {
                $arr = explode(",", $request->remove_bedrooms);
                $uploaded = array_diff($uploaded, $arr);
                //remove each
                foreach ($arr as $img) {
                    if (Storage::disk('digitalocean')->exists($upload_dir . '/' . basename($img))) {
                        Storage::disk('digitalocean')->delete($upload_dir . '/' . basename($img));
                    }
                }
            }

            if (isset($uploaded) && is_array($uploaded)) {
                $bedrooms = array_merge($uploaded, $bedrooms);
            }

            $gallery->bedrooms = json_encode($bedrooms);
        } else {
            $upload_dir = "/uploads/property_gallery/" . $request->propertyId . "/bedrooms";
            $uploaded = json_decode($gallery->bedrooms);
            //remove if to be removed
            if ($request->filled('remove_bedrooms')) {
                $arr = explode(",", $request->remove_bedrooms);
                $uploaded = array_diff($uploaded, $arr);
                //remove each
                foreach ($arr as $img) {
                    if (Storage::disk('digitalocean')->exists($upload_dir . '/' . basename($img))) {
                        Storage::disk('digitalocean')->delete($upload_dir . '/' . basename($img));
                    }
                }
            }

            $gallery->bedrooms = $uploaded ?  json_encode($uploaded) : json_encode([]);
        }

        if ($request->hasFile('bathrooms')) {
            $bathrooms = [];
            $upload_dir = "/uploads/property_gallery/" . $request->propertyId . "/bathrooms";
            foreach ($request->bathrooms as $file) {
                //upload and get links
                $name = Storage::disk('digitalocean')->put($upload_dir, $file, 'public');
                array_push($bathrooms, Storage::disk('digitalocean')->url($name));
                if ($request->cover && $file->getClientOriginalName() == $request->cover->getClientOriginalName()) {
                    $property_cover = Storage::disk('digitalocean')->url($name);
                }
            }

            $uploaded = json_decode($gallery->bathrooms);
            //remove if to be removed
            if ($request->filled('remove_bathrooms')) {
                $arr = explode(",", $request->remove_bathrooms);
                $uploaded = array_diff($uploaded, $arr);
                //remove each
                foreach ($arr as $img) {
                    if (Storage::disk('digitalocean')->exists($upload_dir . '/' . basename($img))) {
                        Storage::disk('digitalocean')->delete($upload_dir . '/' . basename($img));
                    }
                }
            }

            if (isset($uploaded) && is_array($uploaded)) {
                $bathrooms = array_merge($uploaded, $bathrooms);
            }

            $gallery->bathrooms = json_encode($bathrooms);
        } else {
            $upload_dir = "/uploads/property_gallery/" . $request->propertyId . "/bathrooms";
            $uploaded = json_decode($gallery->bathrooms);
            //remove if to be removed
            if ($request->filled('remove_bathrooms')) {
                $arr = explode(",", $request->remove_bathrooms);
                $uploaded = array_diff($uploaded, $arr);
                //remove each
                foreach ($arr as $img) {
                    if (Storage::disk('digitalocean')->exists($upload_dir . '/' . basename($img))) {
                        Storage::disk('digitalocean')->delete($upload_dir . '/' . basename($img));
                    }
                }
            }

            $gallery->bathrooms = $uploaded ?  json_encode($uploaded) : json_encode([]);
        }

        if ($request->hasFile('kitchen')) {
            $kitchen = [];
            $upload_dir = "/uploads/property_gallery/" . $request->propertyId . "/kitchen";
            foreach ($request->kitchen as $file) {
                //upload and get links
                $name = Storage::disk('digitalocean')->put($upload_dir, $file, 'public');
                array_push($kitchen, Storage::disk('digitalocean')->url($name));
                if ($request->cover && $file->getClientOriginalName() == $request->cover->getClientOriginalName()) {
                    $property_cover = Storage::disk('digitalocean')->url($name);
                }
            }

            $uploaded = json_decode($gallery->kitchen);
            //remove if to be removed
            if ($request->filled('remove_kitchen')) {
                $arr = explode(",", $request->remove_kitchen);
                $uploaded = array_diff($uploaded, $arr);
                //remove each
                foreach ($arr as $img) {
                    if (Storage::disk('digitalocean')->exists($upload_dir . '/' . basename($img))) {
                        Storage::disk('digitalocean')->delete($upload_dir . '/' . basename($img));
                    }
                }
            }

            if (isset($uploaded) && is_array($uploaded)) {
                $kitchen = array_merge($uploaded, $kitchen);
            }

            $gallery->kitchen = json_encode($kitchen);
        } else {
            $upload_dir = "/uploads/property_gallery/" . $request->propertyId . "/kitchen";
            $uploaded = json_decode($gallery->kitchen);
            //remove if to be removed
            if ($request->filled('remove_kitchen')) {
                $arr = explode(",", $request->remove_kitchen);
                $uploaded = array_diff($uploaded, $arr);
                //remove each
                foreach ($arr as $img) {
                    if (Storage::disk('digitalocean')->exists($upload_dir . '/' . basename($img))) {
                        Storage::disk('digitalocean')->delete($upload_dir . '/' . basename($img));
                    }
                }
            }

            $gallery->kitchen = $uploaded ?  json_encode($uploaded) : json_encode([]);
        }

        if ($request->hasFile('floor_plan')) {
            $floor_plan = [];
            $upload_dir = "/uploads/property_gallery/" . $request->propertyId . "/floor_plan";
            foreach ($request->floor_plan as $file) {
                //upload and get links
                $name = Storage::disk('digitalocean')->put($upload_dir, $file, 'public');
                array_push($floor_plan, Storage::disk('digitalocean')->url($name));
                if ($request->cover && $file->getClientOriginalName() == $request->cover->getClientOriginalName()) {
                    $property_cover = Storage::disk('digitalocean')->url($name);
                }
            }

            $uploaded = json_decode($gallery->floor_plan);
            //remove if to be removed
            if ($request->filled('remove_floor_plan')) {
                $arr = explode(",", $request->remove_floor_plan);
                $uploaded = array_diff($uploaded, $arr);
                //remove each
                foreach ($arr as $img) {
                    if (Storage::disk('digitalocean')->exists($upload_dir . '/' . basename($img))) {
                        Storage::disk('digitalocean')->delete($upload_dir . '/' . basename($img));
                    }
                }
            }

            if (isset($uploaded) && is_array($uploaded)) {
                $floor_plan = array_merge($uploaded, $floor_plan);
            }

            $gallery->floor_plan = json_encode($floor_plan);
        } else {
            $upload_dir = "/uploads/property_gallery/" . $request->propertyId . "/floor_plan";
            $uploaded = json_decode($gallery->floor_plan);
            //remove if to be removed
            if ($request->filled('remove_floor_plan')) {
                $arr = explode(",", $request->remove_floor_plan);
                $uploaded = array_diff($uploaded, $arr);
                //remove each
                foreach ($arr as $img) {
                    if (Storage::disk('digitalocean')->exists($upload_dir . '/' . basename($img))) {
                        Storage::disk('digitalocean')->delete($upload_dir . '/' . basename($img));
                    }
                }
            }

            $gallery->floor_plan = $uploaded ?  json_encode($uploaded) : json_encode([]);
        }

        if ($request->hasFile('master_plan')) {
            $master_plan = [];
            $upload_dir = "/uploads/property_gallery/" . $request->propertyId . "/master_plan";
            foreach ($request->master_plan as $file) {
                //upload and get links
                $name = Storage::disk('digitalocean')->put($upload_dir, $file, 'public');
                array_push($master_plan, Storage::disk('digitalocean')->url($name));
                if ($request->cover && $file->getClientOriginalName() == $request->cover->getClientOriginalName()) {
                    $property_cover = Storage::disk('digitalocean')->url($name);
                }
            }

            $uploaded = json_decode($gallery->master_plan);
            //remove if to be removed
            if ($request->filled('remove_master_plan')) {
                $arr = explode(",", $request->remove_master_plan);
                $uploaded = array_diff($uploaded, $arr);
                //remove each
                foreach ($arr as $img) {
                    if (Storage::disk('digitalocean')->exists($upload_dir . '/' . basename($img))) {
                        Storage::disk('digitalocean')->delete($upload_dir . '/' . basename($img));
                    }
                }
            }

            if (isset($uploaded) && is_array($uploaded)) {
                $master_plan = array_merge($uploaded, $master_plan);
            }

            $gallery->master_plan = json_encode($master_plan);
        } else {
            $upload_dir = "/uploads/property_gallery/" . $request->propertyId . "/master_plan";
            $uploaded = json_decode($gallery->master_plan);
            //remove if to be removed
            if ($request->filled('remove_master_plan')) {
                $arr = explode(",", $request->remove_master_plan);
                $uploaded = array_diff($uploaded, $arr);
                //remove each
                foreach ($arr as $img) {
                    if (Storage::disk('digitalocean')->exists($upload_dir . '/' . basename($img))) {
                        Storage::disk('digitalocean')->delete($upload_dir . '/' . basename($img));
                    }
                }
            }

            $gallery->master_plan = $uploaded ?  json_encode($uploaded) : json_encode([]);
        }

        if ($request->hasFile('location_map')) {
            $location_map = [];
            $upload_dir = "/uploads/property_gallery/" . $request->propertyId . "/location_map";
            foreach ($request->location_map as $file) {
                //upload and get links
                $name = Storage::disk('digitalocean')->put($upload_dir, $file, 'public');
                array_push($location_map, Storage::disk('digitalocean')->url($name));
                if ($request->cover && $file->getClientOriginalName() == $request->cover->getClientOriginalName()) {
                    $property_cover = Storage::disk('digitalocean')->url($name);
                }
            }

            $uploaded = json_decode($gallery->location_map);
            //remove if to be removed
            if ($request->filled('remove_location_map')) {
                $arr = explode(",", $request->remove_location_map);
                $uploaded = array_diff($uploaded, $arr);
                //remove each
                foreach ($arr as $img) {
                    if (Storage::disk('digitalocean')->exists($upload_dir . '/' . basename($img))) {
                        Storage::disk('digitalocean')->delete($upload_dir . '/' . basename($img));
                    }
                }
            }

            if (isset($uploaded) && is_array($uploaded)) {
                $location_map = array_merge($uploaded, $location_map);
            }

            $gallery->location_map = json_encode($location_map);
        } else {
            $upload_dir = "/uploads/property_gallery/" . $request->propertyId . "/location_map";
            $uploaded = json_decode($gallery->location_map);
            //remove if to be removed
            if ($request->filled('remove_location_map')) {
                $arr = explode(",", $request->remove_location_map);
                $uploaded = array_diff($uploaded, $arr);
                //remove each
                foreach ($arr as $img) {
                    if (Storage::disk('digitalocean')->exists($upload_dir . '/' . basename($img))) {
                        Storage::disk('digitalocean')->delete($upload_dir . '/' . basename($img));
                    }
                }
            }

            $gallery->location_map = $uploaded ?  json_encode($uploaded) : json_encode([]);
        }

        $gallery->others = json_encode([]);

        if ($gallery->save()) {

            $p->front_image = $property_cover ? $property_cover : $p->front_image;
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
    public function getPropertyGallery($id)
    {
        $gallery = PropertyGallery::where("property_id", $id)->first();
        if ($gallery) {
            return response([
                'status'    => true,
                'message'   => 'Property Gallery fetched Successfully.',
                'data'      => $gallery
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Gallery not found!'
        ], 404);
    }
}
