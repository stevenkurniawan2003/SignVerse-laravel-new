<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Landing page publik SignTech via jembatan Inertia.js
 */
class LandingController extends Controller
{
    public function __invoke(Request $request): Response
    {
        return Inertia::render('Landing');
    }
}
