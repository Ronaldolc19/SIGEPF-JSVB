<?php

namespace App;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use stdClass;

class Catalogo
{
    /** @return array{title: string, group: string, key: string, label: string, fields: array<string, array<string, mixed>>, dependents: list<array{string, string}>} */
    public static function definition(string $catalogo): array
    {
        $definitions = config('catalogos');
        abort_unless(array_key_exists($catalogo, $definitions), 404);

        return $definitions[$catalogo];
    }

    public static function query(string $catalogo): Builder
    {
        self::definition($catalogo);

        return DB::table($catalogo)->whereNull($catalogo.'.deleted_at');
    }

    public static function find(string $catalogo, int $registro, bool $lock = false): stdClass
    {
        $definition = self::definition($catalogo);
        $query = self::query($catalogo)->where($definition['key'], $registro);
        if ($lock) {
            $query->lockForUpdate();
        }
        $row = $query->first();
        abort_unless($row, 404);

        return $row;
    }

    public static function optionsQuery(string $catalogo): Builder
    {
        $definition = self::definition($catalogo);
        $query = self::query($catalogo)->select($catalogo.'.'.$definition['key'].' as id', $catalogo.'.'.$definition['label'].' as label');
        if ($catalogo === 'localidades') {
            $query->leftJoin('municipios', 'municipios.id_municipio', '=', 'localidades.id_municipio')
                ->addSelect('municipios.nom_mun as context');
        }
        if ($catalogo === 'municipios') {
            $query->leftJoin('entidades_federativas', 'entidades_federativas.id_entidad_federativa', '=', 'municipios.id_entidad_federativa')
                ->addSelect('entidades_federativas.nombre as context');
        }
        if ($catalogo === 'unidades_medicas') {
            $query->addSelect('unidades_medicas.clave_clues as context');
        }
        if ($catalogo === 'programas') {
            $query->addSelect('programas.clave_programa as context');
        }
        if ($catalogo === 'instituciones') {
            $query->addSelect('instituciones.clave_institucion as context');
        }

        return $query->orderBy($catalogo.'.'.$definition['label'])->orderBy($catalogo.'.'.$definition['key']);
    }
}
