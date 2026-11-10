<?php

namespace Database\Factories;

use App\Models\Asesor;
use App\Models\AsignacionAsesor;
use App\Models\PersonalFormacion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AsignacionAsesor> */
class AsignacionAsesorFactory extends Factory
{
    protected $model = AsignacionAsesor::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['id_asesor' => Asesor::factory(), 'id_personal' => PersonalFormacion::factory(), 'fecha_asignacion' => today(), 'fecha_fin' => null];
    }
}
