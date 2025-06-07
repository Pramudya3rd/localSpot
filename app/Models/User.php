<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens; // Untuk token autentikasi

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'email',
        'password',
        'username',
        'profile_picture_url',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed', // <--- TAMBAHKAN BARIS INI
    ];

    public function places()
    {
        return $this->hasMany(Place::class, 'added_by_user_id');
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function passwordResetTokens()
    {
        return $this->hasMany(PasswordResetToken::class);
    }
}
