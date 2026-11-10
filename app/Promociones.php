<?php

namespace App;

use App\Models\PersonalFormacion;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class Promociones
{
    public static function calendarOptions(Request $request): Collection
    {
        if ($request->attributes->has('promotion_calendar_options')) {
            return $request->attributes->get('promotion_calendar_options');
        }
        $user = $request->user();
        $query = DB::table('promociones')->whereNull('deleted_at');
        if (! $user->hasRole('administrador')) {
            $people = PersonalFormacion::query()->whereHas('persona');
            if ($user->hasRole('pasante')) {
                $people->where('id_persona', $user->id_persona ?? 0);
            } else {
                $people->visibleTo($user);
            }
            $query->whereIn('id_promocion', $people->select('id_promocion'));
        }
        $rows = $query->orderByDesc('fecha_inicio')->orderByDesc('id_promocion')->get(['id_promocion', 'nombre', 'fecha_inicio', 'fecha_fin', 'estado']);
        $request->attributes->set('promotion_calendar_options', $rows);

        return $rows;
    }

    /** @return list<string> */
    public static function selections(Request $request): array
    {
        $modes = $request->user()->hasRole('administrador') ? ['vigentes', 'todas', 'sin', 'prorrogas'] : ['todas', 'sin'];

        return [...$modes, ...self::calendarOptions($request)->pluck('id_promocion')->map(fn ($id): string => (string) $id)->all()];
    }

    public static function sessionKey(Request $request): string
    {
        return 'promocion_consulta.'.$request->user()->id;
    }

    public static function selection(Request $request): string
    {
        $options = self::calendarOptions($request);
        $default = $request->user()->hasRole('administrador') ? 'vigentes' : ($request->user()->hasRole('pasante') && $options->count() === 1 ? (string) $options->first()->id_promocion : 'todas');
        $selected = (string) $request->session()->get(self::sessionKey($request), $default);
        if (! in_array($selected, self::selections($request), true)) {
            $request->session()->forget(self::sessionKey($request));

            return $default;
        }

        return $selected;
    }

    public static function calendar(Request $request): ?array
    {
        $user = $request->user();
        if (! $user || (! $user->hasRole('administrador') && ! $user->hasRole('pasante'))) {
            return null;
        }
        $selected = self::selection($request);
        $options = self::calendarOptions($request);
        $isAdmin = $user->hasRole('administrador');
        $labels = ['vigentes' => 'Seguimiento actual', 'todas' => $isAdmin ? 'Todas las promociones' : 'Mis promociones', 'sin' => 'Sin promoción asignada', 'prorrogas' => 'Periodos extendidos'];
        $people = $isAdmin ? collect() : PersonalFormacion::query()->whereHas('persona')->where('id_persona', $user->id_persona ?? 0)
            ->orderByDesc('fecha_ingreso')->get(['id_personal', 'id_promocion', 'fecha_ingreso', 'fecha_termino', 'estado']);

        return [
            'selected' => $selected,
            'label' => $labels[$selected] ?? $options->firstWhere('id_promocion', (int) $selected)?->nombre,
            'options' => $options, 'isAdmin' => $isAdmin, 'participaciones' => $people,
        ];
    }

    public static function extended(): Builder
    {
        return DB::table('personal_formacion as extended')->join('promociones as generation', 'generation.id_promocion', '=', 'extended.id_promocion')
            ->whereNull('extended.deleted_at')->whereNull('generation.deleted_at')->where('extended.estado', 'En formacion')
            ->whereColumn('extended.fecha_termino', '>', 'generation.fecha_fin')->select('extended.id_personal');
    }

    public static function options(?int $current = null, bool $legacy = false): Collection
    {
        $query = DB::table('promociones')->whereNull('deleted_at');
        if (! $legacy) {
            $query->where(fn (Builder $q): Builder => $q->where(fn (Builder $open): Builder => $open->where('permite_inscripciones', true)->where('estado', '!=', 'Cerrada'))->orWhere('id_promocion', $current));
        }
        $rows = $query->orderByDesc('fecha_inicio')->get();
        $careers = DB::table('promocion_carrera as pc')->join('carreras as c', 'c.id_carrera', '=', 'pc.id_carrera')
            ->whereIn('pc.id_promocion', $rows->pluck('id_promocion'))->whereNull('c.deleted_at')
            ->select('pc.id_promocion', 'c.id_carrera', 'c.nombre')->orderBy('c.nombre')->get()->groupBy('id_promocion');

        return $rows->each(fn (object $row) => $row->carreras = $careers->get($row->id_promocion, collect())->values());
    }

    /** Validate within the caller's transaction before changing participation data. */
    public static function validateParticipation(PersonalFormacion $personal, ?int $id, int $career, string $start, ?string $end, ?string $reason): void
    {
        if ($personal->id_promocion && (int) $personal->id_promocion !== $id) {
            throw ValidationException::withMessages(['id_promocion' => 'La promoción de esta participación se conserva. Una prórroga no cambia su generación.']);
        }
        if (! $id) {
            return;
        }
        $promotion = DB::table('promociones')->where('id_promocion', $id)->whereNull('deleted_at')->lockForUpdate()->first();
        if (! $promotion || (! $personal->exists && (! $promotion->permite_inscripciones || $promotion->estado === 'Cerrada'))) {
            throw ValidationException::withMessages(['id_promocion' => 'Esta promoción no admite nuevas incorporaciones.']);
        }
        if (! DB::table('promocion_carrera')->where('id_promocion', $id)->where('id_carrera', $career)->exists()) {
            throw ValidationException::withMessages(['id_carrera' => 'La carrera no está habilitada en esta promoción.']);
        }
        $changed = $personal->exists && ($start !== $personal->fecha_ingreso?->format('Y-m-d') || $end !== $personal->fecha_termino?->format('Y-m-d'));
        if (($changed || $start !== $promotion->fecha_inicio || $end !== $promotion->fecha_fin) && ! trim($reason ?? '')) {
            throw ValidationException::withMessages(['motivo_periodo' => 'Indica el motivo de la prórroga o del periodo individual diferente al programado.']);
        }
    }

    public static function recordPeriod(PersonalFormacion $personal, ?string $beforeStart, ?string $beforeEnd, ?int $beforePromotion, int $user): void
    {
        $start = $personal->fecha_ingreso?->format('Y-m-d');
        $end = $personal->fecha_termino?->format('Y-m-d');
        if ($personal->id_promocion && ($start !== $beforeStart || $end !== $beforeEnd || $beforePromotion !== (int) $personal->id_promocion)) {
            DB::table('historial_periodos_personal')->insert([
                'id_personal' => $personal->id_personal, 'id_usuario' => $user, 'id_promocion' => $personal->id_promocion,
                'inicio_anterior' => $beforeStart, 'termino_anterior' => $beforeEnd,
                'fecha_inicio' => $start, 'fecha_termino' => $end, 'motivo' => $personal->motivo_periodo,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    /** Filters narrow the authorized query without replacing its access scope. */
    public static function filter(Request $request, Builder|EloquentBuilder $query, string $column = 'personal_formacion.id_promocion'): void
    {
        $allowed = self::calendarOptions($request);
        $data = $request->validate(['promocion' => ['nullable', Rule::in(self::selections($request))]]);
        $selected = (string) ($data['promocion'] ?? self::selection($request));
        if (isset($data['promocion'])) {
            $request->session()->put(self::sessionKey($request), $selected);
        }
        $personalColumn = str_replace('id_promocion', 'id_personal', $column);
        if ($selected === 'vigentes') {
            $ids = $allowed->where('estado', 'En curso')->pluck('id_promocion');
            $query->where(fn ($q) => $q->whereIn($column, $ids)->orWhereNull($column)->orWhereIn($personalColumn, self::extended()));
        } elseif ($selected === 'prorrogas') {
            $query->whereIn($personalColumn, self::extended());
        } elseif ($selected === 'sin') {
            $query->whereNull($column);
        } elseif ($selected !== 'todas') {
            $query->where($column, (int) $selected);
        }
    }
}
