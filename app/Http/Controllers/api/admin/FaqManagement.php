<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FaqManagement extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $faqs = Faq::all();
        return response([
            'status'    => true,
            'message'   => 'FAQs fetched successfully.',
            'data'      => $faqs
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
        $validator = Validator::make($request->input(), [
            'title'     => 'required|string|max:200',
            'type'      => 'required|in:tenant,ibo,landlord',
            'faq'       => 'required'
        ]);

        if ($validator->fails()) {
            return response([
                'status'    => false,
                'message'   => 'Some errors occured.',
                'error'     => $validator->errors()
            ], 422);
        }

        //save faq
        $faq = new Faq;
        $faq->title = $request->title;
        $faq->type = $request->type;
        $faq->faq = $request->faq;

        if ($faq->save()) {
            return response([
                'status'    => true,
                'message'   => 'Faq saved successfully.',
                'data'      => $faq
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Something went wrong!'
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
        $faq = Faq::find($id);

        if ($faq) {
            return response([
                'status'    => true,
                'message'   => 'Faq fetched successfully.',
                'data'      => $faq
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Faq not found.'
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
        $validator = Validator::make($request->input(), [
            'title'     => 'required|string|max:200',
            'type'      => 'required|in:tenant,ibo,landlord',
            'faq'       => 'required'
        ]);

        if ($validator->fails()) {
            return response([
                'status'    => false,
                'message'   => 'Some errors occured.',
                'error'     => $validator->errors()
            ], 422);
        }

        //save faq
        $faq = Faq::find($id);
        if ($faq) {

            $faq->title = $request->title;
            $faq->type = $request->type;
            $faq->faq = $request->faq;

            if ($faq->save()) {
                return response([
                    'status'    => true,
                    'message'   => 'Faq updated successfully.',
                    'data'      => $faq
                ], 200);
            }

            return response([
                'status'    => false,
                'message'   => 'Something went wrong!'
            ], 500);
        }

        return response([
            'status'    => false,
            'message'   => 'Faq not found!'
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
        $faq = Faq::find($id);

        if ($faq) {
            $faq->delete();
            return response([
                'status'    => true,
                'message'   => 'Faq deleted successfully.',
                'data'      => $faq
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Faq not found.'
        ], 404);
    }
}
