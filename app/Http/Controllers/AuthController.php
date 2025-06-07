<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\PasswordResetToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str; // Tetap gunakan Str jika masih perlu untuk hal lain, tapi tidak untuk OTP
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use App\Mail\ResetPasswordOTP;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'username' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::create([
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'username' => $request->username,
        ]);

        return response()->json(['message' => 'User registered successfully!'], 201);
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $user = Auth::user();
        $token = $user->createToken('authToken')->plainTextToken;

        return response()->json(['token' => $token, 'user' => $user], 200);
    }

    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::where('email', $request->email)->first();

        // --- Perubahan untuk OTP Angka Saja ---
        // Menghasilkan OTP 6 digit angka
        $otp = random_int(100000, 999999); // Menghasilkan angka antara 100000 dan 999999
        $otp = (string) $otp; // Pastikan formatnya string
        // Jika Anda ingin lebih fleksibel dengan panjang, bisa juga:
        // $length = 6;
        // $otp = str_pad(random_int(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
        // --- Akhir Perubahan ---

        $expiresAt = Carbon::now()->addMinutes(10); // OTP berlaku 10 menit

        PasswordResetToken::updateOrCreate(
            ['user_id' => $user->id],
            ['token' => $otp, 'expires_at' => $expiresAt] // Menggunakan $otp yang baru
        );

        // Kirim email OTP
        Mail::to($user->email)->send(new ResetPasswordOTP($otp)); // Mengirim $otp yang baru

        return response()->json(['message' => 'OTP sent to your email.'], 200);
    }

    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'otp' => 'required|string|size:6', // Pastikan validasi ukuran sesuai (misal: size:6 untuk 6 digit)
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::where('email', $request->email)->first();
        $resetToken = PasswordResetToken::where('user_id', $user->id)
            ->where('token', $request->otp)
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if (!$resetToken) {
            return response()->json(['message' => 'Invalid or expired OTP.'], 400);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        $resetToken->delete(); // Hapus token setelah berhasil reset password

        return response()->json(['message' => 'Password has been reset successfully.'], 200);
    }
}
