<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OTPVerification extends Model
{
    use HasFactory;

    protected $table = 'otp_verifications';
    protected $guarded = ['id', 'created_at', 'updated_at'];
}
