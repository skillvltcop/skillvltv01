<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Auth;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

final class NewPasswordController
{
    public function create(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:254'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                ])->setRememberToken(Str::random(60));

                $user->save();

                event(new PasswordReset($user));
            },
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with(
                'status',
                match (app()->getLocale()) {
                    'ar' => 'تم تغيير كلمة المرور. يمكنك تسجيل الدخول الآن.',
                    'fr' => 'Votre mot de passe a été modifié. Vous pouvez vous connecter.',
                    default => 'Your password has been reset. You can now sign in.',
                },
            );
        }

        return back()->withErrors([
            'email' => match ($status) {
                Password::INVALID_TOKEN => match (app()->getLocale()) {
                    'ar' => 'رابط إعادة التعيين غير صالح أو انتهت صلاحيته.',
                    'fr' => 'Ce lien de réinitialisation est invalide ou a expiré.',
                    default => 'This password reset link is invalid or has expired.',
                },
                default => match (app()->getLocale()) {
                    'ar' => 'تعذر إعادة تعيين كلمة المرور. تحقق من البريد الإلكتروني وحاول مجددًا.',
                    'fr' => 'Impossible de réinitialiser le mot de passe. Vérifiez votre adresse e-mail.',
                    default => 'Unable to reset the password. Check the email address and try again.',
                },
            },
        ]);
    }
}
