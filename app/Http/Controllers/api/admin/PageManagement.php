<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use App\Models\Pages;

class PageManagement extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $pages = Pages::all();
        foreach ($pages as $page) {
            $parent = Pages::find($page->parent);
            $page->parent = isset($parent->name) ? $parent->name : '';
        }

        if ($pages) {
            return response([
                'status'    => true,
                'message'   => 'Pages fetched successfully.',
                'data'      => $pages
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Something went wrong!'
        ], 500);
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
            "name"      => 'required|string|between:5,100',
            "slug"      => 'required|url|unique:pages',
        ]);

        if ($validator->fails()) {
            return response([
                'status'     => false,
                'message'    => 'Some errors occured.',
                'error'      => $validator->errors()
            ], 400);
        }

        $page = new Pages;

        $page->name = $request->name;
        $page->slug = $request->slug;
        $page->content = isset($request->content) ? $request->content : '';
        $page->meta_title = isset($request->meta_title) ? $request->meta_title : '';
        $page->meta_keywords = isset($request->meta_keywords) ? $request->meta_keywords : '';
        $page->meta_description = isset($request->meta_description) ? $request->meta_description : '';
        $page->parent = $request->parent;
        $page->custom_header_text = isset($request->custom_header_text) ? $request->custom_header_text : '';
        $page->custom_footer_text = isset($request->custom_footer_text) ? $request->custom_footer_text : '';

        if ($page->save()) {
            return response([
                'status'    => true,
                'message'   => 'Page has been created successfully.',
                'data'      => $page
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
        $page = Pages::find($id);

        if ($page) {

            return response([
                'status'    => true,
                'message'   => 'Page data fetched successfully.',
                'data'      => $page
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Page you request not found!'
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
        $validator = Validator::make($request->all(), [
            "name"      => 'required|string|between:5,100',
            "slug"      => 'required|url',
        ]);

        if ($validator->fails()) {
            return response([
                'status'     => false,
                'message'    => 'Some errors occured.',
                'error'      => $validator->errors()
            ], 400);
        }

        $page = Pages::find($id);
        if ($page) {

            //check for unique slug
            if ($page->slug !== $request->slug) {
                if (Pages::where("slug", $request->slug)->count()) {
                    return response([
                        'status'    => false,
                        'message'   => 'The slug has already been taken.'
                    ], 400);
                }
            }

            $page->name = $request->name;
            $page->slug = $request->slug;
            $page->content = isset($request->content) ? $request->content : '';
            $page->meta_title = isset($request->meta_title) ? $request->meta_title : '';
            $page->meta_keywords = isset($request->meta_keywords) ? $request->meta_keywords : '';
            $page->meta_description = isset($request->meta_description) ? $request->meta_description : '';
            $page->parent = $request->parent;
            $page->custom_header_text = isset($request->custom_header_text) ? $request->custom_header_text : '';
            $page->custom_footer_text = isset($request->custom_footer_text) ? $request->custom_footer_text : '';

            if ($page->save()) {
                return response([
                    'status'    => true,
                    'message'   => 'Page has been updated successfully.',
                    'data'      => $page
                ], 200);
            }

            return response([
                'status'    => false,
                'message'   => 'Something went wrong!'
            ], 500);
        }

        return response([
            'status'    => false,
            'message'   => 'Page not found!'
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
        $page = Pages::find($id);

        if ($page) {
            if ($page->delete()) {
                return response([
                    'status'    => true,
                    'message'   => 'Page deleted successfully.',
                    'data'      => $page
                ], 200);
            }

            return response([
                'status'    => false,
                'message'   => 'Something went wrong!'
            ], 500);
        }

        return response([
            'status'    => false,
            'message'   => 'Page you request not found!'
        ], 404);
    }
}
