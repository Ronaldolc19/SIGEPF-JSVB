<?php

namespace Database\Factories;

use App\Models\Asesor;
use App\Models\Persona;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Asesor> */
class AsesorFactory extends Factory
{
    protected $model = Asesor::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['id_persona' => Persona::factory()];
    }
}
