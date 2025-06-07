<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Category; // Pastikan untuk mengimpor model Category

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Restoran', 'icon_url' => 'url_icon_restoran.png'],
            ['name' => 'Kafe', 'icon_url' => 'url_icon_kafe.png'],
            ['name' => 'Wisata Alam', 'icon_url' => 'url_icon_wisata_alam.png'],
            ['name' => 'Tempat Belanja', 'icon_url' => 'url_icon_belanja.png'],
            // Tambahkan kategori lain sesuai kebutuhan
        ];

        foreach ($categories as $category) {
            Category::create($category);
        }
    }
}
