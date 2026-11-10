<?php

namespace Database\Factories;

use App\Models\TipoPersonal;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TipoPersonal> */
class TipoPersonalFactory extends Factory
{
    protected $model = TipoPersonal::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['nombre' => 'Servicio social'];
    }
}
