<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserBelongsToHub
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if ($user && ! in_array($user->role, ['admin', 'inventory_staff'], true)) {
            $requestedHubId = $request->route('hub') ?? $request->route('hub_id') ?? $request->route('hubId') ?? $request->route('id');

            if ($requestedHubId && ! $user->canAccessHub((int) $requestedHubId)) {
                abort(403, 'Unauthorized access to this store hub resource.');
            }
        }

        return $next($request);
    }
}
