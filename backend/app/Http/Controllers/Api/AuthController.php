<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        $user = User::where('email', $data['email'])->first();
        if (!$user || !Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Email atau kata sandi salah.'], 422);
        }
        if (!$user->is_active) return response()->json(['message' => 'Akun dinonaktifkan. Hubungi Super Admin.'], 403);
        $token = Str::random(64);
        $user->update(['api_token' => hash('sha256', $token)]);
        return response()->json(['token' => $token, 'user' => $user->load('competition')]);
    }

    public function me(Request $request) { return $request->user()->load('competition'); }
    public function logout(Request $request) { $request->user()->update(['api_token' => null]); return response()->json(['message' => 'Berhasil keluar.']); }
}
