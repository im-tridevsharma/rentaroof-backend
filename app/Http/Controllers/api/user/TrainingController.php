<?php

namespace App\Http\Controllers\api\user;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Training;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TrainingController extends Controller
{
    public function videos($id)
    {
        $user = User::find($id);
        if ($user) {
            //find all gloabl trainings
            $globals = Training::where("type", "global")->get();
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
            $personal = DB::table("trainings")->where("type", "user")->whereRaw("FIND_IN_SET(?, user_ids) > 0", ["$user->id"])->get();
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

            return $personal;
        }

        return response([
            'status'    => false,
            'message'   => "User not found!"
        ], 404);
    }
}
