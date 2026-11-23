<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

// Status is checked on every authenticated request: deactivated accounts
// are logged out and redirected back to /masuk with a clear error message.
class EnsureActiveAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->is_active === false) {
            Auth::guard('web')->logout();
            $request->session()->regenerate();

            $login = $request->is('admin/*') || $request->is('admin')
                ? route('masuk', ['aktor' => 'admin_toko'])
                : route('masuk');

            return redirect($login)->withErrors([
                'email' => 'Akun Anda nonaktif. Silakan hubungi Admin Toko.',
            ]);
        }

        return $next($request);
    }
}
