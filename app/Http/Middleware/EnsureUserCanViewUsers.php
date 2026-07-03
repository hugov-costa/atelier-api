<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserCanViewUsers
{
    /**
     * Allow only users that may read other accounts (admin or master) through.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->canViewUsers()) {
            abort(Response::HTTP_FORBIDDEN, 'This action requires elevated privileges.');
        }

        return $next($request);
    }
}
