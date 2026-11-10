<?php

namespace App\Console\Commands;

use App\Models\Rol;
use App\Models\User;
use Illuminate\Console\Command;

class AssignUserRole extends Command
{
    protected $signature = 'access:assign-role {email} {role}';

    protected $description = 'Asigna un rol existente a una cuenta sin retirar otros roles';

    public function handle(): int
    {
        $roleCode = $this->argument('role');
        if (! array_key_exists($roleCode, config('access.roles'))) {
            $this->error('Rol no válido. Usa administrador, asesor o pasante.');

            return self::FAILURE;
        }
        $user = User::where('email', $this->argument('email'))->first();
        $role = Rol::where('codigo', $roleCode)->first();
        if (! $user || ! $role) {
            $this->error('No se encontró la cuenta o el rol. Verifica el correo y ejecuta AccessSeeder.');

            return self::FAILURE;
        }
        $user->roles()->syncWithoutDetaching([$role->id]);
        $this->info('Rol asignado correctamente.');

        return self::SUCCESS;
    }
}
