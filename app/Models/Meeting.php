<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Meeting extends Model
{
    use HasFactory;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    //belongs to user
    public function user()
    {
        return $this->belongsTo('App\Models\User');
    }

    //blongs to property
    public function property()
    {
        return $this->belongsTo('App\Models\Property');
    }
}
