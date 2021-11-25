<?php

namespace App\Http\Controllers\api\user;

use App\Http\Controllers\Controller;
use App\Models\Agreement;
use App\Models\Property;
use App\Models\User;
use Illuminate\Http\File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use PDF;

class AgreementController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $user = JWTAuth::user();
        if ($user) {
            $agreements = Agreement::where("tenant_id", $user->id)->orWhere("ibo_id", $user->id)->orWhere("landlord_id", $user->id)->get()->map(function ($a) {
                $a->property_data = Property::find($a->property_id)->only(['front_image', 'name', 'property_code', 'bedrooms', 'bathrooms', 'floors', 'monthly_rent', 'maintenence_charge', 'country_name', 'state_name', 'city_name']);
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
        $agreement->fee_percentage = $request->has('fee_percentage') ? $request->fee_percentage : '';
        $agreement->fee_amount = $request->has('fee_amount') ? $request->fee_amount : '';
        $agreement->number_of_invoices = 0;

        //create agreement and upload to server
        $url = $this->create_agreement($agreement);
        $agreement->agreement_url = $url;

        if ($agreement->save()) {
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
                    $template = str_replace("[[LANDLORD_SIGNATURE]]", '<img src="' . $this->base64($landlord->signature) . 'width="100" height="100"/>', $template);
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
                    $template = str_replace("[[TENANT_SIGNATURE]]", '<img src="' . $this->base64($tenant->signature) . 'width="100" height="100"/>', $template);
                }
                $template = str_replace("[[TENANT_FULL_ADDRESS]]", $tenant->address ? $tenant->address->full_address : '', $template);
            }

            if ($property) {
                $template = str_replace("[[MONTHLY_RENT]]", $property->monthly_rent, $template);
                $template = str_replace("[[MAINTENANCE_CHARGE]]", $property->maintenence_charge, $template);
                $template = str_replace("[[MAINTENANCE_DURATION]]", $property->maintenence_duration, $template);
                $template = str_replace("[[SECURITY_DEPOSIT]]", $property->security_amount, $template);
            }

            $to = \Carbon\Carbon::createFromFormat('Y-m-d', $agreement->end_date);
            $from = \Carbon\Carbon::createFromFormat('Y-m-d', $agreement->start_date);
            $diff_in_months = $to->diffInMonths($from);

            $template = str_replace("[[CONTRACT_DURATION]]", $diff_in_months . ' Months', $template);
            $template = str_replace("[[START_DATE]]", date("d-m-Y", strtotime($agreement->start_date)), $template);
            $template = str_replace("[[END_DATE]]", date("d-m-Y", strtotime($agreement->end_date)), $template);
            $template = str_replace("[[NEXT_DUE]]", date("d-m-Y", strtotime($agreement->next_due)), $template);

            $pdf->loadHTML($template)->save(public_path('temp/temp.pdf'));

            // $upload_dir = '/uploads/agreements';
            // $name = Storage::disk('digitalocean')->put($upload_dir, new File(public_path('temp/temp.pdf')), 'public');
            // $url = Storage::disk('digitalocean')->url($name);

            // if (file_exists(public_path('temp/temp.pdf'))) {
            //     unlink(public_path('temp/temp.pdf'));
            // }

            // return $url;
            return "success";
        }

        return '';
    }

    //getbase64
    public function base64($url)
    {
        $image = file_get_contents($url);
        if ($image !== false) {
            return 'data:image/jpg;base64,' . base64_encode($image);
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
}
