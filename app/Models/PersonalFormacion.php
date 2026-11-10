<?php

namespace App\Models;

use Database\Factories\PersonalFormacionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PersonalFormacion extends Model
{
    /** @use HasFactory<PersonalFormacionFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'personal_formacion';

    protected $primaryKey = 'id_personal';

    /** @var list<string> */
    protected $fillable = ['id_persona', 'id_tipo_personal', 'fecha_ingreso', 'fecha_termino', 'estado', 'edad_constancia', 'id_promocion', 'motivo_periodo'];

    /** @return BelongsTo<Persona, $this> */
    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'id_persona', 'id_persona');
    }

    /** @return BelongsTo<TipoPersonal, $this> */
    public function tipo(): BelongsTo
    {
        return $this->belongsTo(TipoPersonal::class, 'id_tipo_personal', 'id_tipo_personal');
    }

    /** @return HasMany<AsignacionAsesor, $this> */
    public function asignaciones(): HasMany
    {
        return $this->hasMany(AsignacionAsesor::class, 'id_personal', 'id_personal');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['fecha_ingreso' => 'date:Y-m-d', 'fecha_termino' => 'date:Y-m-d'];
    }

    /** @param Builder<PersonalFormacion> $query
     * @return Builder<PersonalFormacion>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $query->whereHas('persona');
        if ($user->hasRole('administrador')) {
            return $query;
        }
        if (! $user->can('personal.ver') || ! $user->persona()->exists()) {
            return $query->whereRaw('1 = 0');
        }
        $modules = $user->modulesByRole();
        $advisor = in_array('personal', $modules['asesor'], true);
        $trainee = in_array('personal', $modules['pasante'], true);

        return $query->where(function (Builder $visible) use ($user, $advisor, $trainee): void {
            $visible->whereRaw('1 = 0');
            if ($trainee) {
                $visible->orWhere('personal_formacion.id_persona', $user->id_persona);
            }
            if ($advisor) {
                $visible->orWhereHas('asignaciones', function (Builder $assignments) use ($user): void {
                    $assignments->vigentes()->whereHas('asesor', fn (Builder $advisors): Builder => $advisors->where('id_persona', $user->id_persona));
                });
            }
        });
    }
}
