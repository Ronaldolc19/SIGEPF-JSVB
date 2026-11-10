<?php

namespace Database\Factories;

use App\Models\Permiso;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Permiso> */
class PermisoFactory extends Factory
{
    protected $model = Permiso::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'codigo' => fake()->unique()->bothify('permiso_########'),
            'nombre' => fake()->words(3, true),
            'descripcion' => null,
        ];
    }
}
