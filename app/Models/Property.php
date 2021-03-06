<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Property extends Model
{
    use HasFactory;

    protected $guarded = ['id', 'verified_at', 'is_approved', 'created_at', 'updated_at'];



    //property has address
    public function address()
    {
        return $this->hasOne('App\Models\Address');
    }

    //property has rating and review
    public function rating_and_review()
    {
        return $this->hasMany('App\Models\PropertyRatingAndReview');
    }

    //property has essentials
    public function essential()
    {
        return $this->hasOne('App\Models\PropertyEssential');
    }

    //property has meetings
    public function meetings()
    {
        return $this->hasMany('App\Models\Meeting');
    }

    //property has gallery
    public function gallery()
    {
        return $this->hasOne('App\Models\PropertyGallery');
    }

    //has many deals
    public function deals()
    {
        return $this->hasMany('App\Models\PropertyDeal');
    }
}
