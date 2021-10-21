<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TenantRating extends Model
{
    use HasFactory;
    protected $guarged = ['id', 'created_at', 'updated_at'];
}
