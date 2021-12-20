<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IboEvaluation extends Model
{
    use HasFactory;
    protected $fillable = ['mcq_id', 'ibo_id', 'answered_questions', 'total_questions', 'total_marks_obtained', 'total_time_taken'];
}
