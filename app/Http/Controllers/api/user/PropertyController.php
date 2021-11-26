<?php

namespace App\Http\Controllers\api\user;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Amenity;
use App\Models\Meeting;
use App\Models\Preference;
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

    //get property by user id
    public function property_by_user($id)
    {
        $user = User::find($id);
        if ($user) {
            $properties = Property::where("posted_by", $user->id)->where("is_approved", 1)->get();
            return response([
                'status'    => true,
                'messsage'  => 'Properties fetched successfully.',
                'data'      => $properties
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Creator not found!'
        ], 404);
    }

    //for_verification
    public function for_verification()
    {
        $user = JWTAuth::user();

        if ($user) {
            $properties = DB::table('property_verifications')->where("ibo_id", $user->id)->orderBy("id", "desc")->get()->map(function ($p) {
                $property = Property::find($p->property_id);
                if ($property) {
                    $p->property = $property;
                    $p->landlord = User::find($property->posted_by);
                    $p->address  = Address::find($property->address_id);
                    return $p;
                }
            });
            return response([
                'status'    => true,
                'message'   => 'Properties fetched successfully.',
                'data'      => $properties
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Unauthorized access!'
        ], 401);
    }

    //change status
    public function change_verification_status(Request $request, $id)
    {
        $is = DB::table('property_verifications')->where("id", $id)->first();
        if ($is) {
            $data = [
                "is_verifiable" => !empty($request->status) ? $request->status : 0,
                "issues_in_verification"    => !empty($request->issue) ? $request->issue : '',
                "updated_at"    => date("Y-m-d H:i:s")
            ];

            DB::table('property_verifications')->where("id", $id)->update($data);

            $returndata = DB::table('property_verifications')->where("id", $id)->first();
            if ($returndata) {
                $property = Property::find($returndata->property_id);
                $returndata->property = $property;
                $returndata->landlord = User::find($property->posted_by);
                $returndata->address  = Address::find($property->address_id);
            }

            return response([
                'status'    => true,
                'message'   => 'Status updated successfully.',
                'data'      => $returndata
            ], 200);
        }

        return  response([
            'status'    => false,
            'message'   => 'Not assigned to any ibo!'
        ], 404);
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
                ->orderBy('distance', 'asc')->distinct()
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
            if ($request->has('posted_by') && !empty($request->posted_by)) {
                $q->where("posted_by", $request->posted_by);
            }
            if ($request->has('bath') && !empty($request->bath)) {
                $q->where("bathrooms", $request->bath);
            }
            if ($request->has('ownership') && !empty($request->ownership)) {
                $q->whereIn("ownership_type", explode(",", $request->ownership));
            }
            if ($request->has('bed') && !empty($request->bed)) {
                $q->where("bedrooms", $request->bed);
            }
            if ($request->has('furnishing') && !empty($request->furnishing)) {
                $q->where("furnished_status", $request->furnishing);
            }
            if ($request->has('ptype') && !empty($request->ptype)) {
                $q->where("type", $request->ptype);
            }
            if ($request->has('amenities') && !empty($request->amenities)) {
                $a = explode(",", $request->amenities);
                $a = implode('", "', $a);
                $q->whereRaw('JSON_CONTAINS(amenities, \'["' . $a . '"]\')');
            }
            if ($request->has('min_price') && !empty($request->min_price)) {
                $q->where("monthly_rent", ">=", $request->min_price);
            }
            if ($request->has('max_price') && !empty($request->max_price)) {
                $q->where("monthly_rent", "<=", $request->max_price);
            }
            if ($request->has('min_size') && !empty($request->min_size)) {
                $q->where("super_area", ">=", $request->min_size);
            }
            if ($request->has('max_size') && !empty($request->max_size)) {
                $q->where("super_area", "<=", $request->max_size);
            }
            if ($request->has('readytomove') && $request->readytomove == 'yes') {
                $q->where("available_immediately", 1);
            }
            if ($request->has('available_from') && !empty($request->available_from)) {
                $q->whereDate("available_from", ">=", date("Y-m-d", strtotime($request->available_from)));
            }
            if ($request->has('available_to') && !empty($request->available_to)) {
                $q->whereDate("available_from", "<=", date("Y-m-d", strtotime($request->available_to)));
            }
        })->with("address");

        if ($request->has("sorting")) {
            $properties = $request->sorting == 'newest' ? $properties->orderBy("created_at", "desc") : $properties->orderBy("created_at", "asc");
        }

        if ($request->has("pagination") && $request->pagination === 'yes') {
            $properties = $properties->paginate(5);
        } else {
            $properties = $properties->get();
        }

        return response([
            'status'    => true,
            'message'   => 'Properties searched successfully.',
            'data'      => $properties
        ], 200);
    }

    //search_by_coords
    public function search_by_coords(Request $request)
    {
        $properties = Property::where("is_approved", 1);
        $properties->join("addresses", "addresses.id", "=", "properties.address_id");
        $properties->where("addresses.lat", "<=", floatval($request->north));
        $properties->where("addresses.lat", ">=", floatval($request->south));
        $properties->where("addresses.long", "<=", floatval($request->east));
        $properties->where("addresses.long", ">=", floatval($request->west));

        if ($request->has("pagination") && $request->pagination === 'yes') {
            $properties = $properties->paginate(5);
        } else {
            $properties = $properties->get();
        }

        return response([
            'status'    => true,
            'message'   => 'Properties searched successfully.',
            'data'      => $properties
        ], 200);
    }

    //get similar properties
    public function get_similar_properties($code, $limit)
    {
        $property = Property::where("property_code", $code)->first();
        if ($property) {
            $properties = Property::where("is_approved", 1)->where(function ($query) use ($property) {
                $query->where("bedrooms", $property->bedrooms)
                    ->orWhere("bathrooms", $property->bathrooms)
                    ->orWhere("floors", $property->floors)
                    ->orWhere("furnished_status", $property->furnished_status)
                    ->orWhere("name", "like", "%" . $property->name . "%")
                    ->orWhere("city_name", "like", "%" . $property->city_name . "%")
                    ->orWhere("pincode", "like", "%" . $property->pincode . "%");
            })->where("property_code", "!=", $code)->with("address");
            if ($limit !== 'all' && is_numeric($limit)) {
                $properties = $properties->paginate($limit);
            } else {
                $properties = $properties->get();
            }

            return response([
                'status'    => true,
                'message'   => 'Similar Properties fetched successfully.',
                'data'      => $properties
            ], 200);
        } else {
            return response([
                'status'    => false,
                'message'   => 'Similar properties not found!'
            ], 403);
        }
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

        if (isset($request->offered_price) && !empty($request->offered_price)) {
            $property->offered_price = $request->offered_price;
        } else {
            $property->offered_price = 0.0;
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

        $property->front_image = '';
        $property->posted_by = JWTAuth::user()->id;

        if (JWTAuth::user() && JWTAuth::user()->role === 'landlord') {
            $property->landlord = JWTAuth::user()->id;
        }

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
            $preferences_data = [];
            //find and merge amenities
            $amenities = json_decode($property->amenities);
            $preferences = json_decode($property->preferences);
            if (is_array($amenities)) {
                foreach ($amenities as $a) {
                    array_push($amenities_data, Amenity::find($a));
                }
            }
            if (is_array($preferences)) {
                foreach ($preferences as $p) {
                    array_push($preferences_data, Preference::find($p));
                }
            }
            $property->amenities_data = $amenities_data;
            $property->preferences_data = $preferences_data;

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
            $preferences_data = [];
            //find and merge amenities
            $amenities = json_decode($property->amenities);
            $preferences = json_decode($property->preferences);
            if (is_array($amenities)) {
                foreach ($amenities as $a) {
                    array_push($amenities_data, Amenity::find($a));
                }
            }
            if (is_array($preferences)) {
                foreach ($preferences as $p) {
                    array_push($preferences_data, Preference::find($p));
                }
            }
            $property->amenities_data = $amenities_data;
            $property->preferences_data = $preferences_data;

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
            $property->lease_period = $request->lease_period ? $request->lease_period : $property->lease_period;
            $property->offered_price = $request->offered_price ? $request->offered_price : $property->offered_price;

            if (JWTAuth::user() && JWTAuth::user()->role === 'landlord') {
                $property->landlord = JWTAuth::user()->id;
            }

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
            ], 400);
        }

        $amenities = json_encode($request->amenities);
        $preferences = json_encode($request->preferences);
        $p = Property::find($request->propertyId);
        $p->amenities = $amenities;
        $p->preferences = $preferences;

        if ($p->save()) {
            return response([
                'status'    => true,
                'message'   => 'Amenities and Preferences saved successfully.'
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
        $validator = Validator::make($request->all(), [
            "propertyId" => "required"
        ]);

        if ($validator->fails()) {
            return response([
                'staus'     => false,
                'message'   => 'Some error occured!',
                'error'     => $validator->errors()
            ], 400);
        }

        $customs = [];

        if ($request->filled('name') && is_array($request->name)) {
            for ($i = 0; $i < count($request->name); $i++) {
                $customs[$request->name[$i]] = $request->value[$i];
            }
        }

        $essential = new PropertyEssential;
        $essential->property_id = $request->propertyId;

        $essential->school = isset($request->school) ? $request->school : '';
        $essential->hospital = isset($request->hospital) ? $request->hospital : '';
        $essential->airport = isset($request->airport) ? $request->airport : '';
        $essential->train = isset($request->train) ? $request->train : '';
        $essential->market = isset($request->market) ? $request->market : '';
        $essential->restaurent = isset($request->restaurent) ? $request->restaurent : '';
        $essential->customs = json_encode($customs);

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
        $validator = Validator::make($request->all(), [
            "propertyId" => "required"
        ]);

        if ($validator->fails()) {
            return response([
                'staus'     => false,
                'message'   => 'Some error occured!',
                'error'     => $validator->errors()
            ], 400);
        }

        $customs = [];

        if ($request->filled('name') && is_array($request->name)) {
            for ($i = 0; $i < count($request->name); $i++) {
                $customs[$request->name[$i]] = $request->value[$i];
            }
        }

        $essential = PropertyEssential::find($id);

        if ($essential) {
            $essential->property_id = $request->propertyId;

            $essential->school = isset($request->school) ? $request->school : $essential->school;
            $essential->hospital = isset($request->hospital) ? $request->hospital : $essential->hospital;
            $essential->airport = isset($request->airport) ? $request->airport : $essential->airport;
            $essential->train = isset($request->train) ? $request->train : $essential->train;
            $essential->market = isset($request->market) ? $request->market : $essential->market;
            $essential->restaurent = isset($request->restaurent) ? $request->restaurent : $essential->restaurent;
            $essential->customs = json_encode($customs);

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

        return response([
            'staus'     => false,
            'message'   => 'Essential not found'
        ], 404);
    }
    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Property  $property
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, $id)
    {
        $property = Property::find($id);
        if ($property) {

            if (false) {
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
            }

            $property->is_deleted = 1;
            $property->delete_reason = $request->filled('delete_reason') ? $request->delete_reason : '';

            if ($property->save()) {
                return response([
                    'status'    => true,
                    'message'   => 'Property delete request sent successfully.',
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

    public function addPin(Request $request, $id)
    {
        $user = JWTAuth::user();
        if ($user) {
            $property = Property::where("posted_by", $user ? $user->id : '')->where("id", $id)->first();
            if ($property) {
                $property->country_name = $request->has('full_address') ? $request->full_address : $property->country_name;
                $property->pincode = $request->has('pincode') ? $request->pincode : $property->pincode;
                $address = Address::find($property->address_id);
                if ($address) {
                    $address->pincode = $request->has('pincode') ? $request->pincode : $address->pincode;
                    $address->full_address = $request->has('full_address') ? $request->full_address : $address->full_address;
                    $address->lat = $request->has('lat') ? $request->lat : $address->lat;
                    $address->long = $request->has('long') ? $request->long : $address->long;
                    $address->street_view = $request->has('street_view') ? $request->street_view : $address->street_view;
                    $address->zoom_level = $request->has('zoom_level') ? $request->zoom_level : $address->zoom_level;

                    if ($address->save()) {
                        return response([
                            'status'    => true,
                            'message'   => 'Pinned successfully.',
                        ], 200);
                    }

                    return response([
                        'status'    => false,
                        'message'   => 'Something went wrong',
                    ], 500);
                }

                return response([
                    'status'    => false,
                    'message'   => 'Address not found for this.'
                ], 404);
            }

            return response([
                'status'    => false,
                'message'   => 'Property not found for this.'
            ], 404);
        }

        return response([
            'status'    => false,
            'message'   => 'Unauthorized!'
        ], 401);
    }
}
