<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasCompany
{
    /**
     * Every authenticated section of the app is scoped to a company, so a
     * user with no company yet is redirected to create one before they can
     * reach anything else.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->company_id && ! $request->routeIs('company.create', 'company.store', 'logout')) {
            return redirect()->route('company.create');
        }

        return $next($request);
    }
}
