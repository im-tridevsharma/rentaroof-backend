<?php

namespace App\Http\Controllers\api\user;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Amenity;
use App\Models\Meeting;
use App\Models\Property;
use App\Models\PropertyEssential;
use App\Models\PropertyGallery;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class PropertyController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $properties = Property::where("posted_by", JWTAuth::user()->id)->get();
        if ($properties) {
            return response([
                'status'    => true,
                'message'   => 'Properties fetched successfully.',
                'data'      => $properties
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Something went wrong!'
        ], 500);
    }

    //get total properties of a user
    public function total(Request $request)
    {
        $count = Property::where("posted_by", JWTAuth::user()->id)->count();
        if ($count >= 0) {
            return response([
                'status'    => true,
                'message'   => 'Total fetched successfully.',
                'data'      => $count
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Something went wrong!'
        ], 500);
    }


    //schedule appointment
    public function appointment(Request $request, $id)
    {

        $validator = Validator::make($request->all(), [
            'name'  => 'required|string|between:2,50',
            'email' => 'required|string',
            'contact' => 'required|between:10,12',
        ]);

        if ($validator->fails()) {
            return response([
                'status'    => false,
                'message'   => 'Some errors occured.',
                'error'     => $validator->errors()
            ], 400);
        }

        $property = Property::find($id);
        if ($property) {
            $address = Address::find($property->address_id);

            $latitude = $address->lat;
            $longitude = $address->long;
            $ibos = DB::table("addresses")
                ->select("user_id", DB::raw("6371 * acos(cos(radians(" . $latitude . "))
                * cos(radians(lat)) * cos(radians(`long`) - radians(" . $longitude . "))
                + sin(radians(" . $latitude . ")) * sin(radians(lat))) AS distance"))
                ->having('distance', '>', 20)
                ->orderBy('distance', 'asc')
                ->where("user_id", "!=", NULL)->get();

            $createid = 'ID-' . time();

            if (count($ibos) > 0) {
                foreach ($ibos as $ibo) {
                    $user = User::where("role", "ibo")->where("id", $ibo->user_id)->first();
                    if ($user) {
                        $meeting = new  Meeting;
                        $meeting->create_id = $createid;
                        $meeting->title = 'Property visit request';
                        $meeting->description = 'Visit for property ' . $property->property_code;
                        $meeting->user_id = $user->id;
                        $meeting->user_role = $user->role;
                        $meeting->property_id = $property->id;
                        $meeting->name = $request->name;
                        $meeting->contact = $request->contact;
                        $meeting->email = $request->email;
                        $meeting->start_time = !empty($request->date) && !empty($request->time) ? date("Y-m-d H:i:s", strtotime($request->date . ' ' . $request->time)) : NULL;
                        $meeting->end_time_expected = NULL;
                        $meeting->end_time = NULL;
                        $meeting->created_by_name = $request->name;
                        $meeting->created_by_role = JWTAuth::user() ? JWTAuth::user()->role : 'guest';
                        $meeting->created_by_id = JWTAuth::user() ? JWTAuth::user()->id : NULL;
                        $meeting->meeting_history = json_encode([]);

                        $meeting->save();
                    }
                }

                return response([
                    'status'    => true,
                    'message'   => 'Secheduled successfully.',
                ], 200);
            } else {
                return response([
                    'status'    => true,
                    'message'   => 'Sorry! Executives are not available right now.',
                ], 400);
            }
        }

        return response([
            'status'    => false,
            'message'   => 'Property not found!'
        ], 404);
    }

    //search properties
    public function search(Request $request)
    {
        $properties = Property::where("is_approved", 1)->where(function ($query) use ($request) {
            if ($request->has('search') && !empty($request->search)) {
                $query->where("name", "like", "%" . $request->search . "%");
                $query->orWhere("property_code", "like", "%" . $request->search . "%");
                $query->orWhere("country_name", "like", "%" . $request->search . "%");
                $query->orWhere("state_name", "like", "%" . $request->search . "%");
                $query->orWhere("city_name", "like", "%" . $request->search . "%");
                $query->orWhere("pincode", "like", "%" . $request->search . "%");
            }
        })->where(function ($q) use ($request) {
            if ($request->has('bath') && !empty($request->bath)) {
                $q->where("bathrooms", $request->bath);
            }
            if ($request->has('bed') && !empty($request->bed)) {
                $q->where("bedrooms", $request->bed);
            }
            if ($request->has('ptype') && !empty($request->ptype)) {
                $q->where("type", $request->ptype);
            }
            if ($request->has('min_price') && !empty($request->min_price)) {
                $q->where("monthly_rent", ">=", $request->min_price);
            }
            if ($request->has('max_price') && !empty($request->max_price)) {
                $q->where("monthly_rent", "<=", $request->max_price);
            }
            if ($request->has('available_from') && !empty($request->available_from)) {
                $q->whereDate("available_from", ">=", date("Y-m-d", strtotime($request->available_from)));
            }
            if ($request->has('available_to') && !empty($request->available_to)) {
                $q->whereDate("available_from", "<=", date("Y-m-d", strtotime($request->available_to)));
            }
        })->with("address")->get();

        return response([
            'status'    => true,
            'message'   => 'Properties searched successfully.',
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
        $validator = Validator::make($request->all(), [
            'name'  => 'required|string|between:2,100',
            'short_description' => 'required|string|max:255',
            'for'   => 'required|in:rent,sale',
            'type'  => 'required',
            'posting_as'    => 'required|in:full_house,sharing_basis',
            'ownership_type'    => 'required|in:sole,joint,ownership',
            'furnished_status'  => 'required|in:furnished,unfurnished,semi-furnished,ongoing',
            'bedrooms'  => 'required',
            'balconies' => 'required',
            'floors'    => 'required',
            'bathrooms' => 'required',
            'super_area'    => 'required',
            'super_area_unit'   => 'required|in:sqft,cm,m',
            'available_from'    => 'required',
            'monthly_rent'      => 'required',
            'security_amount'   => 'required',
            'age_of_construction'   => 'required'
        ]);

        if ($validator->fails()) {
            return response([
                'status'    => false,
                'message'   => 'Some errors occured',
                'error'     => $validator->errors()
            ], 400);
        }

        $property = new Property($request->input());

        $property->property_code = 'RARP-0' . rand(11111, 99999) . '0';

        if (isset($request->custom_bedrooms) && !empty($request->custom_bedrooms)) {
            $property->bedrooms = $request->custom_bedrooms;
        }

        if (isset($request->custom_bathrooms) && !empty($request->custom_bathrooms)) {
            $property->bathrooms = $request->custom_bathrooms;
        }

        if (isset($request->custom_balconies) && !empty($request->custom_balconies)) {
            $property->balconies = $request->custom_balconies;
        }

        if (isset($request->custom_floors) && !empty($request->custom_floors)) {
            $property->floors = $request->custom_floors;
        }

        if (isset($request->available_immediately) && $request->available_immediately == 'on') {
            $property->available_immediately = 1;
        }

        $property->description = $request->description ? $request->description : '';

        $property->address_id = NULL;
        $property->front_image = '';
        $property->posted_by = JWTAuth::user()->id;

        try {
            if ($property->save()) {
                return response([
                    'status'    => true,
                    'message'   => 'Property added successfully.',
                    'data'      => $property
                ], 200);
            }

            return response([
                'status'    => false,
                'message'   => 'Unable to save data.'
            ]);
        } catch (Exception $e) {
            return response([
                'status'    => false,
                'message'   => $e,
            ], 500);
        }
    }


    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Property  $property
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        if (is_numeric($id)) {
            $property = Property::find($id);
        } else {
            $property = Property::where("property_code", $id)->first();
        }
        if ($property) {
            $amenities_data = [];
            //find and merge amenities
            $amenities = json_decode($property->amenities);
            foreach ($amenities as $a) {
                array_push($amenities_data, Amenity::find($a));
            }
            $property->amenities_data = $amenities_data;
            $property->posted_by_data = User::find($property->posted_by)->load("address");

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

    public function code($id)
    {
        $property = Property::where("property_code", $id)->first();

        if ($property) {
            $amenities_data = [];
            //find and merge amenities
            $amenities = json_decode($property->amenities);
            foreach ($amenities as $a) {
                array_push($amenities_data, Amenity::find($a));
            }
            $property->amenities_data = $amenities_data;
            $property->posted_by_data = User::find($property->posted_by)->load("address");

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

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Property  $property
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name'  => 'required|string|between:2,100',
            'short_description' => 'required|string|max:255',
            'for'   => 'required|in:rent,sale',
            'type'  => 'required',
            'posting_as'    => 'required|in:full_house,sharing_basis',
            'ownership_type'    => 'required|in:sole,joint,ownership',
            'furnished_status'  => 'required|in:furnished,unfurnished,semi-furnished,ongoing',
            'bedrooms'  => 'required',
            'balconies' => 'required',
            'floors'    => 'required',
            'bathrooms' => 'required',
            'super_area'    => 'required',
            'super_area_unit'   => 'required|in:sqft,cm,m',
            'available_from'    => 'required',
            'monthly_rent'      => 'required',
            'security_amount'   => 'required',
            'age_of_construction'   => 'required'
        ]);

        if ($validator->fails()) {
            return response([
                'status'    => false,
                'message'   => 'Some errors occured',
                'error'     => $validator->errors()
            ], 400);
        }

        $property = Property::find($id);
        if ($property) {

            $property->name = $request->name;
            $property->short_description = $request->short_description;
            $property->for = $request->for;
            $property->type = $request->type;
            $property->posting_as = $request->posting_as;
            $property->ownership_type = $request->ownership_type;
            $property->furnished_status = $request->furnished_status;
            $property->bedrooms = $request->bedrooms;
            $property->balconies = $request->balconies;
            $property->floors = $request->floors;
            $property->bathrooms = $request->bathrooms;
            $property->super_area = $request->super_area;
            $property->super_area_unit = $request->super_area_unit;
            $property->available_from = $request->available_from;
            $property->monthly_rent = $request->monthly_rent;
            $property->security_amount = $request->security_amount;
            $property->age_of_construction = $request->age_of_construction;

            if (isset($request->custom_bedrooms) && !empty($request->custom_bedrooms)) {
                $property->bedrooms = $request->custom_bedrooms;
            }

            if (isset($request->custom_bathrooms) && !empty($request->custom_bathrooms)) {
                $property->bathrooms = $request->custom_bathrooms;
            }

            if (isset($request->custom_balconies) && !empty($request->custom_balconies)) {
                $property->balconies = $request->custom_balconies;
            }

            if (isset($request->custom_floors) && !empty($request->custom_floors)) {
                $property->floors = $request->custom_floors;
            }

            if (isset($request->available_immediately) && $request->available_immediately == 'on') {
                $property->available_immediately = 1;
            }

            $property->description = $request->description ? $request->description : $property->description;
            $property->maintenence_charge = $request->maintenence_charge ? $request->maintenence_charge : $property->maintenence_charge;
            $property->maintenence_duration = $request->maintenence_duration ? $request->maintenence_duration : $property->maintenence_duration;
            $property->selling_price = $request->selling_price ? $request->selling_price : $property->selling_price;
            $property->offered_price = $request->offered_price ? $request->offered_price : $property->offered_price;

            try {
                if ($property->save()) {
                    return response([
                        'status'    => true,
                        'message'   => 'Property updated successfully.',
                        'data'      => $property
                    ], 200);
                }

                return response([
                    'status'    => false,
                    'message'   => 'Unable to update data.'
                ]);
            } catch (Exception $e) {
                return response([
                    'status'    => false,
                    'message'   => $e,
                ], 500);
            }
        }

        return response([
            'status'    => false,
            'message'   => 'Property not found'
        ], 404);
    }

    //save property amenity
    public function amenity(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'propertyId' => 'required'
        ]);

        if ($validator->fails()) {
            return response([
                'status'    => false,
                'message'   => 'Some errros occured.',
                'error'     => $validator->errors()
            ]);
        }

        $amenities = json_encode($request->amenities);
        $p = Property::find($request->propertyId);
        $p->amenities = $amenities;

        if ($p->save()) {
            return response([
                'status'    => true,
                'message'   => 'Amenities saved successfully.'
            ], 200);
        }

        return response([
            'staus'     => false,
            'message'   => 'Something went wrong!'
        ], 500);
    }

    //save property essential
    public function essential(Request $request)
    {
        $essential = new PropertyEssential;
        $essential->property_id = $request->propertyId;

        $essential->school = isset($request->school) ? $request->school : '';
        $essential->hospital = isset($request->hospital) ? $request->hospital : '';
        $essential->airport = isset($request->airport) ? $request->airport : '';
        $essential->train = isset($request->train) ? $request->train : '';
        $essential->market = isset($request->market) ? $request->market : '';
        $essential->restaurent = isset($request->restaurent) ? $request->restaurent : '';

        if ($essential->save()) {
            $p = Property::find($request->propertyId);
            $p->property_essential_id = $essential->id;
            $p->save();
            return response([
                'status'    => true,
                'message'   => 'Essential added successfully.'
            ], 200);
        }

        return response([
            'staus'     => false,
            'message'   => 'Something went wrong!'
        ], 500);
    }

    //update property essential
    public function essentialUpdate(Request $request, $id)
    {
        $essential = PropertyEssential::find($id);
        $essential->property_id = $request->propertyId;

        $essential->school = isset($request->school) ? $request->school : $essential->school;
        $essential->hospital = isset($request->hospital) ? $request->hospital : $essential->hospital;
        $essential->airport = isset($request->airport) ? $request->airport : $essential->airport;
        $essential->train = isset($request->train) ? $request->train : $essential->train;
        $essential->market = isset($request->market) ? $request->market : $essential->market;
        $essential->restaurent = isset($request->restaurent) ? $request->restaurent : $essential->restaurent;

        if ($essential->save()) {
            $p = Property::find($request->propertyId);
            $p->property_essential_id = $essential->id;
            $p->save();
            return response([
                'status'    => true,
                'message'   => 'Essential updated successfully.'
            ], 200);
        }

        return response([
            'staus'     => false,
            'message'   => 'Something went wrong!'
        ], 500);
    }
    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Property  $property
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
