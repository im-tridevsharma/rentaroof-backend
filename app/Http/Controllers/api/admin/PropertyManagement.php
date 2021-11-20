<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Amenity;
use Illuminate\Http\Request;

use App\Models\Property;
use App\Models\PropertyEssential;
use App\Models\PropertyGallery;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PropertyManagement extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $properties = Property::all();

        return response([
            'status'    => true,
            'message'   => 'properties fetched successfully.',
            'data'      => $properties
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $property = Property::find($id);
        $pv = DB::table('property_verifications')->where("property_id", $id)->first();
        if ($pv && $pv->ibo_id) {
            $pv->ibo = User::find($pv->ibo_id);
        }
        $property->verification = $pv;
        if ($property) {
            $amenities_data = [];
            //find and merge amenities
            $amenities = json_decode($property->amenities);
            if (is_array($amenities)) {
                foreach ($amenities as $a) {
                    array_push($amenities_data, Amenity::find($a));
                }
            }
            $property->amenities_data = $amenities_data;
            $property->owner_data = User::find($property->posted_by);

            return response([
                'status'    => true,
                'message'   => 'Property fetched successfully.',
                'data'      => $property->load(['address', 'essential', 'gallery'])
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Something went wrong!'
        ], 500);
    }

    //method for verification
    public function verification(Request $request, $id)
    {
        $property = Property::find($id);
        if ($property) {
            $property->is_approved = $request->status == 'verify' ? 1 : 0;
            if ($request->status == 'verify') {
                $property->verified_at = date("Y-m-d H:i:s");
            }
            if ($property->save()) {
                return response([
                    'status'    => true,
                    'message'   => 'Status changed successfully.'
                ], 200);
            }

            return response([
                'status'    => false,
                'message'   => 'Something went wrong!'
            ], 500);
        }

        return response([
            'status'    => false,
            'message'   => 'Property not found!'
        ], 404);
    }

    //reject_delete_request
    public function reject_delete_request($id)
    {
        $property = Property::find($id);
        if ($property) {
            $property->is_deleted = 0;
            $property->delete_reason = '';

            $property->save();
            return response([
                'status'    => true,
                'message'   => 'Property delete request rejected successfully.',
                'data'      => $property
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Property not found!'
        ], 404);
    }

    //assign_verification
    public function assign_verification(Request $request)
    {
        $property = Property::find($request->property_id);
        if ($property) {

            $data = [
                'property_id'   => $property->id,
                'ibo_id'        => $request->ibo_id,
                'message'       => $request->message,
                'created_at'    => date("Y-m-d H:i:s"),
                'updated_at'    => date("Y-m-d H:i:s")
            ];

            $is = DB::table('property_verifications')->where("property_id", $property->id)->first();

            if ($is) {
                return response([
                    'status'    => false,
                    'message'   => 'Already assigned to an ibo!',
                ], 404);
            }

            DB::table('property_verifications')->insert($data);

            return response([
                'status'    => true,
                'message'   => 'Assigned for verification successfully.',
                'data'      => $data
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Property not found!'
        ], 404);
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
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $property = Property::find($id);
        if ($property) {
            //get all gallery
            $galleries = PropertyGallery::find($property->gallery_id);
            if ($galleries) {
                $dir = "/uploads/property_gallery/" . $property->id;
                //remove exterior images
                $exterior = json_decode($galleries->exterior_view);
                if (is_array($exterior) && count($exterior) > 0) {
                    $this->remove_files($dir . '/exterior/', $exterior);
                }
                //remove living_room images
                $living_room = json_decode($galleries->living_room);
                if (is_array($living_room) && count($living_room) > 0) {
                    $this->remove_files($dir . '/living_room/', $living_room);
                }
                //remove bedrooms images
                $bedrooms = json_decode($galleries->bedrooms);
                if (is_array($bedrooms) && count($bedrooms) > 0) {
                    $this->remove_files($dir . '/bedrooms/', $bedrooms);
                }
                //remove bathrooms images
                $bathrooms = json_decode($galleries->bathrooms);
                if (is_array($bathrooms) && count($bathrooms) > 0) {
                    $this->remove_files($dir . '/bathrooms/', $bathrooms);
                }
                //remove kitchen images
                $kitchen = json_decode($galleries->kitchen);
                if (is_array($kitchen) && count($kitchen) > 0) {
                    $this->remove_files($dir . '/kitchen/', $kitchen);
                }
                //remove floor_plan images
                $floor_plan = json_decode($galleries->floor_plan);
                if (is_array($floor_plan) && count($floor_plan) > 0) {
                    $this->remove_files($dir . '/floor_plan/', $floor_plan);
                }
                //remove master_plan images
                $master_plan = json_decode($galleries->master_plan);
                if (is_array($master_plan) && count($master_plan) > 0) {
                    $this->remove_files($dir . '/master_plan/', $master_plan);
                }
                //remove location_map images
                $location_map = json_decode($galleries->location_map);
                if (is_array($location_map) && count($location_map) > 0) {
                    $this->remove_files($dir . '/location_map/', $location_map);
                }

                $galleries->delete();
            }

            //remove address
            $address = Address::find($property->address_id);
            if ($address) {
                $address->delete();
            }

            //remove essentials
            $essentials = PropertyEssential::find($property->property_essential_id);
            if ($essentials) {
                $essentials->delete();
            }

            if ($property->delete()) {
                return response([
                    'status'    => true,
                    'message'   => 'Property deleted successfully.',
                    'data'      => $property
                ], 200);
            }

            return response([
                'status'    => false,
                'message'   => 'Something went wrong!'
            ], 500);
        }

        return response([
            'status'    => false,
            'message'   => 'Property not found!'
        ], 404);
    }

    //remove files from serve
    protected function remove_files($dir, $files)
    {
        if ($dir && is_array($files)) {
            foreach ($files as $file) {
                if (Storage::disk('digitalocean')->exists($dir . basename($file))) {
                    Storage::disk('digitalocean')->delete($dir . basename($file));
                }
            }
        }
    }
}
