<?php

namespace App;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

class ProgramacionProductividad
{
    /** @param list<int> $people */
    public static function forPeople(array $people, ?string $date = null): Builder
    {
        $query = DB::table('programacion_productividad as pp')
            ->join('personal_formacion as pf', 'pf.id_promocion', '=', 'pp.id_promocion')
            ->join('datos_academicos as da', function (JoinClause $join): void {
                $join->on('da.id_personal', '=', 'pf.id_personal')->on('da.id_carrera', '=', 'pp.id_carrera');
            })
            ->whereIn('pf.id_personal', $people)->whereNull('pf.deleted_at')->whereNull('da.deleted_at')
            ->whereColumn('pf.fecha_ingreso', '<=', 'pp.fecha_fin')
            ->where(fn (Builder $q): Builder => $q->whereNull('pf.fecha_termino')->orWhereColumn('pf.fecha_termino', '>=', 'pp.fecha_inicio'))
            ->whereExists(function (Builder $ads) use ($date): void {
                $ads->selectRaw('1')->from('adscripciones_personal as ads')->whereColumn('ads.id_personal', 'pf.id_personal')
                    ->whereColumn('ads.id_unidad', 'pp.id_unidad')->whereNull('ads.deleted_at');
                if ($date) {
                    $ads->where('ads.fecha_inicio', '<=', $date)->where(fn (Builder $q): Builder => $q->whereNull('ads.fecha_fin')->orWhere('ads.fecha_fin', '>=', $date));
                } else {
                    $ads->whereColumn('ads.fecha_inicio', '<=', 'pp.fecha_fin')
                        ->where(fn (Builder $q): Builder => $q->whereNull('ads.fecha_fin')->orWhereColumn('ads.fecha_fin', '>=', 'pp.fecha_inicio'));
                }
            });
        if ($date) {
            $query->where('pp.fecha_inicio', '<=', $date)->where('pp.fecha_fin', '>=', $date);
        }

        return $query->select('pp.*', 'pf.id_personal');
    }
}
