<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'icon_url',
    ];

    // Accessor untuk mengubah icon_url menjadi URL lengkap
    public function getIconUrlAttribute($value)
    {
        if ($value) {
            // Asumsi ikon disimpan di storage/app/public/icons/
            return url('storage/icons/' . $value);
        }
        return null;
    }

    public function places()
    {
        return $this->hasMany(Place::class);
    }
}
