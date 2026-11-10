<?php

namespace Database\Factories;

use App\Models\Persona;
use App\Models\PersonalFormacion;
use App\Models\TipoPersonal;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PersonalFormacion> */
class PersonalFormacionFactory extends Factory
{
    protected $model = PersonalFormacion::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['id_persona' => Persona::factory(), 'id_tipo_personal' => TipoPersonal::factory(), 'fecha_ingreso' => today(), 'fecha_termino' => null, 'estado' => 'En formacion'];
    }
}
