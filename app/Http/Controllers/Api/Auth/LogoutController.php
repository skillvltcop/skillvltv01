<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\TransientToken;

final class LogoutController
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        $accessToken = $user->currentAccessToken();

        if ($accessToken instanceof TransientToken) {
            // Stateful Sanctum requests authenticate through the web session,
            // so revoking a personal access token does not log that session out.
            Auth::guard('web')->logout();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
        } elseif ($accessToken !== null) {
            $accessToken->delete();
        }

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }
}
