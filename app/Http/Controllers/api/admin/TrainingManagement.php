<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use App\Models\Training;
use Illuminate\Support\Facades\Storage;

class TrainingManagement extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $trainings = Training::all();

        if ($trainings) {
            return response([
                'status'    => true,
                'message'   => 'Trainings fetched successfully.',
                'data'      => $trainings
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
            'title'         => 'required|string|between:2,100',
            'description'   => 'required|string|max:200',
            'type'          => 'required|in:global,user'
        ]);

        if ($validator->fails()) {
            return response([
                'status'    => false,
                'message'   => 'Some errors occured.',
                'error'     => $validator->errors()
            ], 400);
        }

        $training = new Training;
        $training->title = $request->title;
        $training->description = $request->description;
        $training->type = $request->type;
        $training->user_ids = $request->type === 'user' ? json_encode($request->users) : NULL;

        //upload pdf files
        $pdfs = [];
        if ($request->hasFile('pdfs')) {
            $upload_dir = "/uploads/trainings/" . $request->title . "/pdf";
            foreach ($request->pdfs as $pdf) {
                //upload and get links
                $name = Storage::disk('digitalocean')->put($upload_dir, $pdf, 'public');
                array_push($pdfs, Storage::disk('digitalocean')->url($name));
            }
        }

        //upload video files
        $videos = [];
        if ($request->hasFile('videos')) {
            $upload_dir = "/uploads/trainings/" . $request->title . "/video";
            foreach ($request->videos as $video) {
                //upload and get links
                $name = Storage::disk('digitalocean')->put($upload_dir, $video, 'public');
                array_push($videos, Storage::disk('digitalocean')->url($name));
            }
        }

        $training->attachments = json_encode($pdfs);
        $training->video_urls = json_encode($videos);

        if ($training->save()) {
            return response([
                'status'    => true,
                'message'   => 'New training added successfully.',
                'data'      => $training
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
        $training = Training::find($id);

        if ($training) {
            $training->user_ids = $training->user_ids ? json_decode($training->user_ids) : [];
            return response([
                'status'    => true,
                'message'   => 'Training fetched successfully.',
                'data'      => $training
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Training not found!'
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
            'title'         => 'required|string|between:2,100',
            'description'   => 'required|string|max:200',
            'type'          => 'required|in:global,user'
        ]);

        if ($validator->fails()) {
            return response([
                'status'    => false,
                'message'   => 'Some errors occured.',
                'error'     => $validator->errors()
            ], 400);
        }

        $training = Training::find($id);
        if ($training) {
            $training->title = $request->title;
            $training->description = $request->description;
            $training->type = $request->type;
            $training->user_ids = $request->type === 'user' ? json_encode($request->users) : NULL;

            //upload pdf files
            $pdfs = [];
            if ($request->hasFile('pdfs')) {
                $upload_dir = "/uploads/trainings/" . $request->title . "/pdf";
                foreach ($request->pdfs as $pdf) {
                    //upload and get links
                    $name = Storage::disk('digitalocean')->put($upload_dir, $pdf, 'public');
                    array_push($pdfs, Storage::disk('digitalocean')->url($name));
                }
            }

            //upload video files
            $videos = [];
            if ($request->hasFile('videos')) {
                $upload_dir = "/uploads/trainings/" . $request->title . "/video";
                foreach ($request->videos as $video) {
                    //upload and get links
                    $name = Storage::disk('digitalocean')->put($upload_dir, $video, 'public');
                    array_push($videos, Storage::disk('digitalocean')->url($name));
                }
            }

            $prev_attachments = json_decode($training->attachments);
            $prev_video_urls = json_decode($training->video_urls);

            if (isset($request->remove_prev_pdf)) {
                $training->attachments = json_encode($pdfs);
                $upload_dir = "/uploads/trainings/" . $training->title . "/pdf/";
                //remove old files
                if ($prev_attachments) {
                    foreach ($prev_attachments as $pdf) {
                        if (Storage::disk('digitalocean')->exists($upload_dir . basename($pdf))) {
                            Storage::disk('digitalocean')->delete($upload_dir . basename($pdf));
                        }
                    }
                }
            } else {
                $training->attachments = json_encode(array_merge($pdfs, $prev_attachments));
            }

            if (isset($request->remove_prev_video)) {
                $training->video_urls = json_encode($videos);
                $upload_dir = "/uploads/trainings/" . $training->title . "/video/";
                //remove old files
                if ($prev_video_urls) {
                    foreach ($prev_video_urls as $video) {
                        if (Storage::disk('digitalocean')->exists($upload_dir . basename($video))) {
                            Storage::disk('digitalocean')->delete($upload_dir . basename($video));
                        }
                    }
                }
            } else {
                $training->video_urls = json_encode(array_merge($videos, $prev_video_urls));
            }


            if ($training->save()) {
                return response([
                    'status'    => true,
                    'message'   => 'Training updated successfully.',
                    'data'      => $training
                ], 200);
            }

            return response([
                'status'    => false,
                'message'   => 'Something went wrong!'
            ], 500);
        }
        return response([
            'status'    => false,
            'message'   => 'Training not found!'
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
        $training = Training::find($id);

        if ($training) {
            //remove files
            $folder = $training->title;
            $upload_dir = "/uploads/trainings/" . $folder;

            $pdfs = json_decode($training->attachments);
            $videos = json_decode($training->video_urls);

            if ($pdfs) {
                foreach ($pdfs as $pdf) {
                    if (Storage::disk('digitalocean')->exists($upload_dir . '/pdf/' . basename($pdf))) {
                        Storage::disk('digitalocean')->delete($upload_dir . '/pdf/' . basename($pdf));
                    }
                }
            }

            if ($videos) {
                foreach ($videos as $video) {
                    if (Storage::disk('digitalocean')->exists($upload_dir . '/video/' . basename($video))) {
                        Storage::disk('digitalocean')->delete($upload_dir . '/video/' . basename($video));
                    }
                }
            }

            $training->delete();

            return response([
                'status'    => true,
                'message'   => 'Training deleted successfully.',
                'data'      => $training
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Training not found!'
        ], 404);
    }
}
