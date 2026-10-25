<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password as Broker;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Throwable;

class PasswordResetController extends Controller
{
    public function request(Request $request)
    {
        $this->normalizeEmail($request);
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);
        try {
            Broker::sendResetLink($data);
        } catch (Throwable $exception) {
            // Never reveal account existence or mail transport details to the caller.
            report($exception);
        }

        return response()->json(['message' => 'If an account with that email exists, you will receive a password reset link.'])->header('Cache-Control', 'no-store');
    }

    public function reset(Request $request)
    {
        $this->normalizeEmail($request);
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'token' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:72', 'confirmed', Password::min(12)],
            'id' => ['prohibited'],
            'user_id' => ['prohibited'],
        ]);
        $status = DB::transaction(function () use ($data) {
            // Serialize resets for this account so concurrent requests cannot reuse a token.
            User::where('email', $data['email'])->lockForUpdate()->first();

            return Broker::reset($data, function (User $user, string $password) {
                $user->password = $password;
                $user->setRememberToken(Str::random(60));
                $user->save();
                event(new PasswordReset($user));
            });
        });
        if ($status !== Broker::PASSWORD_RESET) {
            throw ValidationException::withMessages(['token' => ['This password reset link is invalid or expired. Please request a new link.']]);
        }
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Password reset. Sign in with your new password.'])->header('Cache-Control', 'no-store');
    }

    private function normalizeEmail(Request $request): void
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => strtolower(trim($request->input('email')))]);
        }
    }
}
