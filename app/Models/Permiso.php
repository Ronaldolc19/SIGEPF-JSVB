<?php

namespace App\Models;

use Database\Factories\PermisoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permiso extends Model
{
    /** @use HasFactory<PermisoFactory> */
    use HasFactory;

    protected $table = 'permisos';

    /** @var list<string> */
    protected $fillable = ['codigo', 'nombre', 'descripcion'];

    /** @return BelongsToMany<Rol, $this> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Rol::class, 'rol_permiso', 'permiso_id', 'rol_id')->withTimestamps();
    }
}
