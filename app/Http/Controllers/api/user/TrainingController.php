<?php

namespace App\Http\Controllers\api\user;

use App\Http\Controllers\Controller;

use App\Models\Training;
use App\Models\User;

class TrainingController extends Controller
{
    //get videos
    public function videos($id)
    {
        $user = User::find($id);
        if ($user) {
            //find all gloabl trainings
            $globals = Training::where("type", "global")
                ->where("video_urls", "!=", "[]")
                ->get();
            $videos = [];
            foreach ($globals as $t) {
                $video = json_decode($t->video_urls);
                foreach ($video as $v) {
                    array_push($videos, [
                        "id"          => $t->id,
                        "title"       => $t->title,
                        "description" => $t->description,
                        "video"       => $v
                    ]);
                }
            }
            //find personal
            $personal = Training::where("type", "user")
                ->where("video_urls", "!=", "[]")
                ->whereRaw("JSON_CONTAINS(user_ids, '\"{$user->id}\"')")
                ->get();
            foreach ($personal as $t) {
                $video = json_decode($t->video_urls);
                foreach ($video as $v) {
                    array_push($videos, [
                        "id"          => $t->id,
                        "title"       => $t->title,
                        "description" => $t->description,
                        "video"       => $v
                    ]);
                }
            }

            return response([
                'status'    => true,
                'message'   => 'Training videos fetched successfully.',
                'data'      => $videos
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => "User not found!"
        ], 404);
    }

    //get pdfs
    public function pdfs($id)
    {
        $user = User::find($id);
        if ($user) {
            //find all gloabl trainings
            $globals = Training::where("type", "global")
                ->where("attachments", "!=", "[]")
                ->get();
            $global_pdfs = $personal_pdfs = [];
            foreach ($globals as $t) {
                $pdf = json_decode($t->attachments);
                foreach ($pdf as $p) {
                    array_push($global_pdfs, [
                        "id"          => $t->id,
                        "title"       => $t->title,
                        "description" => $t->description,
                        "pdf"       => $p
                    ]);
                }
            }
            //find personal
            $personal = Training::where("type", "user")
                ->where("attachments", "!=", "[]")
                ->whereRaw("JSON_CONTAINS(user_ids, '\"{$user->id}\"')")
                ->get();
            foreach ($personal as $t) {
                $pdf = json_decode($t->attachments);
                foreach ($pdf as $p) {
                    array_push($personal_pdfs, [
                        "id"          => $t->id,
                        "title"       => $t->title,
                        "description" => $t->description,
                        "pdf"       => $p
                    ]);
                }
            }

            return response([
                'status'    => true,
                'message'   => 'Training pdfs fetched successfully.',
                'data'      => ["global" => $global_pdfs, "persoanl" => $personal_pdfs]
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => "User not found!"
        ], 404);
    }
}
