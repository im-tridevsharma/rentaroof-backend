<?php

namespace App\Http\Controllers\api\user;

use App\Http\Controllers\Controller;
use App\Models\Agreement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

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
            $agreements = Agreement::where("tenant_id", $user->id)->orWhere("ibo_id", $user->id)->orWhere("landlord_id", $user->id)->get();
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
