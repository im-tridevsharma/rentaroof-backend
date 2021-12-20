<?php

namespace App\Http\Controllers\api\user;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Models\Training;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;

class TrainingController extends Controller
{
    //get videos
    public function videos($id)
    {
        $user = User::find($id);
        if ($user) {
            $videos = [];
            //find personal
            $personal = Training::where("type", $user->role)
                ->where("video_urls", "!=", "[]")
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
            $personal_pdfs = [];
            //find personal
            $personal = Training::where("type", $user->role)
                ->where("attachments", "!=", "[]")
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
                'data'      =>  $personal_pdfs
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => "User not found!"
        ], 404);
    }

    public function getFaqs()
    {
        $user = JWTAuth::user();
        if ($user) {
            $faqs = Faq::where("type", $user->role)->get();
            return response([
                'status'    => true,
                'message'   => 'Faqs fecthed successfully.',
                'data'      => $faqs
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'User not found.'
        ], 404);
    }
}
