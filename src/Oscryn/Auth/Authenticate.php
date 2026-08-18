<?php

namespace Oscryn\Auth;

use Closure;
use Oscryn\Exceptions\HttpException;
use Oscryn\Http\Request;
use Oscryn\Http\Response;
use Oscryn\Routing\Middleware;

class Authenticate implements Middleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::instance()->guest()) {
            throw new HttpException(401, 'Unauthorized', 'You must be logged in to access this resource.');
        }

        return $next($request);
    }
}
