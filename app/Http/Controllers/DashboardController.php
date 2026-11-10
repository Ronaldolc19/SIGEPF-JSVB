<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        foreach (array_keys(config('access.roles')) as $role) {
            if ($request->user()->hasRole($role)) {
                return to_route($role.'.dashboard');
            }
        }

        return Inertia::render('role-dashboard', ['role' => null]);
    }

    public function administrador(): Response
    {
        return Inertia::render('dashboard');
    }

    public function asesor(): Response
    {
        return Inertia::render('role-dashboard', ['role' => 'asesor']);
    }

    public function pasante(): Response
    {
        return Inertia::render('role-dashboard', ['role' => 'pasante']);
    }
}
