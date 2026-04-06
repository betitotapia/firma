<?php


namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureCan
{
    public function handle(Request $request, Closure $next, string $perm)
    {
        $user = $request->user();

        if (!$user) abort(403);

        $map = [
            'send' => 'can_send',
            'delete' => 'can_delete',
            'manage_users' => 'can_manage_users',
        ];

        $field = $map[$perm] ?? null;
        if (!$field || !$user->{$field}) {
            abort(403, 'No tienes permiso para realizar esta acción.');
        }

        return $next($request);
    }
}