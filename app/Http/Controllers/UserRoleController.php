<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateUserRolesRequest;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UserRoleController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('user-roles', [
            'users' => User::query()->select('id', 'name', 'email', 'id_persona')->with(['roles:id,codigo,nombre', 'persona:id_persona,nombre,ap,am'])->orderBy('name')->paginate(20),
            'roles' => Rol::query()->whereIn('codigo', array_keys(config('access.roles')))->with('permisos:id,codigo,nombre')->get(['id', 'codigo', 'nombre']),
            'modules' => config('access.modules'),
            'roleModules' => config('access.role_modules'),
            'status' => $request->session()->get('status'),
        ]);
    }

    public function update(UpdateUserRolesRequest $request, User $user): RedirectResponse
    {
        $codes = $request->validated('roles');
        if ($user->is($request->user()) && ! in_array('administrador', $codes, true)) {
            throw ValidationException::withMessages(['roles' => 'No puedes retirar tu propio rol de administrador.']);
        }

        $user->roles()->sync(Rol::query()->whereIn('codigo', $codes)->pluck('id'));

        return to_route('usuarios.index')->with('status', 'Roles actualizados. Se aplicarán en la siguiente solicitud del usuario.');
    }
}
