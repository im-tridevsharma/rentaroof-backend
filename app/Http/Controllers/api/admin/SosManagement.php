<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\Controller;
use App\Models\Sos;
use Illuminate\Http\Request;

class SosManagement extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $allsos = Sos::all();

        if ($allsos) {
            return response([
                'status'    => true,
                'message'   => 'All Sos fetched successfully.',
                'data'      => $allsos
            ], 200);
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
        $sos = Sos::find($id);

        if ($sos) {
            $sos->status_history = json_decode($sos->status_history);
            return response([
                'status'    =>  true,
                'message'   => 'Sos fetched successfully.',
                'data'      => $sos
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Sos not found.'
        ], 404);
    }

    /**
     * Update the specified resource from storage
     */
    public function update(Request $request, $id)
    {
        $sos = Sos::find($id);
        if ($sos) {
            $sos->resolve_status = $request->status;
            $sos->resolve_message = isset($request->message) ? $request->message : '';

            $history = json_decode($sos->status_history);
            if (is_array($history)) {
                array_push($history, ["status" => $request->status, "message" => $request->message]);
                $sos->status_history = json_encode($history);
            }

            if ($sos->save()) {
                $sos->status_history = json_decode($sos->status_history);
                return response([
                    'status'    => true,
                    'message'   => 'Sos status updated successfully.',
                    'data'      => $sos
                ], 200);
            }

            return response([
                'status'    => false,
                'message'   => 'Something went wrong.'
            ], 500);
        }

        return response([
            'status'    => false,
            'message'   => 'Sos not found.'
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
        $sos = Sos::find($id);

        if ($sos) {
            $sos->delete();
            return response([
                'status'    => true,
                'message'   => 'Sos deleted successfully.',
                'data'      => $sos
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Sos not found.'
        ], 404);
    }
}
