<?php

namespace App\Http\Controllers\api\user;

use App\Events\AdminNotificationSent;
use App\Events\NotificationSent;
use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\Agreement;
use App\Models\IboNotification;
use App\Models\KycVerification;
use App\Models\Property;
use App\Models\TenantNotification;
use App\Models\User;
use Illuminate\Http\File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class AgreementController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $user = JWTAuth::user();
        if ($user) {
            $agreements = Agreement::where("tenant_id", $user->id)->orWhere("ibo_id", $user->id)->orWhere("landlord_id", $user->id)->get()->map(function ($a) use ($request) {
                if (!$request->has('property_data')) {
                    $a->property_data = Property::find($a->property_id)->only(['front_image', 'name', 'property_code', 'bedrooms', 'bathrooms', 'floors', 'monthly_rent', 'maintenence_charge', 'country_name', 'state_name', 'city_name', 'is_closed']);
                }
                $a->landlord = User::find($a->landlord_id)->only(['first', 'last', 'profile_pic', 'email', 'mobile']);
                $a->ibo = User::find($a->ibo_id)->only(['first', 'last', 'profile_pic', 'email', 'mobile']);
                $a->tenant = User::find($a->tenant_id)->only(['first', 'last', 'profile_pic', 'email', 'mobile']);
                return $a;
            });
            if ($agreements) {
                return response([
                    'status'    => true,
                    'message'   => 'Agreements fetched successfully.',
                    'data'      => $agreements
                ], 200);
            }
        }

        return response([
            'status'    => false,
            'message'   => 'User not found!'
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
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:250',
            'property_id' => 'required',
            'tenant_id' => 'required',
            'ibo_id'    => 'required',
            'landlord_id'   => 'required',
            'agreement_type' => 'required',
            'payment_frequency' => 'required|in:monthly,quarterly,half-yearly,yearly',
            'payment_amount'    => 'required',
            'start_date'    => 'required',
            'end_date'      => 'required',
            'next_due'      => 'required'
        ]);

        if ($validator->fails()) {
            return response([
                'status'    => false,
                'message'   => 'Some errors occured.',
                'error'     => $validator->errors()
            ], 400);
        }

        //check for kyc verification for tenant and landlord as well
        $tenant = User::find($request->tenant_id);
        if ($tenant) {
            if ($tenant->kyc_id) {
                $tkyc = KycVerification::find($tenant->kyc_id);
                if ($tkyc && $tkyc->is_verified) {
                    //nothing to do kyc is verified
                    if (!$tenant->signature) {
                        $user_notify = new TenantNotification;
                        $user_notify->tenant_id = $tenant->id;
                        $user_notify->type = 'Urgent';
                        $user_notify->title = 'Signature Alert!';
                        $user_notify->content = 'Please upload your Signature details to get agreement done.';
                        $user_notify->name = 'Rent A Roof';
                        $user_notify->redirect = '/tenant/profile';
                        $user_notify->save();
                        event(new NotificationSent($user_notify));
                        return response([
                            'status'    => false,
                            'message'   => 'Tenant has not uploaded signature yet. Notification sent to him.'
                        ], 400);
                    }
                } else {
                    return response([
                        'status'    => false,
                        'message'   => 'Tenant Kyc is not verified yet. Contact to Rent a Roof Team.'
                    ], 400);
                }
            } else {
                $user_notify = new TenantNotification;
                $user_notify->tenant_id = $tenant->id;
                $user_notify->type = 'Urgent';
                $user_notify->title = 'KYC Alert!';
                $user_notify->content = 'Please upload your KYC details to get agreement done.';
                $user_notify->name = 'Rent A Roof';
                $user_notify->redirect = '/tenant/kyc';
                $user_notify->save();
                event(new NotificationSent($user_notify));
                return response([
                    'status'    => false,
                    'message'   => 'Tenant has not uploaded KYC details yet. Notification sent to him.'
                ], 400);
            }
        } else {
            return response([
                'status'    => false,
                'message'   => 'Tenant not found.'
            ], 404);
        }

        $landlord = User::find($request->landlord_id);
        if ($landlord) {
            if ($landlord->kyc_id) {
                $lkyc = KycVerification::find($landlord->kyc_id);
                if ($lkyc && $lkyc->is_verified) {
                    //nothing to do kyc is verified
                    if (!$landlord->signature) {
                        return response([
                            'status'    => false,
                            'message'   => 'Landlord has not uploaded signature yet. Please upload it first.'
                        ], 400);
                    }
                } else {
                    return response([
                        'status'    => false,
                        'message'   => 'Landlord\'s Kyc is not verified yet.'
                    ], 400);
                }
            } else {
                return response([
                    'status'    => false,
                    'message'   => 'Landlord has not uploaded KYC details yet. Plesae upload it first.'
                ], 400);
            }
        } else {
            return response([
                'status'    => false,
                'message'   => 'Landlord not found.'
            ], 404);
        }

        $agreement = new Agreement;
        $agreement->title = $request->title;
        $agreement->description = $request->has('description') ? $request->description : '';
        $agreement->property_id = $request->property_id;
        $agreement->tenant_id = $request->tenant_id;
        $agreement->ibo_id = $request->ibo_id;
        $agreement->landlord_id = $request->landlord_id;
        $agreement->agreement_type = $request->agreement_type;
        $agreement->payment_frequency = $request->payment_frequency;
        $agreement->payment_amount = $request->payment_amount;
        $agreement->start_date = $request->start_date;
        $agreement->end_date = $request->end_date;
        $agreement->next_due = $request->next_due;
        $agreement->fee_percentage = $request->has('fee_percentage') ? $request->fee_percentage : 0;
        $agreement->fee_amount = $request->has('fee_amount') ? $request->fee_amount : 0.0;
        $agreement->number_of_invoices = 0;
        $agreement->advance_amount = $request->advance_amount ?? 0;
        $agreement->advance_period = $request->advance_period ?? '';
        $agreement->first_payment  = $request->first_month_payment ?? ($request->has('fee_amount') ? $request->fee_amount : 0.0);
        $agreement->security_amount = $request->has('security_amount') ? $request->security_amount : 0;

        if (!User::find($agreement->landlord_id)) {
            return response([
                'status'   => false,
                'message'  => 'Landlord not found!'
            ], 422);
        }

        if (!User::find($agreement->ibo_id)) {
            return response([
                'status'   => false,
                'message'  => 'IBO not found!'
            ], 422);
        }

        if (!User::find($agreement->tenant_id)) {
            return response([
                'status'   => false,
                'message'  => 'Tenant not found!'
            ], 422);
        }

        //create agreement and upload to server
        $url = $this->create_agreement($agreement);
        $agreement->agreement_url = $url;

        if ($agreement->save()) {
            //notify user and ibo for this agreement
            $property = Property::find($agreement->property_id);

            //notify admin
            $an = new AdminNotification;
            $an->content = 'Agreement created for property - ' . $property->property_code . '. Please check now.';
            $an->type  = 'Notification';
            $an->title = 'Agreement Created????';
            $an->redirect = '/admin/agreements';
            $an->save();
            event(new AdminNotificationSent($an));

            //notify user meeting is scheduled
            $user_notify = new TenantNotification;
            $user_notify->tenant_id = $agreement->tenant_id;
            $user_notify->type = 'Urgent';
            $user_notify->title = 'Agreement Created????';
            $user_notify->content = 'Agreement created for property - ' . $property->property_code . '. Please check now.';
            $user_notify->name = 'Rent A Roof';
            $user_notify->redirect = '/tenant/agreements?a=' . $agreement->id;
            $user_notify->save();
            event(new NotificationSent($user_notify));

            //send notification to ibo
            $ibo_notify = new IboNotification;
            $ibo_notify->ibo_id = $agreement->ibo_id;
            $ibo_notify->type = 'Urgent';
            $ibo_notify->title = 'Agreement Created????';
            $ibo_notify->content = 'Agreement created for property - ' . $property->property_code . '. Please check now.';
            $ibo_notify->name = 'Rent A Roof';
            $ibo_notify->redirect = '/ibo/properties';

            $ibo_notify->save();

            event(new NotificationSent($ibo_notify));

            return response([
                'status'    => true,
                'message'   => 'Agreement saved successfully',
                'data'      => $agreement
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Something went wrong'
        ], 500);
    }

    //create_agreement
    protected function create_agreement($agreement)
    {
        if ($agreement) {
            $pdf = App::make('dompdf.wrapper');
            $s = DB::table("settings")->where("setting_key", "agreement_template")->first();

            //generate dynamic contents
            $landlord = User::find($agreement->landlord_id)->load("address");
            $ibo = User::find($agreement->ibo_id)->load("address");
            $tenant = User::find($agreement->tenant_id)->load("address");
            $property = Property::find($agreement->property_id);

            $template = $s->setting_value;

            if ($landlord) {
                $template = str_replace("[[LANDLORD_FIRST_NAME]]", $landlord->first, $template);
                $template = str_replace("[[LANDLORD_LAST_NAME]]", $landlord->last, $template);
                $template = str_replace("[[LANDLORD_FULL_NAME]]", $landlord->first . ' ' . $landlord->last, $template);
                $template = str_replace("[[LANDLORD_EMAIL]]", $landlord->email, $template);
                $template = str_replace("[[LANDLORD_MOBILE]]", $landlord->mobile, $template);
                if (!empty($tenant->signature)) {
                    $template = str_replace("[[LANDLORD_SIGNATURE]]", '<div style="width:300px;height:120px;
                background-position:center;background-size:cover;
                background-repeat:no-repeat;
                    background-image:url(' . $landlord->signature . ');background-color:white;">
                </div>', $template);
                }
                $template = str_replace("[[LANDLORD_FULL_ADDRESS]]", $landlord->address ? $landlord->address->full_address : '', $template);
            }

            if ($ibo) {
                $template = str_replace("[[IBO_FIRST_NAME]]", $ibo->first, $template);
                $template = str_replace("[[IBO_LAST_NAME]]", $ibo->last, $template);
                $template = str_replace("[[IBO_FULL_NAME]]", $ibo->first . ' ' . $ibo->last, $template);
                $template = str_replace("[[IBO_EMAIL]]", $ibo->email, $template);
                $template = str_replace("[[IBO_MOBILE]]", $ibo->mobile, $template);
                $template = str_replace("[[IBO_FULL_ADDRESS]]", $ibo->address ? $ibo->address->full_address : '', $template);
            }

            if ($tenant) {
                $template = str_replace("[[TENANT_FIRST_NAME]]", $tenant->first, $template);
                $template = str_replace("[[TENANT_LAST_NAME]]", $tenant->last, $template);
                $template = str_replace("[[TENANT_FULL_NAME]]", $tenant->first . ' ' . $tenant->last, $template);
                $template = str_replace("[[TENANT_MOBILE]]", $tenant->email, $template);
                $template = str_replace("[[TENANT_EMAIL]]", $tenant->mobile, $template);
                if (!empty($tenant->signature)) {
                    $template = str_replace("[[TENANT_SIGNATURE]]", '<div style="width:300px;height:120px;
                background-position:center;background-size:cover;
                background-repeat:no-repeat;
                    background-image:url(' . $tenant->signature . ');background-color:white;">
                </div>', $template);
                }
                $template = str_replace("[[TENANT_FULL_ADDRESS]]", $tenant->address ? $tenant->address->full_address : '', $template);
            }

            if ($property) {
                $template = str_replace("[[MONTHLY_RENT]]", $property->monthly_rent, $template);
                $template = str_replace("[[MAINTENANCE_CHARGE]]", $property->maintenence_charge, $template);
                $template = str_replace("[[MAINTENANCE_DURATION]]", $property->maintenence_duration, $template);
                $template = str_replace("[[SECURITY_DEPOSIT]]", $property->security_amount, $template);
            }

            $to = \Carbon\Carbon::parse($agreement->end_date);
            $from = \Carbon\Carbon::parse($agreement->start_date);
            $diff_in_months = $to->diffInMonths($from);

            $template = str_replace("[[CONTRACT_DURATION]]", $diff_in_months . ' Months', $template);
            $template = str_replace("[[START_DATE]]", date("d-m-Y", strtotime($agreement->start_date)), $template);
            $template = str_replace("[[END_DATE]]", date("d-m-Y", strtotime($agreement->end_date)), $template);
            $template = str_replace("[[NEXT_DUE]]", date("d-m-Y", strtotime($agreement->next_due)), $template);

            $pdf->loadHTML($template)->save(public_path('temp/temp.pdf'));

            $upload_dir = '/uploads/agreements';
            $name = Storage::disk('digitalocean')->put($upload_dir, new File(public_path('temp/temp.pdf')), 'public');
            $url = Storage::disk('digitalocean')->url($name);

            if (file_exists(public_path('temp/temp.pdf'))) {
                unlink(public_path('temp/temp.pdf'));
            }

            return $url;
        }

        return '';
    }

    //police verification
    public function police_verification($id)
    {
        $agreement = Agreement::find($id);
        if ($agreement) {
            $pdf = App::make('dompdf.wrapper',);

            //generate dynamic contents
            $landlord = User::find($agreement->landlord_id)->load("address");
            $lkyc = KycVerification::find($landlord->kyc_id);
            $tenant = User::find($agreement->tenant_id)->load("address");
            $tkyc = KycVerification::find($tenant->kyc_id);

            $pdf->loadView('police-verification', compact('landlord', 'lkyc', 'tenant', 'tkyc'))
                ->save(public_path('temp/temp.pdf'));

            $upload_dir = '/uploads/police-verification';
            $name = Storage::disk('digitalocean')->put($upload_dir, new File(public_path('temp/temp.pdf')), 'public');
            $url = Storage::disk('digitalocean')->url($name);

            if (file_exists(public_path('temp/temp.pdf'))) {
                unlink(public_path('temp/temp.pdf'));
            }

            $agreement->police_verification = $url;
            $agreement->save();

            return $url;
        } else {
            return false;
        }
    }


    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $agreement = Agreement::find($id);
        if ($agreement) {
            return response([
                'status'    => true,
                'message'   => 'Agreement fetched successfully.',
                'data'      => $agreement
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Agreement not found!'
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
        return response([
            'status' => false,
            'message'   => 'Api not supported for this endpoint yet!'
        ], 204);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $agreement = Agreement::find($id);
        if ($agreement) {
            $agreement->delete();
            return response([
                'status'    => true,
                'message'   => 'Agreement deleted successfully.',
                'data'      => $agreement
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Agreement not found!'
        ], 404);
    }

    //upcoming payments
    public function upcoming_payments()
    {
        $user = JWTAuth::user();

        if ($user) {
            $payments = [];
            $agreements = Agreement::whereRaw("next_due < end_date")->where("tenant_id", $user->id)->get();

            foreach ($agreements as $a) {
                $data = [
                    "amount"    => $a->payment_amount,
                    "type"      =>  'rent',
                    "type_id"   => $a->id,
                    "next_due"  => $a->next_due,
                    "message"   => 'Rent for the month ' . date('d-m-Y', strtotime($a->next_due)) . ' of Property: ' . Property::find($a->property_id)->property_code
                ];

                array_push($payments, $data);
            }

            return response([
                'status'    => true,
                'message'   => 'Upcoming payments fetched successfully.',
                'data'      => $payments
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Access not allowed.'
        ], 401);
    }
}
