<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\Place;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class ReviewController extends Controller
{
    public function index($place_id)
    {
        $place = Place::find($place_id);

        if (!$place) {
            return response()->json(['message' => 'Place not found'], 404);
        }

        $reviews = $place->reviews()->with('user')->get(); // Load relasi user

        return response()->json($reviews, 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'place_id' => 'required|exists:places,id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Pastikan user hanya bisa mereview satu kali per tempat
        $existingReview = Review::where('place_id', $request->place_id)
            ->where('user_id', Auth::id())
            ->first();

        if ($existingReview) {
            return response()->json(['message' => 'You have already reviewed this place.'], 409);
        }

        $review = Review::create([
            'place_id' => $request->place_id,
            'user_id' => Auth::id(),
            'rating' => $request->rating,
            'comment' => $request->comment,
        ]);

        return response()->json($review, 201);
    }

    public function update(Request $request, $id)
    {
        $review = Review::find($id);

        if (!$review) {
            return response()->json(['message' => 'Review not found'], 404);
        }

        // Otorisasi: Hanya pemilik ulasan yang bisa mengedit
        if (Auth::id() !== $review->user_id) {
            return response()->json(['message' => 'Unauthorized to update this review'], 403);
        }

        $validator = Validator::make($request->all(), [
            'rating' => 'sometimes|integer|min:1|max:5',
            'comment' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $review->update($request->all());

        return response()->json($review, 200);
    }

    public function destroy($id)
    {
        $review = Review::find($id);

        if (!$review) {
            return response()->json(['message' => 'Review not found'], 404);
        }

        // Otorisasi: Hanya pemilik ulasan yang bisa menghapus
        if (Auth::id() !== $review->user_id) {
            return response()->json(['message' => 'Unauthorized to delete this review'], 403);
        }

        $review->delete();

        return response()->json(['message' => 'Review deleted successfully'], 200);
    }
}
