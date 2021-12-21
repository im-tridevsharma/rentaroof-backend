<?php

namespace App\Http\Controllers\api\user;

use App\Events\AdminNotificationSent;
use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\Faq;
use App\Models\IboEvaluation;
use App\Models\Mcq;
use App\Models\Question;
use App\Models\Training;
use App\Models\User;
use Illuminate\Http\Request;
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

    public function getMcqsIBO()
    {
        $user = JWTAuth::user();
        if ($user && $user->role === 'ibo') {
            $mcqs = Mcq::all()->map(function ($m) use ($user) {
                $is = IboEvaluation::where("mcq_id", $m->id)->where("ibo_id", $user->id)->first();
                $m->evaluation = $is;
                $m->questions  = Question::select(['title', 'option1', 'option2', 'option3', 'option4', 'id'])->where("mcq_id", $m->id)->get();
                return $m;
            });
            return response([
                'status'    => true,
                'message'   => 'Mcqs fecthed successfully.',
                'data'      => $mcqs
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'User not found.'
        ], 404);
    }

    //submit answer
    public function saveAnswer(Request $request)
    {
        if ($request->has('mcq_id')) {
            if (is_array($request->question_id) && is_array($request->question_answer)) {
                $savedata = new IboEvaluation;
                $total_answered = 0;
                $marks_obtained = 0;
                $answers_history = [];

                $mcq = Mcq::find($request->mcq_id);
                $savedata->total_questions  = $mcq->total_questions;
                $savedata->total_time_taken = floatval($request->time_taken) / 60;
                $savedata->mcq_id = $mcq->id;
                $savedata->ibo_id = JWTAuth::user()->id;

                $questions = Question::where("mcq_id", $request->mcq_id)->get();
                $i = 0;
                foreach ($questions as $q) {
                    if (in_array($q->id, $request->question_id)) {
                        if ($request->question_answer[$i] === $q->answer) {
                            $marks_obtained += intval($q->mark);
                        }

                        if ($request->question_answer[$i]) {
                            $total_answered++;
                        }

                        array_push($answers_history, ['ibo_answer' => $request->question_answer[$i], 'is_correct' => $request->question_answer[$i] === $q->answer, 'correct' => $q->answer, 'name' => $q->title]);
                    }
                    $i++;
                }

                $savedata->answers = json_encode($answers_history);
                $savedata->answered_questions = $total_answered;
                $savedata->total_marks_obtained = $marks_obtained;

                if ($savedata->save()) {

                    //notify admin
                    $an = new AdminNotification;
                    $an->content = JWTAuth::user()->first . ' ' . JWTAuth::user()->last . ' has attened MCQ Test: ' . $mcq->title;
                    $an->type  = 'Urgent';
                    $an->title = 'New Response to MCQ';
                    $an->redirect = '/admin/evaluations';
                    $an->save();
                    event(new AdminNotificationSent($an));

                    return response([
                        'status'    => true,
                        'message'   => 'Answered saved successfully.',
                        'data'      => $savedata
                    ], 200);
                }

                return response([
                    'status'    => false,
                    'message'   => 'Something went wrong.'
                ], 500);
            } else {
                return response([
                    'status'    => false,
                    'message'   => 'Answers not found!'
                ], 422);
            }
        }

        return response([
            'status'    => false,
            'message'   => 'Mcq not found!'
        ], 404);
    }
}
