<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KycVerification extends Model
{
    use HasFactory;

    protected $fillable = ['document_type', 'document_number', 'other_document_name', 'other_document_number', 'aadhar_upload', 'pan_upload'];


    //belongs to user
    public function user()
    {
        return $this->belongsTo('App\Models\User');
    }
}
