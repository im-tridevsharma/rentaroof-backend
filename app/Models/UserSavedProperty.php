<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserSavedProperty extends Model
{
    use HasFactory;
    protected $guarded = ['id', 'updated_at', 'created_at'];

    public function user()
    {
        return $this->belongsTo('App\Models\User');
    }
}
