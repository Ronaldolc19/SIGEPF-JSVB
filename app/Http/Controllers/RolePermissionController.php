<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateRolePermissionsRequest;
use App\Models\Permiso;
use App\Models\Rol;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class RolePermissionController extends Controller
{
    public function update(UpdateRolePermissionsRequest $request, Rol $rol): RedirectResponse
    {
        abort_unless(in_array($rol->codigo, ['asesor', 'pasante'], true), 403);
        DB::transaction(function () use ($request, $rol): void {
            $role = Rol::query()->lockForUpdate()->findOrFail($rol->id);
            $ids = [];
            foreach ($request->validated('modulos') as $module) {
                foreach (config('access.role_modules.'.$role->codigo.'.'.$module) as $action) {
                    $ids[] = Permiso::firstOrCreate(['codigo' => $module.'.'.$action], [
                        'nombre' => ucfirst($action).' · '.config('access.modules.'.$module.'.nombre'),
                    ])->id;
                }
            }
            $role->permisos()->sync($ids);
        });

        return to_route('usuarios.index')->with('status', 'Módulos del rol actualizados. Se aplican a todas sus cuentas en la siguiente solicitud.');
    }
}
