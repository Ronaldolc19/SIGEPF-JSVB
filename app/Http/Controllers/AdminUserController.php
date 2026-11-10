<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAdminUserRequest;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AdminUserController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('user-create');
    }

    public function store(StoreAdminUserRequest $request): RedirectResponse
    {
        try {
            $user = DB::transaction(function () use ($request): User {
                $role = Rol::query()->where('codigo', $request->validated('rol'))->first();
                if (! $role) {
                    throw ValidationException::withMessages(['rol' => 'Este rol aún no está configurado en el sistema.']);
                }
                $user = User::create($request->safe()->only(['name', 'email', 'password']));
                $user->roles()->attach($role);

                return $user;
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['email' => 'Este correo ya tiene una cuenta.']);
        }

        return to_route('usuarios.persona.edit', $user)->with('status', 'Cuenta creada. Ahora puedes registrar sus datos personales o vincular una persona existente.');
    }
}
