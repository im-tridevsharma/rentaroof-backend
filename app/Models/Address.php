<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    use HasFactory;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    //belongs to property
    public function property()
    {
        return $this->belongsTo('App\Models\Property');
    }

    //belongs to user
    public function user()
    {
        return $this->belongsTo('App\Models\User');
    }

    //belongs to employee
    public function employee()
    {
        return $this->belongsTo('App\Models\Employee');
    }
}
