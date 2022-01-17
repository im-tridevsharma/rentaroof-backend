<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use App\Models\Blog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class BlogManagement extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $blogs = Blog::all();
        foreach ($blogs as $blog) {
            $parent = Blog::find($blog->parent);
            $blog->parent = isset($parent->title) ? $parent->title : '';
        }

        if ($blogs) {
            return response([
                'status'    => true,
                'message'   => 'Blog fetched successfully.',
                'data'      => $blogs
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Something went wrong!'
        ], 500);
    }

    //get blog data
    public function getBlog(Request $request)
    {
        if ($request->has('slug')) {
            $blog = Blog::where("slug", $request->slug)->where("status", 1)->first();
            if($blog){
                return response([
                    'status'    => true,
                    'message'   => 'Blog data fetched successfully.',
                    'data'      => $blog
                ], 200);
            }

            return response([
                'status'    => false,
                'message'   => 'Blog not found for slug '. $request->slug
            ], 404);
        }

        return response([
            'status'    => false,
            'message'   => 'Blog data not found!'
        ], 404);
    }

    //get blog data
    public function getBlogs(Request $request)
    {
        $blogs = Blog::orderBy("id", "desc")->where("status" ,1);
        if($request->limit){
            $blogs->limit($request->limit);
        }
        $blogs = $blogs->get();

        return response([
            'status'    => true,
            'message'   => 'Blogs fetched successfully.',
            'data'      => $blogs
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
        $validator = Validator::make($request->all(), [
            "title"      => 'required|string|between:5,100',
            "slug"      => 'required|url|unique:blogs',
        ]);

        if ($validator->fails()) {
            return response([
                'status'     => false,
                'message'    => 'Some errors occured.',
                'error'      => $validator->errors()
            ], 400);
        }

        $cover_image = '';
        if ($request->hasFile('cover_image')) {
            $upload_dir = "/uploads/blogs/cover_image";
            $name = Storage::disk('digitalocean')->put($upload_dir, $request->file('cover_image'), 'public');
            $cover_image = Storage::disk('digitalocean')->url($name);
        }

        $blog = new Blog;

        $blog->title = $request->title;
        $blog->sub_title = $request->sub_title ?? '';
        $blog->slug = $request->slug;
        $blog->content = isset($request->content) ? $request->content : '';
        $blog->meta_title = isset($request->meta_title) ? $request->meta_title : '';
        $blog->meta_keywords = isset($request->meta_keywords) ? $request->meta_keywords : '';
        $blog->meta_description = isset($request->meta_description) ? $request->meta_description : '';
        $blog->status = $request->status ?? 0;
        $blog->cover_image = $cover_image ?? '';
        $blog->posted_by = Auth::user()->id;
        $blog->user_role = Auth::user()->role;

        if ($blog->save()) {
            return response([
                'status'    => true,
                'message'   => 'Blog has been created successfully.',
                'data'      => $blog
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
        $blog = Blog::find($id);

        if ($blog) {

            return response([
                'status'    => true,
                'message'   => 'Blog data fetched successfully.',
                'data'      => $blog
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Blog you request not found!'
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
            "title"      => 'required|string|between:5,100',
            "slug"      => 'required|url',
        ]);

        if ($validator->fails()) {
            return response([
                'status'     => false,
                'message'    => 'Some errors occured.',
                'error'      => $validator->errors()
            ], 400);
        }

        $blog = Blog::find($id);

        $cover_image = '';
        if ($request->hasFile('cover_image') && $request->cover_image) {
            $upload_dir = "/uploads/blogs/cover_image";
            $name = Storage::disk('digitalocean')->put($upload_dir, $request->file('cover_image'), 'public');
            $cover_image = Storage::disk('digitalocean')->url($name);

            //remove old image
            $oldimg = $blog->cover_image;
            if (Storage::disk('digitalocean')->exists($upload_dir . '/' . basename($oldimg))) {
                Storage::disk('digitalocean')->delete($upload_dir . '/' . basename($oldimg));
            }
        }

        if ($blog) {
            //check for unique slug
            if ($blog->slug !== $request->slug) {
                if (Blog::where("slug", $request->slug)->count()) {
                    return response([
                        'status'    => false,
                        'message'   => 'The slug has already been taken.'
                    ], 400);
                }
            }

            $blog->title = $request->title;
            $blog->sub_title = $request->sub_title ?? '';
            $blog->slug = $request->slug;
            $blog->content = isset($request->content) ? $request->content : '';
            $blog->meta_title = isset($request->meta_title) ? $request->meta_title : '';
            $blog->meta_keywords = isset($request->meta_keywords) ? $request->meta_keywords : '';
            $blog->meta_description = isset($request->meta_description) ? $request->meta_description : '';
            $blog->status = $request->status ?? 0;
            $blog->cover_image = !empty($cover_image) ? $cover_image : $blog->cover_image;

            if ($blog->save()) {
                return response([
                    'status'    => true,
                    'message'   => 'Blog has been updated successfully.',
                    'data'      => $blog
                ], 200);
            }

            return response([
                'status'    => false,
                'message'   => 'Something went wrong!'
            ], 500);
        }

        return response([
            'status'    => false,
            'message'   => 'Blog not found!'
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
        $blog = Blog::find($id);

        if ($blog) {
            //remove old image
            $upload_dir = "/uploads/blogs/cover_image";
            $oldimg = $blog->cover_image;
            if (Storage::disk('digitalocean')->exists($upload_dir . '/' . basename($oldimg))) {
                Storage::disk('digitalocean')->delete($upload_dir . '/' . basename($oldimg));
            }

            if ($blog->delete()) {
                return response([
                    'status'    => true,
                    'message'   => 'Blog deleted successfully.',
                    'data'      => $blog
                ], 200);
            }

            return response([
                'status'    => false,
                'message'   => 'Something went wrong!'
            ], 500);
        }

        return response([
            'status'    => false,
            'message'   => 'Blog you request not found!'
        ], 404);
    }
}
