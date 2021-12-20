<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\Controller;
use App\Models\IboEvaluation;
use App\Models\Mcq;
use App\Models\Question;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class McqManagement extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $mcqs = Mcq::all();

        return response([
            'status'    => true,
            'message'   => 'Mcqs fetched successfully.',
            'data'      => $mcqs
        ], 200);
    }

    //get evaluations
    public function get_evaluations()
    {
        $evaluations = IboEvaluation::all();
        return response([
            'status'    => true,
            'message'   => 'Evaluations fetched successfully.',
            'data'      => $evaluations->map(function ($e) {
                $e->ibo_name = User::find($e->ibo_id)->first . ' ' . User::find($e->ibo_id)->last;
                $e->mcq_title = Mcq::find($e->mcq_id)->title;

                return $e;
            })
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
            'description' => 'max:200',
            'total_time'  => 'required|integer'
        ]);

        if ($validator->fails()) {
            return response([
                'status'    => false,
                'message'   => 'Some errors occured.',
                'error'     => $validator->errors()
            ], 422);
        }

        //save
        $mcq = new Mcq;
        $mcq->title = $request->title;
        $mcq->description = $request->description;
        $mcq->total_time  = $request->total_time;

        if ($request->has('question_title') && is_array($request->question_title)) {
            $mcq->total_questions = count($request->question_title);
            $marks = 0;
            foreach ($request->question_mark as $m) {
                $marks += $m;
            }

            $mcq->total_marks = $marks;
        } else {
            $mcq->total_questions = 0;
        }

        if ($mcq->save()) {
            //save questions
            if (is_array($request->question_title)) {
                for ($i = 0; $i < count($request->question_title); $i++) {
                    $question = new Question;
                    $question->mcq_id = $mcq->id;
                    $question->title = $request->question_title[$i];
                    $question->option1 = $request->question_option1[$i];
                    $question->option2 = $request->question_option2[$i];
                    $question->option3 = $request->question_option3[$i];
                    $question->option4 = $request->question_option4[$i];
                    $question->answer = $request->question_answer[$i];
                    $question->mark = $request->question_mark[$i];

                    $question->save();
                }
            }

            return response([
                'status'    => true,
                'message'   => 'New Mcq saved successfully.',
                'data'      => $mcq
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
        $mcq = Mcq::find($id);
        $questions = Question::where("mcq_id", $id)->get();
        $mcq->questions = $questions;

        if ($mcq) {
            return response([
                'status'    => true,
                'message'   => 'Mcq fetched successfully.',
                'data'      => $mcq
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Mcq not found!'
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
            'description' => 'max:200',
            'total_time'  => 'required|integer'
        ]);

        if ($validator->fails()) {
            return response([
                'status'    => false,
                'message'   => 'Some errors occured.',
                'error'     => $validator->errors()
            ], 422);
        }

        //save
        $mcq = Mcq::find($id);
        if ($mcq) {
            $mcq->title = $request->title;
            $mcq->description = $request->description;
            $mcq->total_time  = $request->total_time;

            if ($request->has('question_title') && is_array($request->question_title)) {
                $mcq->total_questions = count($request->question_title);
                $marks = 0;
                foreach ($request->question_mark as $m) {
                    $marks += $m;
                }

                $mcq->total_marks = $marks;
            } else {
                $mcq->total_questions = 0;
            }

            if ($mcq->save()) {
                //save questions
                if (is_array($request->question_title)) {
                    for ($i = 0; $i < count($request->question_title); $i++) {
                        $question = Question::find($request->question_id[$i]) ? Question::find($request->question_id[$i]) : new Question;
                        $question->mcq_id = $mcq->id;
                        $question->title = $request->question_title[$i];
                        $question->option1 = $request->question_option1[$i];
                        $question->option2 = $request->question_option2[$i];
                        $question->option3 = $request->question_option3[$i];
                        $question->option4 = $request->question_option4[$i];
                        $question->answer = $request->question_answer[$i];
                        $question->mark = $request->question_mark[$i];

                        $question->save();
                    }
                }

                return response([
                    'status'    => true,
                    'message'   => 'Mcq updated successfully.',
                    'data'      => $mcq
                ], 200);
            }

            return response([
                'status'    => false,
                'message'   => 'Something went wrong!'
            ], 500);
        }

        return response([
            'status'    => false,
            'message'   => 'Mcq not found!'
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
        $mcq = Mcq::find($id);
        if ($mcq) {
            $mcq->delete();
            Question::where("mcq_id", $mcq->id)->delete();

            return response([
                'status'    => true,
                'message'   => 'Mcq deleted successfully.',
                'data'      => $mcq
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Mcq not found!'
        ], 404);
    }

    //delete question
    public function delete_question($id)
    {
        $question = Question::find($id);
        if ($question) {
            $question->delete();
            return response([
                'status'    => true,
                'message'   => 'Question deleted successfully.',
                'data'      => $question
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Question not found.'
        ], 404);
    }

    //delete evaluation
    public function delete_evaluation($id)
    {
        $question = IboEvaluation::find($id);
        if ($question) {
            $question->delete();
            return response([
                'status'    => true,
                'message'   => 'Evaluation deleted successfully.',
                'data'      => $question
            ], 200);
        }

        return response([
            'status'    => false,
            'message'   => 'Evaluation not found.'
        ], 404);
    }
}
