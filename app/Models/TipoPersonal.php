<?php

namespace App\Models;

use Database\Factories\TipoPersonalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TipoPersonal extends Model
{
    /** @use HasFactory<TipoPersonalFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'tipos_personal';

    protected $primaryKey = 'id_tipo_personal';

    /** @var list<string> */
    protected $fillable = ['nombre'];
}
