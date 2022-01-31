<?php

namespace App\Http\Controllers\api\user;

use App\Events\AdminNotificationSent;
use App\Events\NotificationSent;
use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\Agreement;
use App\Models\IboNotification;
use App\Models\LandlordNotification;
use App\Models\TenantNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use App\Models\Meeting;
use App\Models\Property;
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
        $meetings = Meeting::where("user_id", $user->id)->orWhere("created_by_id", $user->id)->orderBy("id", "desc");
        if ($user && $user->role === 'tenant') {
            $meetings->where("meeting_status", '!=', 'pending');
            $meetings->where("user_id", '!=', 0);
        }
        $meetings = $meetings->get();

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

                    $m->property_data = $p->name . ' - ' . $p->property_code;
                    $m->vvc = $vvcode;

                    if ($user->role === 'ibo') :
                        $m->property_monthly_rent = $p->monthly_rent;
                        $m->property_security_amount = $p->security_amount;
                        $m->property_asking_price = $p->offered_price;
                        $m->property_posted_by = $p->posted_by;
                        $m->landlord = User::select(['id', 'first', 'last', 'email', 'mobile'])->where("id", $p->posted_by)->first();
                    endif;
                    if ($user->role === 'landlord') :
                        $m->ibo_id = $u->id;
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
        $vvc_code = $request->vvc;
        if ($request->type && in_array($request->type, ['tenant', 'landlord'])) {
            $data = $request->type === 'tenant' ? ["tenant_verified" => $request->status] : ["landlord_verified" => $request->status];
            VvcCode::where("vvc_code", $vvc_code)->update($data);

            return response([
                'status'    => true,
                'message'   => 'Status changed successfully.'
            ]);
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
                $m->is_tenant_vvc_verified = $vvcode->tenant_verified ?? 0;
                $m->is_landlord_vvc_verified = $vvcode->landlord_verified ?? 0;
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

            $m->property_data = $p->name . ' - ' . $p->property_code;
            $m->vvc = $vvcode;

            if ($user->role === 'ibo') :
                $m->property_monthly_rent = $p->monthly_rent;
                $m->property_security_amount = $p->security_amount;
                $m->property_asking_price = $p->offered_price;
                $m->property_posted_by = $p->posted_by;
                $m->landlord = User::select(['id', 'first', 'last', 'email', 'mobile'])->where("id", $p->posted_by)->first();
            endif;
            if ($user->role === 'landlord') :
                $m->ibo_id = $u->id;
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
            $properties = Property::where("posted_by", $landlord->id)->get();
            $meetings = [];
            foreach ($properties as $p) {
                if (Meeting::where("property_id", $p->id)->count() > 0) {
                    $allm = Meeting::where("property_id", $p->id)->get();
                    foreach ($allm as $m) {
                        $p = Property::find($m->property_id);
                        $u = User::find($m->user_id);

                        $vvcode = VvcCode::where("property_id", $p->id)
                            ->where("landlord_id", $p->posted_by)
                            ->where("tenant_id", $m->created_by_id)
                            ->where("ibo_id", $m->user_id)->first();
                        $m->is_landlord_vvc_verified = $vvcode->landlord_verified ?? 0;
                        $vvcode = $vvcode ? $vvcode->code_for_tenant : null;

                        $m->property_data = $p->name . ' - ' . $p->property_code;
                        $m->property_monthly_rent = $p->monthly_rent;
                        $m->property_security_amount = $p->security_amount;
                        $m->property_posted_by = $p->posted_by;
                        $m->front_image = $p->front_image;
                        $m->ibo = $u ? $u->first . ' ' . $u->last : '';
                        $m->vvc = $vvcode;
                        $m->landlord = User::select(['first', 'last', 'email', 'mobile'])->where("id", $p->posted_by)->first();
                        $a = Agreement::where("property_id", $m->property_id)->where("ibo_id", $m->user_id)->where("tenant_id", $m->created_by_id)->where("landlord_id", $p->posted_by)->first();
                        $m->agreement = $a;
                        array_push($meetings, $m);
                    }
                }
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
            $properties = Property::where("posted_by", $landlord->id)->get();
            $today = 0;
            $upcoming = 0;
            $history = 0;
            foreach ($properties as $p) {
                if (Meeting::where("property_id", $p->id)->count() > 0) {
                    $allm = Meeting::where("property_id", $p->id)->get();

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
            $properties = Property::where("posted_by", $landlord->id)->get();
            $today = [];
            $upcoming = [];
            $history = [];
            foreach ($properties as $p) {
                if (Meeting::where("property_id", $p->id)->count() > 0) {
                    $allm = Meeting::where("property_id", $p->id)->get();

                    foreach ($allm as $m) {
                        $user = $landlord;
                        $u = User::find($m->user_id);
                        $vvcode = null;
                        $vvcode = VvcCode::where("property_id", $p->id)
                            ->where("landlord_id", $p->posted_by)
                            ->where("tenant_id", $m->created_by_id)
                            ->where("ibo_id", $m->user_id)->first();
                        $m->is_landlord_vvc_verified = $vvcode->landlord_verified ?? 0;
                        $vvcode = $vvcode ? $vvcode->code_for_tenant : null;
                        $m->property_data = $p->name . ' - ' . $p->property_code;
                        $m->vvc = $vvcode;
                        if ($user->role === 'landlord') :
                            $m->ibo_id = $u->id ?? 0;
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
        $meeting = Meeting::where(function ($q) use ($id, $user) {
            $q->where("id", $id);
            $q->where(function ($q) use ($user) {
                $q->where("user_id", $user->id);
                $q->orWhere("created_by_id", $user->id);
            });
        })->get();

        if ($meeting && count($meeting) > 0) {
            return response([
                'status'    => true,
                'message'   => 'Meeting fetched successfully.',
                'data'      => $meeting[0]
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

        $meeting = Meeting::where(function ($q) use ($id, $user) {
            $q->where("id", $id);
            $q->where(function ($q) use ($user) {
                $q->where("user_id", $user->id);
                $q->orWhere("created_by_id", $user->id);
            });
        })->get();

        if ($meeting && count($meeting) > 0) {
            $meeting = $meeting[0];
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
                        Meeting::where("create_id", $meeting->create_id)->update(["user_id" => 0]);
                    }
                }

                if ($request->status === 'on the way') {
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

                //notifications
                $property = Property::find($meeting->property_id);
                $ibo = User::find($ibo_id);

                if ($user->role === 'ibo') {
                    //notify user meeting is scheduled
                    $s = $meeting->meeting_status === 'approved' ? 'Appointment Accepted' : 'Appointment ' . ucwords($meeting->meeting_status);
                    $user_notify = new TenantNotification;
                    $user_notify->tenant_id = $meeting->created_by_id;
                    $user_notify->type = 'Urgent';
                    $user_notify->title = $s;
                    $user_notify->content = $meeting->meeting_status === 'approved' ? 'Scheduled visit for property - ' . $property->property_code . ' has been accepted by IBO - ' . $ibo->first . ' ' . $ibo->last : 'Your Appointment status has been updated by IBO.';
                    $user_notify->name = 'Rent A Roof';

                    $user_notify->save();

                    event(new NotificationSent($user_notify));
                } else if ($user->role === 'tenant') {
                    //notify user meeting is scheduled
                    $ibo_notify = new IboNotification;
                    $ibo_notify->ibo_id = $ibo_id;
                    $ibo_notify->type = 'Urgent';
                    $ibo_notify->title = 'Appointment ' . ucwords($meeting->meeting_status);
                    $ibo_notify->content = 'Appointment for property - ' . $property->property_code . ' has been ' . $meeting->meeting_status . ' by - ' . $user->first . ' ' . $user->last;
                    $ibo_notify->name = 'Rent A Roof';

                    $ibo_notify->save();

                    event(new NotificationSent($ibo_notify));
                }
                //notify admin
                $an = new AdminNotification;
                $an->content = $meeting->meeting_status === 'approved' ? 'Meeting request for property - ' . $property->property_code . ' has been accepted by IBO - ' . $ibo->first . ' ' . $ibo->last : 'Meeting status updated';
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
                $landlord_notify->content = $meeting->meeting_status === 'approved' ? 'Meeting request for property - ' . $property->property_code . ' has been accepted by IBO - ' . $ibo->first . ' ' . $ibo->last : 'Meeting status updated';
                $landlord_notify->name = 'Rent A Roof';
                $landlord_notify->redirect = '/landlord/appointment';

                $landlord_notify->save();

                event(new NotificationSent($landlord_notify));

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

        $meeting = Meeting::where(function ($q) use ($id, $user) {
            $q->where("id", $id);
            $q->where(function ($q) use ($user) {
                $q->where("user_id", $user->id);
                $q->orWhere("created_by_id", $user->id);
            });
        })->get();

        if ($meeting && count($meeting) > 0) {
            $meeting = $meeting[0];
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
