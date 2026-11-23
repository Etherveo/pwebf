<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Server-side role authorization check running after auth middleware.
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(403);
        }

        // A user without a matching role must never pass, whatever guard.
        if (! isset($user->role) || ! in_array($user->role, $roles, true)) {
            abort(403);
        }

        return $next($request);
    }
}
