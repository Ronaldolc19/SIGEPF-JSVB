<?php

namespace App\Models;

use Database\Factories\AsesorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Asesor extends Model
{
    /** @use HasFactory<AsesorFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'asesores';

    protected $primaryKey = 'id_asesor';

    /** @var list<string> */
    protected $fillable = ['id_persona'];

    /** @return BelongsTo<Persona, $this> */
    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'id_persona', 'id_persona');
    }

    /** @return HasMany<AsignacionAsesor, $this> */
    public function asignaciones(): HasMany
    {
        return $this->hasMany(AsignacionAsesor::class, 'id_asesor', 'id_asesor');
    }
}
