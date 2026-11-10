<?php

namespace App\Models;

use Database\Factories\AsignacionAsesorFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AsignacionAsesor extends Model
{
    /** @use HasFactory<AsignacionAsesorFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'asigna_asesores';

    protected $primaryKey = 'id_asigna_asesor';

    /** @var list<string> */
    protected $fillable = ['id_asesor', 'id_personal', 'fecha_asignacion', 'fecha_fin', 'motivo_fin'];

    /** @return BelongsTo<Asesor, $this> */
    public function asesor(): BelongsTo
    {
        return $this->belongsTo(Asesor::class, 'id_asesor', 'id_asesor');
    }

    /** @return BelongsTo<PersonalFormacion, $this> */
    public function personal(): BelongsTo
    {
        return $this->belongsTo(PersonalFormacion::class, 'id_personal', 'id_personal');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['fecha_asignacion' => 'date', 'fecha_fin' => 'date'];
    }

    /** @param Builder<AsignacionAsesor> $query
     * @return Builder<AsignacionAsesor>
     */
    public function scopeVigentes(Builder $query): Builder
    {
        $today = today()->toDateString();

        return $query->whereDate('fecha_asignacion', '<=', $today)
            ->where(fn (Builder $dates): Builder => $dates->whereNull('fecha_fin')->orWhereDate('fecha_fin', '>=', $today));
    }
}
