<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PropertyDeal extends Model
{
    use HasFactory;

    protected $fillable = ['property_id', 'created_by', 'offer_for', 'offer_price', 'offer_expires_time'];

    public function property()
    {
        return $this->belongsTo('App\Models\Property');
    }
}
