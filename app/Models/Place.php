<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Place extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'address',
        'description',
        'category_id',
        'latitude',
        'longitude',
        'opening_hours',
        'main_image_url',
        'added_by_user_id',
    ];

    // --- PERUBAHAN DI SINI: SERTAKAN 'rating' di $appends ---
    // Ini akan memastikan atribut 'rating' (dari accessor di bawah) disertakan dalam JSON
    protected $appends = ['main_image_url', 'rating'];

    // Accessor untuk main_image_url (pastikan ini ada dan benar pathnya)
    public function getMainImageUrlAttribute($value)
    {
        if ($value) {
            // Asumsi gambar utama disimpan di storage/app/public/images/
            return url('storage/images/' . $value);
        }
        return null;
    }

    // --- TAMBAHKAN ACCESSOR getRatingAttribute() INI ---
    // Accessor ini akan menghitung rata-rata rating (dari reviews_avg_rating) dan memformatnya.
    // reviews_avg_rating disediakan oleh withAvg() dari controller.
    public function getRatingAttribute()
    {
        // Jika reviews_avg_rating null (misal tidak ada ulasan), akan dianggap 0.0 sebelum diformat
        return number_format($this->reviews_avg_rating ?? 0, 1);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by_user_id');
    }
}
