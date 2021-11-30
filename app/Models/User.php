<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\App;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    protected $fillable = ['first', 'last', 'email', 'mobile', 'username', 'role'];
    protected $hidden = ['password', 'remember_token'];

    // Rest omitted for brevity

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    //conversation
    public function conversations()
    {
        return $this->hasMany('App\Models\Conversation', 'sender_id');
    }

    //user has kyc details
    public function kyc()
    {
        return $this->hasOne('App\Models\KycVerification');
    }

    //user address
    public function address()
    {
        return $this->hasOne('App\Models\Address');
    }

    //user has meetings
    public function meetings()
    {
        return $this->hasMany('App\Models\Meeting');
    }

    public function searches()
    {
        return $this->hasMany('App\Models\UserSavedSearch');
    }

    public function saved_properties()
    {
        return $this->hasMany('App\Models\UserSavedProperty');
    }

    public function complains()
    {
        return $this->hasMany('App\Models\Complain');
    }
}
