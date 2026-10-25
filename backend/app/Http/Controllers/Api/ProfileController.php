<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password as Broker;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function update(Request $request)
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => strtolower(trim($request->input('email')))]);
        }
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'current_password' => ['nullable', 'string'],
            'id' => ['prohibited'],
            'user_id' => ['prohibited'],
        ]);
        DB::transaction(function () use ($user, $data) {
            $account = $user->newQuery()->lockForUpdate()->findOrFail($user->id);
            if ($data['email'] !== $account->email) {
                $this->checkPassword($data['current_password'] ?? '', $account->password);
                Broker::deleteToken($account);
                $account->email_verified_at = null;
            }
            $account->fill(['name' => $data['name'], 'email' => $data['email']])->save();
        });

        return response()->json(['user' => $user->fresh(), 'message' => 'Profile updated.'])->header('Cache-Control', 'no-store');
    }

    public function password(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'max:72', 'confirmed', Password::min(12)],
            'id' => ['prohibited'],
            'user_id' => ['prohibited'],
        ]);
        $hash = DB::transaction(function () use ($request, $data) {
            $user = $request->user()->newQuery()->lockForUpdate()->findOrFail($request->user()->id);
            $this->checkPassword($data['current_password'], $user->password);
            $user->password = $data['password'];
            $user->setRememberToken(Str::random(60));
            $user->save();
            Broker::deleteToken($user);

            return $user->password;
        });
        $request->session()->regenerate();
        $request->session()->put('auth_password_hash', $hash);

        return response()->json(['message' => 'Password changed. Other sessions have been signed out.'])->header('Cache-Control', 'no-store');
    }

    private function checkPassword(string $password, string $hash): void
    {
        if (! Hash::check($password, $hash)) {
            throw ValidationException::withMessages(['current_password' => ['The current password is incorrect.']]);
        }
    }
}
