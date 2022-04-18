<?php

namespace App\Http\Controllers\api\user;

use App\Events\AdminNotificationSent;
use App\Events\DealUpdated;
use App\Events\NotificationSent;
use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\AdminNotification;
use App\Models\Agreement;
use App\Models\Amenity;
use App\Models\IboEarning;
use App\Models\IboNotification;
use App\Models\KycVerification;
use App\Models\LandlordNotification;
use App\Models\Meeting;
use App\Models\Preference;
use App\Models\Property;
use App\Models\PropertyDeal;
use App\Models\PropertyEssential;
use App\Models\PropertyGallery;
use App\Models\PropertyRatingAndReview;
use App\Models\TenantNotification;
use App\Models\User;
use App\Models\UserSavedProperty;
use App\Models\Wallet;
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
        $user = JWTAuth::user();
        if ($user->role !== 'ibo') {
            $properties = Property::where("posted_by", $user->id)->get();
        } else {
            $properties = Property::where("ibo", $user->id)->get()->map(function ($q) {
                $owner = User::find($q->landlord);
                $q->owner = $owner ? $owner->first . ' ' . $owner->last : '';
                return $q;
            });
        }
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
            $properties = DB::table('property_verifications')->where("ibo_id", $user->id)->where("status", "!=", "rejected")->orderBy("id", "desc")->get()->map(function ($p) {
                $property = Property::select(["front_image", "id", "name", "address_id", "property_code", "posted_by", "is_closed", "is_approved", "country_name", "state_name", "city_name"])->find($p->property_id);
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

            if ($request->has('inspection') && $request->inspection) {
                DB::table("inspections")->insert([
                    'property_id'   => $is->property_id,
                    'ibo_id'        => JWTAuth::user()->id ?? 0,
                    'address'       => $request->address,
                    'super_area'    => $request->super_area,
                    'carpet_area'   => $request->carpet_area,
                    'bedrooms'      => $request->bedrooms,
                    'bathrooms'     => $request->bathrooms,
                    'balconies'     => $request->balconies,
                    'floors'        => $request->floors,
                    'renting_amount' => $request->renting_amount,
                    'images'        => $request->images,
                    'amenities'     => $request->amenities,
                    'preferences'   => $request->preferences,
                    'essentials'    => $request->essentials,
                    'inspection_note' => $request->inspection_note ?? '',
                    'created_at'    => date('Y-m-d H:i:s'),
                    'updated_at'    => date('Y-m-d H:i:s')
                ]);
            }

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

            if ($request->filled('status') && $request->status === 1) {
                //assign points to ibo when verify
                $point_value  = DB::table('settings')->where("setting_key", "point_value")->first()->setting_value;
                $v_point  = DB::table('settings')->where("setting_key", "ibo_property_verification_point")->first()->setting_value;

                $spoints = floatval($v_point) * floatval($point_value);

                $ibo = User::find($is->ibo_id);
                $property = Property::find($is->property_id);
                //point data
                $sdata = [
                    "user_id"   => $ibo->id,
                    "role"      => $ibo->role,
                    "title"     => "You earned " . $v_point . " points for verifying property - " . $property->name . ' (' . $property->property_code . ')',
                    "point_value"   => $point_value,
                    "points"    => $v_point,
                    "amount_earned" => $spoints,
                    "type"  => "credit",
                    "for"  => "referral",
                    "created_at"    => date("Y-m-d H:i:s"),
                    "updated_at"    => date("Y-m-d H:i:s"),
                ];

                DB::table('user_referral_points')->insert($sdata);

                try {
                    //add amount to wallet
                    $wallet = Wallet::where("user_id", $ibo->id)->first();
                    $wallet->amount += floatval($spoints);
                    $wallet->credit += floatval($spoints);
                    $wallet->last_credit_transaction = date('Y-m-d H:i:s');
                    $wallet->last_transaction_type = 'credit';
                    $wallet->save();
                } catch (Exception $e) {
                    //
                }

                //notification to ibo
                $ibo_notify = new IboNotification;
                $ibo_notify->ibo_id = $ibo->id;
                $ibo_notify->type = 'Urgent';
                $ibo_notify->title = 'Coins Earned';
                $ibo_notify->content = "You earned " . $v_point . " points for verifying property - " . $property->name . ' (' . $property->property_code . ')';
                $ibo_notify->name = 'Rent A Roof';
                $ibo_notify->redirect = '/ibo/payment';

                $ibo_notify->save();

                event(new NotificationSent($ibo_notify));
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

    //accept_or_reject_verification
    public function accept_or_reject_verification(Request $request, $id)
    {
        $is = DB::table('property_verifications')->where("id", $id)->first();
        if ($is) {
            $data = [
                "status" => !empty($request->status) ? $request->status : 'pending',
                "reason_for_rejection"    => !empty($request->reason) ? $request->reason : '',
                "updated_at"    => date("Y-m-d H:i:s")
            ];

            DB::table('property_verifications')->where("id", $id)->update($data);

            //delete rest if exist
            if ($request->status === 'accepted') {
                DB::table('property_verifications')->where("ibo_id", "!=", JWTAuth::user()->id)
                    ->where("property_id", $is->property_id)->delete();
            }

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
            'name'    => 'required|string|between:2,50',
            'email'   => 'required|string',
            'contact' => 'required|between:10,12',
            'date'    => 'required',
            'time'    => 'required'
        ]);

        if ($validator->fails()) {
            return response([
                'status'    => false,
                'message'   => 'Some errors occured.',
                'error'     => $validator->errors()
            ], 400);
        }

        if (JWTAuth::user() && empty(JWTAuth::user()->role)) {
            return response([
                'status'    => false,
                'message'   => 'Your account is not well set.',
            ], 400);
        }

        $property = Property::find($id);
        if ($property) {

            if ($property->is_closed) {
                return response([
                    'status'    => false,
                    'message'   => 'Property already booked. You can select another property or book another property.',
                ], 499);
            }

            //check if meeting exist for same
            $is_meeting = Meeting::where("property_id", $property->id)->where("created_by_id", JWTAuth::user() ? JWTAuth::user()->id : 0)->count();
            if ($is_meeting > 0) {
                return response([
                    'status'    => false,
                    'message'   => 'Already scheduled a visit for this property.',
                ], 403);
            }

            //Check property owner
            $ibo = User::find($property->ibo);
            if (
                $property->ibo
                // && $ibo->ibo_duty_mode === 'online'
            ) {
                $meeting = new  Meeting;
                $meeting->create_id = time();
                $meeting->title = 'Property visit request';
                $meeting->description = 'Visit for property ' . $property->property_code;
                $meeting->user_id = $ibo->id;
                $meeting->user_role = $ibo->role;
                $meeting->property_id = $property->id;
                $meeting->name = $request->name;
                $meeting->contact = $request->contact;
                $meeting->email = $request->email;
                $meeting->start_time = !empty($request->date) && !empty($request->time) ? date("Y-m-d H:i:s", strtotime($request->date . ' ' . $request->time)) : NULL;
                $meeting->end_time_expected = NULL;
                $meeting->end_time = NULL;
                $meeting->created_by_name = $request->name;
                $meeting->landlord_status = 'pending';
                $meeting->created_by_role = JWTAuth::user() ? JWTAuth::user()->role : 'guest';
                $meeting->created_by_id = JWTAuth::user() ? JWTAuth::user()->id : NULL;
                $meeting->meeting_history = json_encode([]);

                $meeting->save();

                //send notification to ibo for appointment request
                $ibo_notify = new IboNotification;
                $ibo_notify->ibo_id = $ibo->id;
                $ibo_notify->type = 'Urgent';
                $ibo_notify->title = 'You have new appointment.';
                $ibo_notify->content = 'You have new appointment for property - ' . $property->property_code . '. Scheduled at ' . date('d-m-Y H:i', strtotime($meeting->start_time));
                $ibo_notify->name = 'Rent A Roof';
                $ibo_notify->redirect = '/ibo/appointment';

                $ibo_notify->save();

                event(new NotificationSent($ibo_notify));
            } else if ($ibo) {
                DB::table('payment_splits')->insert([
                    'property_id'   => $property->id,
                    'ibo_id'        => $ibo->id,
                ]);
            }

            $address = Address::find($property->address_id);

            $latitude = $address->lat ?? 0;
            $longitude = $address->long ?? 0;
            $ibos = DB::table("addresses")
                ->select("user_id", DB::raw("6371 * acos(cos(radians(" . $latitude . "))
                * cos(radians(lat)) * cos(radians(`long`) - radians(" . $longitude . "))
                + sin(radians(" . $latitude . ")) * sin(radians(lat))) AS distance"))
                ->join('users', 'users.id', '=', 'addresses.user_id')
                ->where('users.role', '=', 'ibo')
                ->having('distance', '<', env('ASSIGN_DISTANCE'))
                ->orderBy('distance', 'asc')->distinct()
                ->where("user_id", "!=", NULL)->get();

            $createid = 'ID-' . time();

            if (count($ibos) > 0) {
                if (!$ibo) {
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
                            $meeting->start_time = !empty($request->date) && !empty($request->time) ? date("Y-m-d H:i:s", strtotime($request->date . ' ' . $request->time)) : date('Y-m-d H:i:s', strtotime('+1day'));
                            $meeting->end_time_expected = NULL;
                            $meeting->end_time = NULL;
                            $meeting->created_by_name = $request->name;
                            $meeting->created_by_role = JWTAuth::user() ? JWTAuth::user()->role : 'guest';
                            $meeting->created_by_id = JWTAuth::user() ? JWTAuth::user()->id : NULL;
                            $meeting->meeting_history = json_encode([]);

                            $meeting->save();

                            //send notification to ibo for appointment request
                            $ibo_notify = new IboNotification;
                            $ibo_notify->ibo_id = $user->id;
                            $ibo_notify->type = 'Urgent';
                            $ibo_notify->title = 'You have new appointment.';
                            $ibo_notify->content = 'You have new appointment for property - ' . $property->property_code . '. Scheduled at ' . date('d-m-Y H:i', strtotime($meeting->start_time));
                            $ibo_notify->name = 'Rent A Roof';
                            $ibo_notify->redirect = '/ibo/appointment';

                            $ibo_notify->save();

                            event(new NotificationSent($ibo_notify));
                        }
                    }
                }
            } else if (!$ibo) {
                $meeting = new  Meeting;
                $meeting->create_id = time();
                $meeting->title = 'Property visit request';
                $meeting->description = 'Visit for property ' . $property->property_code;
                $meeting->user_id = null;
                $meeting->user_role = '';
                $meeting->property_id = $property->id;
                $meeting->name = $request->name;
                $meeting->contact = $request->contact;
                $meeting->email = $request->email;
                $meeting->start_time = !empty($request->date) && !empty($request->time) ? date("Y-m-d H:i:s", strtotime($request->date . ' ' . $request->time)) : date('Y-m-d H:i:s', strtotime('+1day'));
                $meeting->end_time_expected = NULL;
                $meeting->end_time = NULL;
                $meeting->created_by_name = $request->name;
                $meeting->created_by_role = JWTAuth::user() ? JWTAuth::user()->role : 'guest';
                $meeting->created_by_id = JWTAuth::user() ? JWTAuth::user()->id : NULL;
                $meeting->meeting_history = json_encode([]);

                $meeting->save();

                return response([
                    'status'    => false,
                    'message'   => 'Appointment has been scheduled! Executives are not available near this property, we will be in touch with you asap.',
                ], 400);
            }

            if ((count($ibos) > 0 || $ibo->role === 'ibo')) {
                //notify admin
                $an = new AdminNotification;
                $an->content = 'New meeting request for property - ' . $property->property_code . '. Assigned to near by agents.';
                $an->type  = 'Urgent';
                $an->title = 'New Meeting Request';
                $an->redirect = '/admin/meetings';
                $an->save();

                event(new AdminNotificationSent($an));


                //notify user meeting is scheduled
                $user_notify = new TenantNotification;
                $user_notify->tenant_id = JWTAuth::user() ? JWTAuth::user()->id : 0;
                $user_notify->type = 'Urgent';
                $user_notify->title = 'You successfully scheduled a visit.';
                $user_notify->content = 'Scheduled a visit for property - ' . $property->property_code . '. Our agent will contact you soon.';
                $user_notify->name = 'Rent A Roof';

                $user_notify->save();

                event(new NotificationSent($user_notify));

                //notify landlord meeting is scheduled
                $landlord_notify = new LandlordNotification;
                $landlord_notify->landlord_id = $property->posted_by;
                $landlord_notify->type = 'Urgent';
                $landlord_notify->title = 'New meeting request.';
                $landlord_notify->content = 'New meeting request for property - ' . $property->property_code . '. Assigned to near by agents.';
                $landlord_notify->name = 'Rent A Roof';
                $landlord_notify->redirect = '/landlord/appointment';

                $landlord_notify->save();

                event(new NotificationSent($landlord_notify));

                return response([
                    'status'    => true,
                    'message'   => 'Secheduled successfully.',
                ], 200);
            } else {
                return response([
                    'status'    => false,
                    'message'   => 'Opps! Something went wrong.',
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
        $locations = Address::where(function ($q) use ($request) {

            if (!empty($request->state)) {
                $state = $request->state ?? '';
                $q->where("state", "like", "%" . $state . "%");
            }

            if (!empty($request->city)) {
                $city = $request->city ?? '';
                $q->orWhere("city", "like", "%" . $city . "%");
            }

            if (!empty($request->zone)) {
                $zone = $request->zone ?? '';
                $q->orWhere("zone", "like", "%" . $zone . "%");
            }

            if (!empty($request->area)) {
                $area = $request->area ?? '';
                $q->orWhere("area", "like", "%" . $area . "%");
            }

            if (!empty($request->sub_area)) {
                $sub_area = $request->sub_area ?? '';
                $q->orWhere("sub_area", "like", "%" . $sub_area . "%");
            }

            if (!empty($request->route)) {
                $route = $request->route ?? '';
                $q->orWhere("route", "like", "%" . $route . "%");
            }

            if (!empty($request->pincode)) {
                $pincode = $request->pincode ?? '';
                $q->orWhere("pincode", "like", "%" . $pincode . "%");
            }
        });

        if (!empty($request->search_radius)) {
            //search radius
            $latitude = $request->lat ?? 0;
            $longitude = $request->lng ?? 0;

            $address_within_radius = DB::table("addresses")
                ->select("id", DB::raw("6371 * acos(cos(radians(" . $latitude . "))
                * cos(radians(lat)) * cos(radians(`long`) - radians(" . $longitude . "))
                + sin(radians(" . $latitude . ")) * sin(radians(lat))) AS distance"))
                ->having('distance', '<', $request->search_radius ?? 5)
                ->orderBy('distance', 'asc')->distinct()->pluck('id')->toArray();

            $locations->whereIn("id", $address_within_radius);
        }

        $locations = $locations->pluck('id')->toArray();

        $properties = Property::where("is_approved", 1)
            ->where('is_closed', 0)
            ->where(function ($query) use ($request) {
                if ($request->has('search') && !empty($request->search)) {
                    // $query->orWhere("name", "like", "%" . $request->search . "%");
                    // $query->orWhere("property_code", "like", "%" . $request->search . "%");
                }
            })->where(function ($q) use ($request, $locations) {
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


                if ($request->has('budget') && !empty($request->budget)) {
                    $price = explode("-", $request->budget);
                    $min_price = floatval($price[0] ?? 1000);
                    $max_price = floatval($price[1] ?? 20000);

                    $q->where("monthly_rent", ">=", $min_price);
                    $q->where("monthly_rent", "<=", $max_price);
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

                if (
                    $request->state || $request->city || $request->area ||
                    $request->sub_area || $request->zone || $request->pincode
                    || $request->route
                ) {
                    $q->whereIn("address_id", $locations);
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

        //loggedin user
        $user = JWTAuth::user();
        if ($user) {
            $properties = $properties->map(function ($p) use ($user) {
                $is_saved = UserSavedProperty::where("property_id", $p->id)->where("user_id", $user->id)->where("type", "saved")->first();
                $is_favorite = UserSavedProperty::where("property_id", $p->id)->where("user_id", $user->id)->where("type", "favorite")->first();

                if ($is_saved) {
                    $p->is_saved = 'yes';
                } else {
                    $p->is_saved = 'no';
                }
                if ($is_favorite) {
                    $p->is_favorite = 'yes';
                } else {
                    $p->is_favorite = 'no';
                }

                return $p;
            });
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
            'for'   => 'required|in:rent',
            'type'  => 'required',
            'posting_as'    => 'required',
            'ownership_type'    => 'required',
            'furnished_status'  => 'required',
            'bedrooms'  => 'required',
            'balconies' => 'required',
            'floors'    => 'required',
            'bathrooms' => 'required',
            'super_area'    => 'required',
            'super_area_unit'   => 'required',
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

        //check if user is verified or not
        $user = JWTAuth::user();
        if ($user->account_status !== 'activated') {
            return response([
                'status'    => false,
                'message'   => 'Your account is not activated to post your property. Please contact
                to Administrator.'
            ], 401);
        }

        if ($user->account_status == 'activated') {
            $kyc = KycVerification::where("user_id", $user->id)->first();
            if ($user->role === 'ibo' && !$kyc) {
                return response([
                    'status'    => false,
                    'message'   => 'You havn\'t uploaded KYC Details yet.'
                ], 401);
            }

            if ($user->role === 'ibo' && $kyc && !$kyc->is_verified) {
                return response([
                    'status'    => false,
                    'message'   => 'Your KYC Details is not valid to post your property. Please contact
                    to Administrator.'
                ], 401);
            }
        }

        $property = new Property($request->input());

        $property->property_code = 'RARP-0' . rand(11111, 99999) . '0';
        if (JWTAuth::user()->role === 'ibo') {
            $property->ibo = JWTAuth::user()->id;
            $property->landlord  = $request->landlord ?? 0;
            $property->posted_by = $request->landlord ?? JWTAuth::user()->id;
        } else {
            $property->posted_by = JWTAuth::user()->id;
        }
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

        //inspections
        if ($request->filled('inspection_days')) {
            $property->inspection_days = $request->inspection_days;
        } else {
            $property->inspection_days = '';
        }
        if ($request->filled('inspection_time_from')) {
            $property->inspection_time_from = $request->inspection_time_from;
        } else {
            $property->inspection_time_from = '';
        }
        if ($request->filled('inspection_time_to')) {
            $property->inspection_time_to = $request->inspection_time_to;
        } else {
            $property->inspection_time_to = '';
        }

        $property->description = $request->description ? $request->description : '';
        $property->advance_amount_period = $request->advance_amount_period ?? '';

        $property->front_image = '';

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

            $property->posted_by_data = User::find($property->posted_by) ? User::find($property->posted_by)->load("address") : [];

            return response([
                'status'    => true,
                'message'   => 'Property fetched successfully.',
                'data'      => $property->load(['address', 'essential', 'gallery'])
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Propety not found.'
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
            'for'   => 'required|in:rent',
            'type'  => 'required',
            'posting_as'    => 'required',
            'ownership_type'    => 'required',
            'furnished_status'  => 'required',
            'bedrooms'  => 'required',
            'balconies' => 'required',
            'floors'    => 'required',
            'bathrooms' => 'required',
            'super_area'    => 'required',
            'super_area_unit'   => 'required',
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
            //check is he authorized to edit this property
            $l_user = JWTAuth::user();
            if ($l_user->id !== $property->posted_by && $property->ibo !== $l_user->id) {
                $pv = DB::table('property_verifications')->where("property_id", $property->id)->where("ibo_id", $l_user->id)->first();
                if ($pv) {
                    if ($pv->property_id !== $property->id || $pv->status !== 'accepted') {
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

            if (JWTAuth::user()->role === 'ibo') {
                $property->ibo = JWTAuth::user()->id;
                $property->landlord  = $request->landlord ?? 0;
                $property->posted_by = $request->landlord ?? JWTAuth::user()->id;
            } else {
                $property->posted_by = JWTAuth::user()->id;
            }

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

            //inspections
            if ($request->filled('inspection_days')) {
                $property->inspection_days = $request->inspection_days;
            }
            if ($request->filled('inspection_time_from')) {
                $property->inspection_time_from = $request->inspection_time_from;
            }
            if ($request->filled('inspection_time_to')) {
                $property->inspection_time_to = $request->inspection_time_to;
            }

            $property->description = $request->description ? $request->description : '';
            $property->maintenence_charge = $request->maintenence_charge ? $request->maintenence_charge : 0;
            $property->maintenence_duration = $request->maintenence_duration ? $request->maintenence_duration : '';
            $property->lease_period = $request->lease_period ? $request->lease_period : '';
            $property->offered_price = $request->offered_price ? $request->offered_price : 0;
            $property->advance_amount_period = $request->advance_amount_period ?? '';

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

        //check is he authorized to edit this property
        $l_user = JWTAuth::user();
        if ($l_user->id !== $p->posted_by && $l_user->id !== $p->ibo) {
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
        $essential->metro = isset($request->metro) ? $request->metro : '';
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
        $essential = $essential ? $essential : new PropertyEssential;

        if ($essential) {
            $essential->property_id = $request->propertyId;

            $essential->school = isset($request->school) ? $request->school : '';
            $essential->metro = isset($request->metro) ? $request->metro : '';
            $essential->hospital = isset($request->hospital) ? $request->hospital : '';
            $essential->airport = isset($request->airport) ? $request->airport : '';
            $essential->train = isset($request->train) ? $request->train : '';
            $essential->market = isset($request->market) ? $request->market : '';
            $essential->restaurent = isset($request->restaurent) ? $request->restaurent : '';
            $essential->customs = json_encode($customs);

            $p = Property::find($request->propertyId);

            //check is he authorized to edit this property
            $l_user = JWTAuth::user();
            if ($l_user->id !== $p->posted_by) {
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

            if ($essential->save()) {
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

    public function getDeal($id)
    {
        $deal = PropertyDeal::find($id);
        if ($deal) {
            return response([
                'status'    => true,
                'message'   => 'Deal fetched successfully.',
                'data'      => $deal
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Deal not found!'
        ], 400);
    }

    //close deal
    public function closeDeal(Request $request, $id)
    {
        $user = JWTAuth::user();
        if ($user) {
            $deal = PropertyDeal::where("created_by", $user->id)->where("id", $id)->first();
            if ($deal) {
                $deal->is_closed = 1;
                $deal->conversationId = $request->has('convesationId') ? $request->conversationId : 0;
                $deal->save();
                $deal->property = Property::where("id", $deal->property_id)->first(['name', 'property_code', 'front_image', 'monthly_rent']);

                event(new DealUpdated($deal));

                return response([
                    'status'    => true,
                    'message'   => 'Deal closed successfully.',
                    'data'      => $deal
                ], 200);
            }

            return response([
                'status'    => false,
                'message'   => 'Deal not found!'
            ], 400);
        }

        return response([
            'status'    => false,
            'message'   => 'User not found!'
        ], 400);
    }

    //change deal status
    public function updateDealStatus(Request $request, $id)
    {
        $user = JWTAuth::user();
        if ($user) {
            $deal = PropertyDeal::where("offer_for", $user->id)->where("id", $id)->first();
            if ($deal) {
                $deal->status = $request->status;
                $deal->conversationId = $request->has('convesationId') ? $request->conversationId : $deal->conversationId;
                $deal->save();
                $deal->property = Property::where("id", $deal->property_id)->first(['name', 'property_code', 'front_image', 'monthly_rent']);

                event(new DealUpdated($deal));

                return response([
                    'status'    => true,
                    'message'   => 'Deal updated successfully.',
                    'data'      => $deal
                ], 200);
            }

            return response([
                'status'    => false,
                'message'   => 'Deal not found!'
            ], 400);
        }

        return response([
            'status'    => false,
            'message'   => 'User not found!'
        ], 400);
    }

    //closeProperty
    public function closeProperty($code)
    {
        try {
            $property = Property::where("property_code", $code)->first();
            if ($property) {
                $property->is_closed = 1;
                $property->save();

                //notify pending appointment for this property
                $appointments = Meeting::where("property_id", $property->id)->where("meeting_status", "pending")->get()->groupBy("create_id");
                foreach ($appointments as $value) {
                    $a = $value[0];
                    $n = new TenantNotification;
                    $n->tenant_id = $a->created_by_id;
                    $n->type = 'Normal';
                    $n->title = 'Property Booked - ' . $property->name;
                    $n->content = 'Property already booked. You can select another property or book another property';
                    $n->name = "System";

                    $n->save();

                    event(new NotificationSent($n));

                    Meeting::where("create_id", $a->create_id)->delete();
                }

                //notify admin
                $an = new AdminNotification;
                $an->content = 'Property - ' . $property->property_code . '. has been closed by owner.';
                $an->type  = 'Urgent';
                $an->title = 'Property Closed';
                $an->redirect = '/admin/meetings';

                $an->save();

                event(new AdminNotificationSent($an));

                //pay fee to ibo for this property
                $agreement = Agreement::where("property_id", $property->id)->where("landlord_id", JWTAuth::user()->id)->first();
                if ($agreement) {
                    $deal = PropertyDeal::where("property_id", $property->id)->where("status", "accepted")->first();
                    $isearning = IboEarning::where("ibo_id", $agreement->ibo_id)->where("agreement_id", $agreement->id)->where("deal_id", $deal->id ?? 0)->where("property_id", $agreement->property_id)->count();

                    if (!$isearning) {
                        //get list of ibos for payment
                        $ibos = DB::table('payment_splits')
                            ->where("property_id", $agreement->property_id)
                            ->where("paid", 0)
                            ->where("accepted", 1)->get();

                        foreach ($ibos as $ibo) {
                            $percent = $ibo->split_percent ?? 10;
                            $earning = new IboEarning;
                            $earning->ibo_id = $ibo->ibo_id;
                            $earning->deal_id = $deal->id ?? 0;
                            $earning->property_id = $agreement->property_id;
                            $earning->agreement_id = $agreement->id;
                            $earning->amount_percentage = $ibo->split_percent ?? 0;
                            $earning->amount = $agreement->fee_amount * $percent / 100;
                            $earning->date = date('Y-m-d');
                            $earning->type = 'payout_pending';
                            $earning->save();

                            //mark as paid
                            DB::table('payment_splits')
                                ->where("ibo_id", $ibo->ibo_id)
                                ->update(['paid' => 1]);
                        }
                    }
                }

                //points to referer on deal close
                $agreement_ = Agreement::where("property_id", $property->id)->where("landlord_id", JWTAuth::user()->id)->first();
                $user = User::where("id", $agreement_->tenant_id)->first();
                if ($user) {
                    $ruser = User::where("system_userid", $user->referral_code)->first();
                    if ($ruser) {
                        //save point
                        //get settings for points
                        $point_value  = DB::table('settings')->where("setting_key", "point_value")->first()->setting_value;
                        $s_point  = DB::table('settings')->where("setting_key", "referral_deal_closed_point")->first()->setting_value;

                        $spoints = floatval($s_point) * floatval($point_value);

                        DB::table('user_referral_points')->insert([
                            "user_id" => $ruser->id,
                            "role"    => $ruser->role,
                            "title"   => 'You earned 10 points on deal closed for user ' . $user->first,
                            "point_value" => $point_value,
                            "points"      => $s_point,
                            "type"        => "credit",
                            "amount_earned" => $spoints,
                            "for"         => "deal closed",
                            "created_at"  => date("Y-m-d H:i:s"),
                            "updated_at"  => date("Y-m-d H:i:s")
                        ]);

                        try {
                            //add amount to wallet
                            $wallet = Wallet::where("user_id", $ruser->id)->first();
                            $wallet->amount += floatval($spoints);
                            $wallet->credit += floatval($spoints);
                            $wallet->last_credit_transaction = date('Y-m-d H:i:s');
                            $wallet->last_transaction_type = 'credit';
                            $wallet->save();
                        } catch (Exception $e) {
                            //
                        }
                    }
                }

                return response([
                    'status'    => true,
                    'message'   => 'Property closed successfully.',
                    'data'      => $property->only(['front_image', 'name', 'property_code', 'bedrooms', 'bathrooms', 'floors', 'monthly_rent', 'maintenence_charge', 'country_name', 'state_name', 'city_name', 'is_closed'])
                ], 200);
            }
        } catch (Exception $e) {
            return response([
                'status'    => false,
                'message'   => $e->getMessage() . ' > ' . $e->getLine()
            ], 404);
        }
    }

    //openProperty
    public function openProperty($code)
    {
        $property = Property::where("property_code", $code)->first();
        if ($property) {
            $property->is_closed = 0;
            $property->save();

            return response([
                'status'    => true,
                'message'   => 'Property opened successfully.',
                'data'      => $property->only(['front_image', 'name', 'property_code', 'bedrooms', 'bathrooms', 'floors', 'monthly_rent', 'maintenence_charge', 'country_name', 'state_name', 'city_name', 'is_closed'])
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Property not found.'
        ], 404);
    }

    //top_properties
    public function top_properties()
    {
        $pids = UserSavedProperty::where("type", "visited")->pluck("property_id")->toArray();
        $pids = array_count_values($pids);
        arsort($pids);
        $popular = array_slice(array_keys($pids), 0, 5, true);

        $properties = Property::whereIn("id", $popular)->orderByRaw("FIELD(id, " . implode(',', $popular) . ")")->limit(5)->get();
        //loggedin user
        $user = JWTAuth::user();
        if ($user) {
            $properties = $properties->map(function ($p) use ($user) {
                $is_saved = UserSavedProperty::where("property_id", $p->id)->where("user_id", $user->id)->where("type", "saved")->first();
                $is_favorite = UserSavedProperty::where("property_id", $p->id)->where("user_id", $user->id)->where("type", "favorite")->first();

                if ($is_saved) {
                    $p->is_saved = 'yes';
                } else {
                    $p->is_saved = 'no';
                }
                if ($is_favorite) {
                    $p->is_favorite = 'yes';
                } else {
                    $p->is_favorite = 'no';
                }

                return $p;
            });
        }
        return response([
            'status'    => true,
            'message'   => 'Top properties fetched successfully.',
            'data'      => $properties
        ], 200);
    }

    //ibo_properties_by_type
    public function ibo_properties_by_type()
    {
        $user = JWTAuth::user();
        if ($user && $user->role === 'ibo') {
            $posted_properties = Property::where("posted_by", $user->id)->count();
            $rented_properties = Agreement::where("ibo_id", $user->id)->count();
            $visited_properties = Meeting::where("meeting_status", "visited")->where("user_id", $user->id)->count();

            return response([
                'status'    => true,
                'message'   => 'Properties fetched successfully.',
                'data'      => [
                    [
                        "type"  => "posted",
                        "count" => $posted_properties
                    ],
                    [
                        "type"  => "rented",
                        "count" => $rented_properties
                    ],
                    [
                        "type"  => "visited",
                        "count" => $visited_properties
                    ]
                ]
            ]);
        }

        return response([
            'status'    => false,
            'message'   => 'Access not allowed.'
        ], 401);
    }

    //landlord_properties_by_type
    public function landlord_properties_by_type()
    {
        $user = JWTAuth::user();
        if ($user && $user->role === 'landlord') {
            $posted_properties = Property::where("posted_by", $user->id)->count();
            $rented_properties = Agreement::where("landlord_id", $user->id)->count();
            $visited_properties = Meeting::where("meeting_status", "visited")->where("created_by_id", $user->id)->count();

            return response([
                'status'    => true,
                'message'   => 'Properties fetched successfully.',
                'data'      => [
                    [
                        "type"  => "posted",
                        "count" => $posted_properties
                    ],
                    [
                        "type"  => "rented",
                        "count" => $rented_properties
                    ],
                    [
                        "type"  => "visited",
                        "count" => $visited_properties
                    ]
                ]
            ]);
        }

        return response([
            'status'    => false,
            'message'   => 'Access not allowed.'
        ], 401);
    }

    //visited properties
    public function visitedProperties()
    {
        $user = JWTAuth::user();
        if ($user) {
            $pids = Meeting::whereIn("meeting_status", ['visited', 'closed']);
            if ($user->role === 'tenant') {
                $pids->where("created_by_id", $user->id);
            } else if ($user->role === 'ibo') {
                $pids->where("user_id", $user->id);
            }
            $pids = $pids->pluck("property_id")->toArray();

            $properties = Property::select(['id', 'property_code', 'short_description', 'is_closed', 'name', 'front_image', 'monthly_rent', 'bedrooms', 'bathrooms', 'floors', 'security_amount', 'posted_by'])->whereIn("id", $pids)->get()->map(function ($q) {
                $ratings = PropertyRatingAndReview::where("property_id", $q->id)->get();
                $total_rating = 0;
                foreach ($ratings as $r) {
                    $total_rating += $r->rating;
                }
                $q->landlord = User::find($q->posted_by)->first;
                $q->rating = count($ratings) > 0 ? sprintf('%.1f', $total_rating / count($ratings)) : 0.0;
                return $q;
            });

            return response([
                'status'    => true,
                'message'   => 'Visited properties are fetched successfully.',
                'data'      => $properties
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'User is not authorized.'
        ], 401);
    }


    //get property for deal
    public function dealableProperty(Request $request)
    {
        if ($request->has('tenant_id') && $request->has('ibo_id')) {
            $last_appointment = Meeting::where("user_id", $request->ibo_id)
                ->where("created_by_id", $request->tenant_id)
                ->where("meeting_status", "visited")
                ->orderBy("id", "desc")->first();

            if ($last_appointment) {
                $access = ['id', 'property_code', 'name', 'front_image', 'monthly_rent'];
                if (JWTAuth::user()->role === 'ibo') {
                    array_push($access, 'offered_price');
                }

                $property = Property::find($last_appointment->property_id)->only($access);
                return response([
                    'status'    => true,
                    'message'   => 'Dealable property.',
                    'data'      => $property
                ], 200);
            } else {
                return response([
                    'status'    => false,
                    'message'   => 'No found any property.'
                ], 404);
            }
        } else {
            return response([
                'status'    => false,
                'message'   => 'Request is not valid.'
            ], 422);
        }
    }

    //add property and login
    public function storeAndLogin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'  => 'required|string|between:2,100',
            'short_description' => 'required|string|max:255',
            'for'   => 'required|in:rent',
            'type'  => 'required',
            'posting_as'    => 'required',
            'ownership_type'    => 'required',
            'furnished_status'  => 'required',
            'bedrooms'  => 'required',
            'balconies' => 'required',
            'floors'    => 'required',
            'bathrooms' => 'required',
            'super_area'    => 'required',
            'super_area_unit'   => 'required',
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

        //inspections
        if ($request->filled('inspection_days')) {
            $property->inspection_days = $request->inspection_days;
        } else {
            $property->inspection_days = '';
        }
        if ($request->filled('inspection_time_from')) {
            $property->inspection_time_from = $request->inspection_time_from;
        } else {
            $property->inspection_time_from = '';
        }
        if ($request->filled('inspection_time_to')) {
            $property->inspection_time_to = $request->inspection_time_to;
        } else {
            $property->inspection_time_to = '';
        }

        $property->description = $request->description ? $request->description : '';
        $property->advance_amount_period = $request->advance_amount_period ?? '';

        $property->front_image = '';
        $property->posted_by = 0;

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

    //get featured properties
    public function getFeaturedProperties()
    {
        $ids = DB::table('featured_properties')->pluck('property_id')->toArray();
        $properties = Property::where('is_approved', 1)
            ->where('is_closed', 0)->where('is_deleted', 0)
            ->whereIn('id', $ids)->get([
                'id', 'name', 'property_code', 'front_image',
                'posted_by', 'type', 'monthly_rent', 'available_immediately',
                'city_name', 'state_name', 'carpet_area', 'carpet_area_unit'
            ]);

        return response([
            'status' => true,
            'message'   => 'properties fetched successfully.',
            'data'  => $properties
        ], 200);
    }
}
