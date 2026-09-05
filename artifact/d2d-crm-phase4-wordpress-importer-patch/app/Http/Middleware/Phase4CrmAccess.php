<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class Phase4CrmAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->guest(route('login'));
        }

        $allowed = ['admin', 'content-admin', 'super-admin'];

        try {
            if (Schema::hasTable('roles') && Schema::hasTable('role_user')) {
                $columns = Schema::getColumnListing('roles');
                $roleColumn = in_array('slug', $columns, true) ? 'slug' : (in_array('name', $columns, true) ? 'name' : null);

                if ($roleColumn) {
                    $hasRole = DB::table('roles')
                        ->join('role_user', 'roles.id', '=', 'role_user.role_id')
                        ->where('role_user.user_id', $user->id)
                        ->whereIn('roles.'.$roleColumn, $allowed)
                        ->exists();

                    if ($hasRole) {
                        return $next($request);
                    }
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        abort(403, 'CRM admin access is required.');
    }
}
