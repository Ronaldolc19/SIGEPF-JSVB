<?php

namespace App\Models;

use Database\Factories\PersonaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Persona extends Model
{
    /** @use HasFactory<PersonaFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'personas';

    protected $primaryKey = 'id_persona';

    /** @var list<string> */
    protected $fillable = ['nombre', 'ap', 'am', 'correo', 'id_localidad', 'id_genero', 'id_nacionalidad', 'id_entidad_federativa', 'fecha_nacimiento', 'curp', 'rfc', 'desc_domicilio', 'telefono'];

    protected function casts(): array
    {
        return ['fecha_nacimiento' => 'date:Y-m-d'];
    }

    /** @return HasOne<User, $this> */
    public function usuario(): HasOne
    {
        return $this->hasOne(User::class, 'id_persona', 'id_persona');
    }

    /** @return HasOne<Asesor, $this> */
    public function asesor(): HasOne
    {
        return $this->hasOne(Asesor::class, 'id_persona', 'id_persona');
    }

    /** @return HasMany<PersonalFormacion, $this> */
    public function participaciones(): HasMany
    {
        return $this->hasMany(PersonalFormacion::class, 'id_persona', 'id_persona');
    }
}
