<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminAuthController extends Controller
{
    public function login(Request $r)
    {
        $data = $r->validate([
            'email'    => ['required','email'],
            'password' => ['required','string','min:6'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Sai email hoặc mật khẩu'], 401);
        }

        if ($user->roles !== 'admin') {
            return response()->json(['message' => 'Không có quyền truy cập admin'], 403);
        }

        $token = $user->createToken('admin', ['admin'])->plainTextToken;

        return response()->json([
            'message' => 'Đăng nhập admin thành công',
            'token'   => $token,
            'user'    => [
                'id'    => $user->id,
                'email' => $user->email,
                'roles' => $user->roles,
            ],
        ]);
    }

    public function me(Request $r)
    {
        $u = $r->user();
        if (!$u) return response()->json(['message'=>'Unauthenticated'], 401);
        if ($u->roles !== 'admin') return response()->json(['message'=>'Forbidden'], 403);

        return response()->json([
            'id'    => $u->id,
            'name'  => $u->name,
            'email' => $u->email,
            'roles' => $u->roles,
        ]);
    }

    public function logout(Request $r)
    {
        if ($r->user()?->currentAccessToken()) {
            $r->user()->currentAccessToken()->delete();
        }
        return response()->json(['ok' => true]);
    }
}
