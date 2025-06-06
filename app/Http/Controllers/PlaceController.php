<?php

namespace App\Http\Controllers;

use App\Models\Place;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage; // Untuk upload gambar

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

        $places = $query->with('category', 'reviews')->get(); // Load relasi

        return response()->json($places, 200);
    }

    public function show($id)
    {
        $place = Place::with('category', 'reviews.user')->find($id); // Load relasi

        if (!$place) {
            return response()->json(['message' => 'Place not found'], 404);
        }

        return response()->json($place, 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'required|exists:categories,id',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'opening_hours' => 'nullable|string|max:255',
            'main_image_url' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $place = Place::create([
            'name' => $request->name,
            'address' => $request->address,
            'description' => $request->description,
            'category_id' => $request->category_id,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'opening_hours' => $request->opening_hours,
            'main_image_url' => $request->main_image_url,
            'added_by_user_id' => Auth::id(), // Pengguna yang sedang login
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
            'main_image_url' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $place->update($request->all());

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

        $place->delete();

        return response()->json(['message' => 'Place deleted successfully'], 200);
    }
}
