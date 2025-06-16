<?php

namespace App\Http\Controllers;

use App\Models\Place;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage; // Pastikan ini diimport untuk mengelola file
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB; // Pastikan ini diimport untuk DB::raw

class PlaceController extends Controller
{
    public function index(Request $request)
    {
        $query = Place::query();

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('search_query')) {
            $query->where(function ($q) use ($request) {
                // Perbaikan: Pastikan menggunakan $request->search_query
                $q->where('name', 'like', '%' . $request->search_query . '%')
                    ->orWhere('address', 'like', '%' . $request->search_query . '%')
                    ->orWhere('description', 'like', '%' . $request->search_query . '%');
            });
        }

        // Filter berdasarkan lokasi terdekat (contoh sederhana, bisa lebih kompleks dengan PostGIS)
        if ($request->has(['latitude', 'longitude'])) {
            $latitude = $request->latitude;
            $longitude = $request->longitude;
            $radius = 50; // Radius dalam KM, bisa disesuaikan

            $query->selectRaw("*,
                ( 6371 * acos( cos( radians(?) ) * cos( radians( latitude ) )
                * cos( radians( longitude ) - radians(?) ) + sin( radians(?) )
                * sin( radians( latitude ) ) ) ) AS distance", [$latitude, $longitude, $latitude])
                ->having('distance', '<', $radius)
                ->orderBy('distance');
        }

        // --- PERBAIKAN PENTING DI SINI: Tambahkan withAvg untuk menghitung rata-rata rating ---
        // Load kategori, dan hitung rata-rata rating ulasan.
        $places = $query->with('category')->withAvg('reviews', 'rating')->get();

        return response()->json($places, 200);
    }

    public function show($id)
    {
        // --- PERBAIKAN PENTING DI SINI: Tambahkan withAvg untuk detail tempat ---
        // Load kategori, ulasan (beserta user), dan hitung rata-rata rating ulasan.
        $place = Place::with('category', 'reviews.user')->withAvg('reviews', 'rating')->find($id);

        if (!$place) {
            return response()->json(['message' => 'Place not found'], 404);
        }

        // Catatan: Jika Anda telah mengatur accessor 'getRatingAttribute()' dan '$appends = ['rating']'
        // di model Place.php, maka properti 'rating' yang terformat akan otomatis disertakan.

        return response()->json($place, 200);
    }

    public function store(Request $request)
    {
        // --- TAMBAHKAN BARIS INI UNTUK DEBUGGING ---
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'required|exists:categories,id',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'opening_hours' => 'nullable|string|max:255', // Di sini sudah 'opening_hours'
            // --- PERBAIKAN: Validasi untuk file gambar 'main_image' ---
            'main_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', // max 2MB
            // --- AKHIR PERBAIKAN ---
            // 'main_image_url' tidak lagi divalidasi sebagai string, diganti main_image (file)
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $mainImageUrl = null;
        // --- PERBAIKAN: Logika penyimpanan gambar ---
        if ($request->hasFile('main_image')) {
            // Simpan gambar ke storage/app/public/images/
            // 'public' adalah nama disk yang terhubung ke storage/app/public
            // Pastikan Anda sudah menjalankan 'php artisan storage:link' secara lokal
            $path = $request->file('main_image')->store('images', 'public');
            $mainImageUrl = basename($path); // Simpan hanya nama file (tanpa path 'images/') di database
        }
        // --- AKHIR PERBAIKAN ---

        $place = Place::create([
            'name' => $request->name,
            'address' => $request->address,
            'description' => $request->description,
            'category_id' => $request->category_id,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'opening_hours' => $request->opening_hours,
            'main_image_url' => $mainImageUrl, // Simpan nama file yang didapat
            'added_by_user_id' => Auth::id() ?? $request->added_by_user_id, // Gunakan Auth::id() jika user login, atau fallback ke request
        ]);

        return response()->json($place, 201);
    }

    public function update(Request $request, $id)
    {
        $place = Place::find($id);

        if (!$place) {
            return response()->json(['message' => 'Place not found'], 404);
        }

        // Otorisasi: Hanya user yang menambahkan atau admin yang bisa mengedit
        if (Auth::id() !== $place->added_by_user_id /*&& !Auth::user()->is_admin*/) {
            return response()->json(['message' => 'Unauthorized to update this place'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'address' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'sometimes|exists:categories,id',
            'latitude' => 'sometimes|numeric',
            'longitude' => 'sometimes|numeric',
            'opening_hours' => 'nullable|string|max:255',
            // --- PERBAIKAN: Validasi untuk file gambar 'main_image' pada update ---
            'main_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            // --- AKHIR PERBAIKAN ---
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // --- PERBAIKAN: Logika penyimpanan gambar untuk update ---
        $updateData = $request->except('main_image'); // Ambil semua data kecuali file gambar

        if ($request->hasFile('main_image')) {
            // Hapus gambar lama jika ada
            if ($place->main_image_url && Storage::disk('public')->exists('images/' . $place->main_image_url)) {
                Storage::disk('public')->delete('images/' . $place->main_image_url);
            }
            // Simpan gambar baru
            $path = $request->file('main_image')->store('images', 'public');
            $updateData['main_image_url'] = basename($path); // Simpan nama file baru
        }
        // --- AKHIR PERBAIKAN ---

        $place->update($updateData);

        return response()->json($place, 200);
    }

    public function destroy($id)
    {
        $place = Place::find($id);

        if (!$place) {
            return response()->json(['message' => 'Place not found'], 404);
        }

        // Otorisasi: Hanya user yang menambahkan atau admin yang bisa menghapus
        if (Auth::id() !== $place->added_by_user_id /*&& !Auth::user()->is_admin*/) {
            return response()->json(['message' => 'Unauthorized to delete this place'], 403);
        }

        // --- PERBAIKAN: Hapus juga file gambar saat tempat dihapus ---
        if ($place->main_image_url && Storage::disk('public')->exists('images/' . $place->main_image_url)) {
            Storage::disk('public')->delete('images/' . $place->main_image_url);
        }
        // --- AKHIR PERBAIKAN ---

        $place->delete();

        return response()->json(['message' => 'Place deleted successfully'], 200);
    }
}
