<?php

namespace Database\Factories;

use App\Models\Rol;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Rol> */
class RolFactory extends Factory
{
    protected $model = Rol::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'codigo' => fake()->unique()->bothify('rol_########'),
            'nombre' => fake()->words(3, true),
            'descripcion' => null,
        ];
    }
}
