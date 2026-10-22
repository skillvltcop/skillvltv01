<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Auth;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

final class PasswordResetLinkController
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email', 'max:254'],
        ]);

        // Keep the response identical for known and unknown addresses.
        Password::sendResetLink($request->only('email'));

        return back()->with(
            'status',
            match (app()->getLocale()) {
                'ar' => 'إذا كان البريد مسجلًا لدينا، فستصلك رسالة تتضمن رابط إعادة تعيين كلمة المرور.',
                'fr' => 'Si cette adresse est enregistrée, vous recevrez un lien de réinitialisation.',
                default => 'If that email is registered, you will receive a password reset link.',
            },
        );
    }
}
