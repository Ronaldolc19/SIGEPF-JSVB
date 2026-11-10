<?php

namespace Database\Seeders;

use App\Models\Rol;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(AccessSeeder::class);
        $email = config('access.admin.email');
        if (! $email) {
            $this->command?->warn('Configura SIGEPF_ADMIN_EMAIL y SIGEPF_ADMIN_PASSWORD en .env para crear el administrador.');

            return;
        }
        $data = Validator::make([
            'name' => config('access.admin.name'),
            'email' => $email,
        ], ['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255']])->validate();

        DB::transaction(function () use ($data): void {
            $user = User::where('email', $data['email'])->first();
            if (! $user) {
                $credentials = Validator::make(['password' => config('access.admin.password')], [
                    'password' => ['required', 'string', 'min:12'],
                ])->validate();
                $user = User::create([...$data, 'password' => $credentials['password']]);
            }
            $role = Rol::where('codigo', 'administrador')->firstOrFail();
            $user->roles()->syncWithoutDetaching([$role->id]);
        });
        $this->command?->info('Administrador preparado. Las credenciales de cuentas existentes no se modifican.');
    }
}
