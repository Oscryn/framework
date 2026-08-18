<?php

namespace Oscryn\Routing;

use Closure;
use Oscryn\Http\Request;
use Oscryn\Http\Response;

interface Middleware
{
    public function handle(Request $request, Closure $next): Response;
}
