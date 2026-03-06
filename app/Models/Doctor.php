<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Doctor extends Authenticatable
{
    use HasApiTokens, HasFactory;

    protected $fillable = ['name', 'email', 'specialty', 'password', 'bio'];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function slots()
    {
        return $this->hasMany(Slot::class);
    }
}
