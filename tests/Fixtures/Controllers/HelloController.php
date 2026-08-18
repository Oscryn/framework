<?php

declare(strict_types=1);

namespace Tests\Fixtures\Controllers;

use Oscryn\Http\Controller;
use Oscryn\Http\Response;

class HelloController extends Controller
{
    public function index(): Response
    {
        return new Response('hello from controller');
    }

    public function greet(string $name = 'world'): Response
    {
        return new Response('hello '.$name);
    }
}
