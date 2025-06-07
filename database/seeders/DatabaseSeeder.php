<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents; // Ini sudah dikomentari, biarkan saja

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Membuat satu user contoh
        User::factory()->create([
            'email' => 'test@example.com', // Menggunakan 'email' sebagai field utama untuk login
            'password' => \Illuminate\Support\Facades\Hash::make('password123'), // Atur password yang kuat
            'username' => 'testuser', // Menambahkan username jika ada di tabel users
        ]);

        // Memanggil CategorySeeder untuk mengisi data kategori
        $this->call(CategorySeeder::class);
    }
}
