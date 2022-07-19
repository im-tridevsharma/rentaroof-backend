<?php

namespace App\Http\Controllers\api\user;

use App\Events\AdminNotificationSent;
use App\Events\NotificationSent;
use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\AdminNotification;
use App\Models\Agreement;
use App\Models\IboNotification;
use App\Models\LandlordNotification;
use App\Models\TenantNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use App\Models\Meeting;
use App\Models\Property;
use App\Models\PropertyDeal;
use App\Models\User;
use App\Models\VvcCode;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;

class MeetingController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $user = JWTAuth::user();
        $meetings = Meeting::where("user_id", $user->id)->orWhere("created_by_id", $user->id);
        $meetings = $meetings->orderBy("start_time", "desc")->get();

        if ($meetings) {
            return response([
                'status'    => true,
                'message'   => 'Meetings fetched successfully.',
                'data'      => $meetings->map(function ($m) use ($user) {
                    $p = Property::find($m->property_id);
                    $u = User::find($m->user_id);
                    $vvcode = null;
                    if ($user->role === 'ibo') {
                        $vvcode = VvcCode::where("property_id", $p->id)
                            ->where("tenant_id", $m->created_by_id)
                            ->where("landlord_id", $p->posted_by)
                            ->where("ibo_id", $m->user_id)->first();
                        $m->tenant_vvc = $vvcode->code_for_tenant ?? '';
                        $m->landlord_vvc = $vvcode->code_for_landlord ?? '';
                        $m->is_tenant_vvc_verified = $vvcode->tenant_verified ?? '';
                        $m->is_landlord_vvc_verified = $vvcode->landlord_verified ?? '';
                        $m->property_added_by = $p->ibo ? $p->ibo : $p->posted_by;
                        $vvcode = $vvcode ? $vvcode->vvc_code : null;
                    }
                    if ($user->role === 'tenant') {
                        $vvcode = VvcCode::where("property_id", $p->id)
                            ->where("tenant_id", $m->created_by_id)
                            ->where("landlord_id", $p->posted_by)
                            ->where("ibo_id", $m->user_id)->first();
                        $m->is_tenant_vvc_verified = $vvcode->tenant_verified ?? 0;
                        $vvcode = $vvcode ? $vvcode->code_for_tenant : null;
                    }

                    if ($user->role === 'landlord') :
                        $vvcode = VvcCode::where("property_id", $p->id)
                            ->where("tenant_id", $m->created_by_id)
                            ->where("landlord_id", $p->posted_by)
                            ->where("ibo_id", $m->user_id)->first();
                        $m->is_tenant_vvc_verified = $vvcode->tenant_verified ?? 0;
                        $vvcode = $vvcode ? $vvcode->code_for_landlord : null;
                        $m->ibo_id = $u->id;
                    endif;

                    $m->property_data = $p->name . ' - ' . $p->property_code;
                    $m->vvc = $vvcode;
                    $m->advance_payment = $p->advance_amount_period;

                    $last_deal = PropertyDeal::where('property_id', $p->id)
                        ->orderBy('id', 'desc')->first();
                    $m->final = $last_deal ? $last_deal->offer_price : 0;

                    if ($user->role === 'ibo') :
                        $m->property_monthly_rent = $p->monthly_rent;
                        $m->property_security_amount = $p->security_amount;
                        $m->property_asking_price = $p->offered_price;
                        $m->property_posted_by = $p->posted_by;
                        $m->landlord = User::select(['id', 'first', 'last', 'email', 'mobile'])->where("id", $p->posted_by)->first();
                    endif;

                    $m->front_image = $p->front_image;
                    $m->ibo = $u ? $u->first . ' ' . $u->last : '-';
                    $a = Agreement::where("property_id", $m->property_id)->where("ibo_id", $m->user_id)->where("tenant_id", $m->created_by_id)->where("landlord_id", $p->posted_by)->first();
                    $m->agreement = $a;
                    return $m;
                })
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Something went wrong.'
        ], 500);
    }

    //vvc status
    public function update_vvc(Request $request)
    {
        $vvc_code = $request->id;
        if ($vvc_code && $request->vvc && $request->type && in_array($request->type, ['tenant', 'landlord'])) {
            $is = VvcCode::where("vvc_code", $vvc_code)
                ->where($request->type === 'tenant' ? 'code_for_tenant' : 'code_for_landlord', $request->vvc)->first();
            if ($is) {
                $data = $request->type === 'tenant' ? ["tenant_verified" => 1] : ["landlord_verified" => 1];
                VvcCode::where("vvc_code", $vvc_code)->update($data);
                return response([
                    'status'    => true,
                    'message'   => $request->type === 'tenant' ? 'Tenant verified successfully.' : 'Landlord verified successfully.'
                ]);
            } else {
                return response([
                    'status'    => false,
                    'message'   => 'Vvc code is invalid.'
                ], 200);
            }
        } else {
            return response([
                "status"    => false,
                "message"   => 'Request is not valid.'
            ], 422);
        }
    }

    //meeting_count_for_mobile
    public function meeting_count_for_mobile()
    {
        $user = JWTAuth::user();
        $meetings = Meeting::where("user_id", $user->id)->orWhere("created_by_id", $user->id)->orderBy("id", "desc");
        if ($user && $user->role === 'tenant') {
            $meetings->where("meeting_status", '!=', 'pending');
            $meetings->where("user_id", '!=', 0);
        }

        $meetings = $meetings->get();
        $today = 0;
        $upcoming = 0;
        $history = 0;

        foreach ($meetings as $m) {
            if (date('Y-m-d') === date('Y-m-d', strtotime($m->start_time))) {
                $today++;
            }
            if (date('Y-m-d') < date('Y-m-d', strtotime($m->start_time))) {
                $upcoming++;
            }
            if ($m->meeting_history !== '' && $m->meeting_history !== '[]') {
                $history++;
            }
        }

        if ($meetings) {
            return response([
                'status'    => true,
                'message'   => 'Meetings count fetched successfully.',
                'data'      => [
                    [
                        "name"  => "today",
                        "value" => $today
                    ],
                    [
                        "name"  => "upcoming",
                        "value" => $upcoming
                    ],
                    [
                        "name"  => "history",
                        "value" => $history
                    ]
                ]
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Something went wrong.'
        ], 500);
    }

    //meeting_count_for_mobile
    public function meeting_count_for_mobile_all()
    {
        $user = JWTAuth::user();
        $meetings = Meeting::where("user_id", $user->id)->orWhere("created_by_id", $user->id)->orderBy("id", "desc");
        if ($user && $user->role === 'tenant') {
            $meetings->where("meeting_status", '!=', 'pending');
            $meetings->where("user_id", '!=', 0);
        }

        $meetings = $meetings->get();
        $today = [];
        $upcoming = [];
        $history = [];

        foreach ($meetings as $m) {
            $p = Property::find($m->property_id);
            $vvcode = null;
            $u = User::find($m->user_id);

            if ($user->role === 'ibo') {
                $vvcode = VvcCode::where("property_id", $p->id)
                    ->where("tenant_id", $m->created_by_id)
                    ->where("landlord_id", $p->posted_by)
                    ->where("ibo_id", $m->user_id)->first();
                $m->tenant_vvc = $vvcode->code_for_tenant ?? '';
                $m->landlord_vvc = $vvcode->code_for_landlord ?? '';
                $m->is_tenant_vvc_verified = $vvcode->tenant_verified ?? 0;
                $m->is_landlord_vvc_verified = $vvcode->landlord_verified ?? 0;
                $vvcode = $vvcode ? $vvcode->vvc_code : null;
                $m->property_added_by = $p->ibo ? $p->ibo : $p->posted_by;
            }
            if ($user->role === 'tenant') {
                $vvcode = VvcCode::where("property_id", $p->id)
                    ->where("tenant_id", $m->created_by_id)
                    ->where("landlord_id", $p->posted_by)
                    ->where("ibo_id", $m->user_id)->first();
                $m->is_tenant_vvc_verified = $vvcode->tenant_verified ?? 0;
                $vvcode = $vvcode ? $vvcode->code_for_tenant : null;
            }
            if ($user->role === 'landlord') :
                $m->ibo_id = $u->id;
                $vvcode = VvcCode::where("property_id", $p->id)
                    ->where("tenant_id", $m->created_by_id)
                    ->where("landlord_id", $p->posted_by)
                    ->where("ibo_id", $m->user_id)->first();
                $m->is_tenant_vvc_verified = $vvcode->tenant_verified ?? 0;
                $vvcode = $vvcode ? $vvcode->code_for_landlord : null;
            endif;

            $m->property_data = $p->name . ' - ' . $p->property_code;
            $m->vvc = $vvcode;
            $m->advance_payment = $p->advance_amount_period;
            $last_deal = PropertyDeal::where('property_id', $p->id)
                ->orderBy('id', 'desc')->first();
            $m->final = $last_deal ? $last_deal->offer_price : 0;
            if ($user->role === 'ibo') :
                $m->property_monthly_rent = $p->monthly_rent;
                $m->property_security_amount = $p->security_amount;
                $m->property_asking_price = $p->offered_price;
                $m->property_posted_by = $p->posted_by;
                $m->landlord = User::select(['id', 'first', 'last', 'email', 'mobile'])->where("id", $p->posted_by)->first();
            endif;
            $m->front_image = $p->front_image;
            $m->ibo = $u ? $u->first . ' ' . $u->last : '-';
            $a = Agreement::where("property_id", $m->property_id)->where("ibo_id", $m->user_id)->where("tenant_id", $m->created_by_id)->where("landlord_id", $p->posted_by)->first();
            $m->agreement = $a;

            if (date('Y-m-d') === date('Y-m-d', strtotime($m->start_time))) {
                array_push($today, $m);
            }
            if (date('Y-m-d') < date('Y-m-d', strtotime($m->start_time))) {
                array_push($upcoming, $m);
            }
            if ($m->meeting_history !== '' && $m->meeting_history !== '[]') {
                array_push($history, $m);
            }
        }

        if ($meetings) {
            return response([
                'status'    => true,
                'message'   => 'Meetings fetched successfully.',
                'data'      => [
                    [
                        "name"  => "today",
                        "value" => $today
                    ],
                    [
                        "name"  => "upcoming",
                        "value" => $upcoming
                    ],
                    [
                        "name"  => "history",
                        "value" => $history
                    ]
                ]
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Something went wrong.'
        ], 500);
    }

    /**get meetings of landlord belonged property */
    public function landlord_meetings($id)
    {
        $landlord = User::find($id);
        if ($landlord) {
            //fetch properties of landlord
            $properties = Property::where("posted_by", $landlord->id)->pluck('id')->toArray();
            $meetings = [];
            $allm = Meeting::whereIn("property_id", $properties)->orderBy("start_time", "desc")->get();
            foreach ($allm as $m) {
                $p = Property::find($m->property_id);
                $u = User::find($m->user_id);

                $vvcode = VvcCode::where("property_id", $p->id)
                    ->where("landlord_id", $p->posted_by)
                    ->where("tenant_id", $m->created_by_id)
                    ->where("ibo_id", $m->user_id)->first();
                $m->is_landlord_vvc_verified = $vvcode->landlord_verified ?? 0;
                $vvcode = $vvcode ? $vvcode->code_for_landlord : null;

                $m->property_data = $p->name . ' - ' . $p->property_code;
                $m->property_monthly_rent = $p->monthly_rent;
                $m->property_security_amount = $p->security_amount;
                $m->property_posted_by = $p->posted_by;
                $m->advance_payment = $p->advance_amount_period;
                $last_deal = PropertyDeal::where('property_id', $p->id)
                    ->orderBy('id', 'desc')->first();
                $m->final = $last_deal ? $last_deal->offer_price : 0;
                $m->front_image = $p->front_image;
                $m->ibo = $u ? $u->first . ' ' . $u->last : '';
                $m->ibo_id = $u->id ?? 0;
                $m->vvc = $vvcode;
                $m->landlord = User::select(['first', 'last', 'email', 'mobile'])->where("id", $p->posted_by)->first();
                $a = Agreement::where("property_id", $m->property_id)->where("ibo_id", $m->user_id)->where("tenant_id", $m->created_by_id)->where("landlord_id", $p->posted_by)->first();
                $m->agreement = $a;
                array_push($meetings, $m);
            }
            return response([
                'status'    => true,
                'message'   => 'Meetings fetched successfully.',
                'data'      => $meetings
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Landlord not found!'
        ], 404);
    }

    /**get meetings of landlord belonged property for mobile */
    public function landlord_meetings_mobile($id)
    {
        $landlord = User::find($id);
        if ($landlord) {
            //fetch properties of landlord
            $properties = Property::where("posted_by", $landlord->id)->pluck('id')->toArray();
            $today = 0;
            $upcoming = 0;
            $history = 0;
            $allm = Meeting::whereIn("property_id", $properties)->orderBy("start_time", "desc")->get();

            foreach ($allm as $m) {
                if (date('Y-m-d') === date('Y-m-d', strtotime($m->start_time))) {
                    $today++;
                }
                if (date('Y-m-d') < date('Y-m-d', strtotime($m->start_time))) {
                    $upcoming++;
                }
                if ($m->meeting_history !== '' && $m->meeting_history !== '[]') {
                    $history++;
                }
            }
            return response([
                'status'    => true,
                'message'   => 'Meetings count fetched successfully.',
                'data'      => [
                    [
                        "name"  => "today",
                        "value" => $today
                    ],
                    [
                        "name"  => "upcoming",
                        "value" => $upcoming
                    ],
                    [
                        "name"  => "history",
                        "value" => $history
                    ]
                ]
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Landlord not found!'
        ], 404);
    }


    /**get meetings of landlord belonged property for mobile */
    public function landlord_meetings_mobile_all($id)
    {
        $landlord = User::find($id);
        if ($landlord) {
            //fetch properties of landlord
            $properties = Property::where("posted_by", $landlord->id)->pluck()->toArray();
            $today = [];
            $upcoming = [];
            $history = [];
            $allm = Meeting::whereIn("property_id", $properties)->orderBy("start_time", "desc")->get();

            foreach ($allm as $m) {
                $user = $landlord;
                $u = User::find($m->user_id);
                $vvcode = null;
                $vvcode = VvcCode::where("property_id", $p->id)
                    ->where("landlord_id", $p->posted_by)
                    ->where("tenant_id", $m->created_by_id)
                    ->where("ibo_id", $m->user_id)->first();
                $m->is_landlord_vvc_verified = $vvcode->landlord_verified ?? 0;
                $vvcode = $vvcode ? $vvcode->code_for_landlord : null;
                $m->property_data = $p->name . ' - ' . $p->property_code;
                $m->vvc = $vvcode;
                if ($user->role === 'landlord') :
                    $m->ibo_id = $u->id ?? 0;
                endif;
                $m->front_image = $p->front_image;
                $m->ibo = $u ? $u->first . ' ' . $u->last : '-';
                $last_deal = PropertyDeal::where('property_id', $p->id)
                    ->orderBy('id', 'desc')->first();
                $m->final = $last_deal ? $last_deal->offer_price : 0;
                $a = Agreement::where("property_id", $m->property_id)->where("ibo_id", $m->user_id)->where("tenant_id", $m->created_by_id)->where("landlord_id", $p->posted_by)->first();
                $m->agreement = $a;

                if (date('Y-m-d') === date('Y-m-d', strtotime($m->start_time))) {
                    array_push($today, $m);
                }
                if (date('Y-m-d') < date('Y-m-d', strtotime($m->start_time))) {
                    array_push($upcoming, $m);
                }
                if ($m->meeting_history !== '' && $m->meeting_history !== '[]') {
                    array_push($history, $m);
                }
            }
            return response([
                'status'    => true,
                'message'   => 'Meetings fetched successfully.',
                'data'      => [
                    [
                        "name"  => "today",
                        "value" => $today
                    ],
                    [
                        "name"  => "upcoming",
                        "value" => $upcoming
                    ],
                    [
                        "name"  => "history",
                        "value" => $history
                    ]
                ]
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Landlord not found!'
        ], 404);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $user = JWTAuth::user();
        $validator = Validator::make($request->all(), [
            'title'     => 'required|string|max:200',
            'user_id'   => 'required',
            'property_id'   => 'required',
            'start_time'   => 'required|string',
            'end_time_expected'   => 'required|string',
        ]);

        if ($validator->fails()) {
            return response([
                'status'    => false,
                'message'   => 'Some error occured',
                'error'     => $validator->errors()
            ], 404);
        }

        $user = User::find($request->user_id);

        //save information
        $meeting = new Meeting;

        $meeting->create_id = 'ID-' . time();
        $meeting->title = $request->title;
        $meeting->description = isset($request->description) ? $request->description : '';
        $meeting->user_id = $request->user_id;
        $meeting->user_role = $user ? $user->role : '';
        $meeting->property_id = $request->property_id;
        $meeting->name = $user ? $user->first . ' ' . $user->last : $request->name;
        $meeting->contact = $user ? $user->mobile : (!empty($request->contact) ? $request->contact : '');
        $meeting->email = $user ? $user->email : (!empty($request->email) ? $request->email : '');
        $meeting->start_time = !empty($request->start_time) ? date("Y-m-d H:i:s", strtotime($request->start_time)) : NULL;
        $meeting->end_time_expected = !empty($request->end_time_expected) ? date("Y-m-d H:i:s", strtotime($request->end_time_expected)) : NULL;
        $meeting->created_by_id = $user->id;
        $meeting->created_by_name = $user->first . ' ' . $user->last;
        $meeting->created_by_role = $user->role;
        //create history for meeting
        $meeting_history = [
            [
                "id"      => 1,
                "action"  => "Created",
                "action_by" => $user->id,
                "time"    => date("Y-m-d H:i:s"),
                "name" => $user->first . ' ' . $user->last,
                "message" => $user->first . "(" . $user->role . ")" . " has created this meeting."
            ]
        ];
        $meeting->meeting_history = json_encode($meeting_history);

        if ($meeting->save()) {
            return response([
                'status'    => true,
                'message'   => 'New Meeting saved successfully.',
                'data'      => $meeting
            ]);
        }

        return response([
            'status'    => false,
            'message'   => 'Something went wrong.'
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
        $user = JWTAuth::user();
        $meeting = Meeting::where("id", $id)->first();

        if ($meeting) {
            return response([
                'status'    => true,
                'message'   => 'Meeting fetched successfully.',
                'data'      => $meeting
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Meeting not found.'
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
        $user = JWTAuth::user();
        $validator = Validator::make($request->all(), [
            'title'     => 'required|string|max:200',
            'user_id'   => 'required',
            'property_id'   => 'required',
            'start_time'   => 'required|string',
            'end_time_expected'   => 'required|string',
        ]);

        if ($validator->fails()) {
            return response([
                'status'    => false,
                'message'   => 'Some error occured',
                'error'     => $validator->errors()
            ], 404);
        }

        //save information
        $meeting = Meeting::where("created_by_id", $user->id)->find($id);
        if ($meeting) {
            $user = User::find($request->user_id);
            $meeting->title = $request->title;
            $meeting->description = isset($request->description) ? $request->description : '';
            $meeting->user_id = $request->user_id;
            $meeting->user_role = $user ? $user->role : '';
            $meeting->property_id = $request->property_id;
            $meeting->name = $user ? $user->first . ' ' . $user->last : $request->name;
            $meeting->contact = $user ? $user->mobile : (!empty($request->contact) ? $request->contact : $meeting->contact);
            $meeting->email = $user ? $user->email : (!empty($request->email) ? $request->email : $meeting->email);
            $meeting->start_time = !empty($request->start_time) ? date("Y-m-d H:i:s", strtotime($request->start_time)) : NULL;
            $meeting->end_time_expected = !empty($request->end_time_expected) ? date("Y-m-d H:i:s", strtotime($request->end_time_expected)) : NULL;
            $meeting->created_by_id = $user->id;
            $meeting->created_by_name = $user->first . ' ' . $user->last;
            $meeting->created_by_role = $user->role;
            //create history for meeting
            $meeting_history = json_decode($meeting->meeting_history);
            $mhid = count($meeting_history);
            array_push($meeting_history, [
                "id"      => $mhid + 1,
                "action"  => "Updated",
                "action_by" => $user->id,
                "name" => $user->first . ' ' . $user->last,
                "message" => $user->first . "(" . $user->role . ")" . " has updated this meeting."
            ]);
            $meeting->meeting_history = json_encode($meeting_history);

            if ($meeting->save()) {
                return response([
                    'status'    => true,
                    'message'   => 'Meeting updated successfully.',
                    'data'      => $meeting
                ]);
            }

            return response([
                'status'    => false,
                'message'   => 'Something went wrong.'
            ], 500);
        }

        return response([
            'status'    => false,
            'message'   => 'Meeting not found.'
        ], 404);
    }

    /**
     * Update status of meeting
     */

    public function update_status(Request $request, $id)
    {
        $user = JWTAuth::user();
        $ibo_id = null;
        $validator = Validator::make($request->input(), [
            'status'    => 'required|string|in:pending,cancelled,approved,visited,closed,on the way'
        ]);

        if ($validator->fails()) {
            return response([
                'status'    => false,
                'message'   => 'Some error occured.',
                'error'     => $validator->errors()
            ], 404);
        }

        $meeting = Meeting::where("id", $id)->first();

        if ($meeting) {

            if ($user->role === 'landlord') {
                return $this->update_landlord_meeting_status($request, $meeting);
            }

            $meeting->meeting_status = $request->status;
            $ibo_id = $meeting->user_id;

            if ($request->status === 'closed') {
                $meeting->end_time = date("Y-m-d H:i:s");
            }
            if ($request->status === 'visited') {
                $meeting->end_time_expected = date("Y-m-d H:i:s");
            }

            $meeting_history = json_decode($meeting->meeting_history);
            array_push($meeting_history, [
                "id"      => count($meeting_history) + 1,
                "action"  => "Status Updated",
                "action_by" => $user->id,
                "time"    => date("Y-m-d H:i:s"),
                "name" => $user->first . ' ' . $user->last,
                "message" => $user->first . "(" . $user->role . ")" . " has updated meeting's status to > " . $request->status
            ]);

            $meeting->meeting_history = json_encode($meeting_history);

            if ($meeting->save()) {

                if ($request->status === 'approved') {
                    Meeting::where("user_id", "!=", $user->id)->where("create_id", $meeting->create_id)->delete();

                    //check is there any slipt
                    $is_split = DB::table('payment_splits')->where("property_id", $meeting->property_id)->count();
                    if ($is_split === 0) {
                        DB::table('payment_splits')->insert([
                            'property_id'   => $meeting->property_id,
                            'ibo_id'        => $meeting->user_id,
                            'accepted'      => 1,
                            'paid'          => 0
                        ]);
                    }

                    //mark accepted in payment split
                    DB::table('payment_splits')->where("property_id", $meeting->property_id)
                        ->where("ibo_id", JWTAuth::user()->id)->update([
                            "accepted" => 1
                        ]);
                }

                if ($request->status === 'cancelled') {
                    $count = Meeting::where("create_id", $meeting->create_id)->count();
                    if ($count > 1) {
                        Meeting::where("user_id", $user->id)->where("create_id", $meeting->create_id)->delete();
                    } else {
                        //if it was posted by an agent
                        $property = Property::find($meeting->property_id);
                        if ($property && $property->ibo === $user->id) {

                            //check is there any slipt
                            $is_split = DB::table('payment_splits')->where("property_id", $property->id)->count();
                            if ($is_split === 0) {
                                DB::table('payment_splits')->insert([
                                    'property_id'   => $meeting->property_id,
                                    'ibo_id'        => $meeting->user_id,
                                    'accepted'      => 1,
                                    'paid'          => 0,
                                    'split_percent' => 20
                                ]);
                            }

                            //assign to other nearby agents except he
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
                                ->where("user_id", "!=", NULL)
                                ->where("user_id", '!=', $user->id)
                                ->get();

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

                                $user = JWTAuth::user();

                                //notify landlord meeting is scheduled
                                $landlord_notify = new LandlordNotification;
                                $landlord_notify->landlord_id = $property->posted_by;
                                $landlord_notify->type = 'Urgent';
                                $landlord_notify->title = 'Appointment assigned!';
                                $landlord_notify->content = 'Assigned to new agents for property - ' . $property->property_code . '.';
                                $landlord_notify->name = 'Rent A Roof';
                                $landlord_notify->redirect = '/landlord/appointment';

                                $landlord_notify->save();
                                event(new NotificationSent($landlord_notify));

                                Meeting::where("user_id", $user->id)->where("create_id", $meeting->create_id)->delete();
                            } else {
                                Meeting::where("create_id", $meeting->create_id)->update(["user_id" => 0]);
                                //notify admin
                                $an = new AdminNotification;
                                $an->content = 'No agents found nearby for scheduled appointment of property - ' . $property->property_code . '.';
                                $an->type  = 'Urgent';
                                $an->title = 'No Agents are available!';
                                $an->redirect = '/admin/meetings';
                                $an->save();

                                event(new AdminNotificationSent($an));
                            }
                        } else {
                            Meeting::where("create_id", $meeting->create_id)->update(["user_id" => 0]);
                        }
                    }
                }

                if ($request->status === 'on the way') {
                    $user = JWTAuth::user();
                    $vproperty = Property::find($meeting->property_id);

                    //delete old vvc
                    VvcCode::where("property_id", $meeting->property_id)
                        ->where("ibo_id", $meeting->user_id)
                        ->where("tenant_id", $meeting->created_by_id)
                        ->where("landlord_id", $vproperty->posted_by)->delete();

                    //generate vvc
                    $vvc = new VvcCode;
                    $vvc->property_id = $meeting->property_id;
                    $vvc->ibo_id = $meeting->user_id;
                    $vvc->tenant_id = $meeting->created_by_id;
                    $vvc->landlord_id = $vproperty->posted_by;
                    $vvc->vvc_code = 'VVC-' . time();
                    $vvc->code_for_tenant = rand(111111, 999999);
                    $vvc->code_for_landlord = rand(111111, 999999);

                    $vvc->save();

                    //notify user
                    $unotify = new TenantNotification;
                    $unotify->tenant_id = $meeting->created_by_id;
                    $unotify->type = 'Urgent';
                    $unotify->title = 'VVC has been generated for property visit.';
                    $unotify->content = 'VVC for ' . $vproperty->name . '(' . $vproperty->property_code . ') is ' . $vvc->code_for_tenant;
                    $unotify->name = 'Rent A Roof';
                    $unotify->save();
                    event(new NotificationSent($unotify));

                    //notify landlord
                    $lnotify = new LandlordNotification;
                    $lnotify->landlord_id = $vproperty->posted_by;
                    $lnotify->type = 'Urgent';
                    $lnotify->title = 'VVC has been generated for property visit.';
                    $lnotify->content = 'VVC for ' . $vproperty->name . '(' . $vproperty->property_code . ') is ' . $vvc->code_for_landlord;
                    $lnotify->name = 'Rent A Roof';
                    $lnotify->save();
                    event(new NotificationSent($lnotify));

                    //notify ibo
                    $inotify = new IboNotification;
                    $inotify->ibo_id = $meeting->user_id;
                    $inotify->type = 'Urgent';
                    $inotify->title = 'VVC has been generated for property visit.';
                    $inotify->content = 'VVC for ' . $vproperty->name . '(' . $vproperty->property_code . ') is ' . $vvc->vvc_code;
                    $inotify->name = 'Rent A Roof';
                    $inotify->save();
                    event(new NotificationSent($inotify));
                }

                $user = JWTAuth::user();
                //notifications
                $property = Property::find($meeting->property_id);
                $ibo = User::find($ibo_id);

                if ($property && $property->ibo !== $user->id) {
                    //notify admin
                    $an = new AdminNotification;
                    $an->content = $meeting->meeting_status === 'approved' ? 'Meeting request for property - ' . $property->property_code . ' has been accepted by Agent - ' . $ibo->first . ' ' . $ibo->last : 'Meeting status updated';
                    $an->type  = 'Urgent';
                    $an->title = $meeting->meeting_status === 'approved' ? 'Meeting Accepted.' : 'Meeting Status Changed to ' . $meeting->meeting_status;

                    $an->redirect = '/admin/meetings';
                    $an->save();
                    event(new AdminNotificationSent($an));

                    //notify landlord meeting is scheduled
                    $landlord_notify = new LandlordNotification;
                    $landlord_notify->landlord_id = $property->posted_by;
                    $landlord_notify->type = 'Urgent';
                    $landlord_notify->title = $meeting->meeting_status === 'approved' ? 'Meeting Accepted.' : 'Meeting Status Changed to ' . $meeting->meeting_status;
                    $landlord_notify->content = $meeting->meeting_status === 'approved' ? 'Meeting request for property - ' . $property->property_code . ' has been accepted by Agent - ' . $ibo->first . ' ' . $ibo->last : 'Meeting status updated';
                    $landlord_notify->name = 'Rent A Roof';
                    $landlord_notify->redirect = '/landlord/appointment';

                    $landlord_notify->save();

                    event(new NotificationSent($landlord_notify));
                }

                return response([
                    'status'    => true,
                    'message'   => 'Meeting updated successfully.',
                    'data'      => $meeting
                ], 200);
            }

            return response([
                'status'    => false,
                'message'   => 'Something went wrong.'
            ], 500);
        }

        return response([
            'status'    => false,
            'message'   => 'Meeting not found.'
        ], 404);
    }

    public function update_landlord_meeting_status(Request $request, $meeting)
    {
        if ($meeting) {
            $meeting->landlord_status = $request->status;
            $meeting->save();

            $property = Property::find($meeting->property_id);

            //notify ibo
            $inotify = new IboNotification;
            $inotify->ibo_id = $meeting->user_id;
            $inotify->type = 'Urgent';
            $inotify->title = 'Landlord updated appintment status!';
            $inotify->content = 'Landlord of property ' . $property->name . ' updated appointment status to ' . $request->status;
            $inotify->name = 'Rent A Roof';
            $inotify->save();
            event(new NotificationSent($inotify));

            //notify admin
            $an = new AdminNotification;
            $an->content = 'Landlord of property ' . $property->name . ' updated meeting status to ' . $request->status;
            $an->type  = 'Urgent';
            $an->title = 'Meeting Status Updated';
            $an->redirect = '/admin/meetings';
            $an->save();
            event(new AdminNotificationSent($an));

            return response([
                'status'    => true,
                'message'   => 'Meeting status updated!'
            ], 200);
        } else {
            return response([
                'status'    => false,
                'message'   => 'Meeting not found.'
            ], 404);
        }
    }

    public function reschedule(Request $request, $id)
    {
        $user = JWTAuth::user();
        $validator = Validator::make($request->input(), [
            'date'    => 'required',
            'time'    => 'required'
        ]);

        if ($validator->fails()) {
            return response([
                'status'    => false,
                'message'   => 'Some error occured.',
                'error'     => $validator->errors()
            ], 404);
        }

        $meeting = Meeting::where("id", $id)->first();

        if ($meeting) {
            $meeting->meeting_status = 'scheduled';
            $meeting->start_time = date("Y-m-d H:i:s", strtotime($request->date . ' ' . $request->time));
            $meeting_history = json_decode($meeting->meeting_history);
            $meeting->rescheduled = 1;
            array_push($meeting_history, [
                "id"      => count($meeting_history) + 1,
                "action"  => "Schedule Updated",
                "time"    => date("Y-m-d H:i:s"),
                "action_by" => $user->id,
                "name" => $user->first . ' ' . $user->last,
                "message" => $user->first . "(" . $user->role . ")" . " has updated meeting's schedule time to > " . date("Y-m-d H:i:s", strtotime($request->date . ' ' . $request->time))
            ]);
            $meeting->meeting_history = json_encode($meeting_history);

            if ($meeting->save()) {
                //notifications
                $property = Property::find($meeting->property_id);
                $ibo = User::find($meeting->user_id);

                if ($user->role === 'ibo') {
                    //notify user meeting is scheduled
                    $user_notify = new TenantNotification;
                    $user_notify->tenant_id = $meeting->created_by_id;
                    $user_notify->type = 'Urgent';
                    $user_notify->title = 'Appointment Rescheduled.';
                    $user_notify->content = 'Appointment for property - ' . $property->property_code . ' has been rescheduled by IBO - ' . $ibo->first . ' ' . $ibo->last . ' at ' . date('d-m-Y H:i', strtotime($meeting->start_time));
                    $user_notify->name = 'Rent A Roof';

                    $user_notify->save();

                    event(new NotificationSent($user_notify));
                } else if ($user->role === 'tenant') {
                    //notify user meeting is scheduled
                    $ibo_notify = new IboNotification;
                    $ibo_notify->ibo_id = $meeting->user_id;
                    $ibo_notify->type = 'Urgent';
                    $ibo_notify->title = 'Appointment Rescheduled.';
                    $ibo_notify->content = 'Appointment for property - ' . $property->property_code . ' has been rescheduled by - ' . $user->first . ' ' . $user->last . ' at ' . date('d-m-Y H:i', strtotime($meeting->start_time));
                    $ibo_notify->name = 'Rent A Roof';

                    $ibo_notify->save();

                    event(new NotificationSent($ibo_notify));
                }

                //notify admin
                $an = new AdminNotification;
                $an->content = $user->role === 'ibo' ? 'Meeting for property - ' . $property->property_code . ' has been rescheduled by IBO - ' . $ibo->first . ' ' . $ibo->last . ' at ' . date('d-m-Y H:i', strtotime($meeting->start_time)) : 'Appointment for property - ' . $property->property_code . ' has been rescheduled by User - ' . $user->first . ' ' . $user->last . ' at ' . date('d-m-Y H:i', strtotime($meeting->start_time));
                $an->type  = 'Urgent';
                $an->title = 'Meeting Rescheduled';
                $an->redirect = '/admin/meetings';
                $an->save();
                event(new AdminNotificationSent($an));

                //notify landlord meeting is scheduled
                $landlord_notify = new LandlordNotification;
                $landlord_notify->landlord_id = $property->posted_by;
                $landlord_notify->type = 'Normal';
                $landlord_notify->title = 'Appointment Rescheduled';
                $landlord_notify->content = $user->role === 'ibo' ? 'Appointment for property - ' . $property->property_code . ' has been rescheduled by IBO - ' . $ibo->first . ' ' . $ibo->last . ' at ' . date('d-m-Y H:i', strtotime($meeting->start_time)) : 'Appointment for property - ' . $property->property_code . ' has been rescheduled by User - ' . $user->first . ' ' . $user->last . ' at ' . date('d-m-Y H:i', strtotime($meeting->start_time));
                $landlord_notify->name = 'Rent A Roof';
                $landlord_notify->redirect = '/landlord/appointment';

                $landlord_notify->save();

                event(new NotificationSent($landlord_notify));

                return response([
                    'status'    => true,
                    'message'   => 'Meeting scheduled successfully.',
                    'data'      => $meeting
                ], 200);
            }

            return response([
                'status'    => false,
                'message'   => 'Something went wrong.'
            ], 500);
        }

        return response([
            'status'    => false,
            'message'   => 'Meeting not found.'
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
        $user = JWTAuth::user();
        $meeting = Meeting::where("created_by_id", $user->id)->where("id", $id)->first();

        if ($meeting) {
            $meeting->delete();
            return response([
                'status'    => true,
                'message'   => 'Meeting deleted successfully.',
                'data'      => $meeting
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Meeting not found.'
        ], 404);
    }
}
