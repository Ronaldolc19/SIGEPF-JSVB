<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /** @return BelongsToMany<Rol, $this> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Rol::class, 'usuario_rol', 'user_id', 'rol_id')->withTimestamps();
    }

    public function hasRole(string $codigo): bool
    {
        return $this->roles()->where('codigo', $codigo)->exists();
    }

    /** @return BelongsTo<Persona, $this> */
    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'id_persona', 'id_persona');
    }

    public function hasPermission(string $codigo): bool
    {
        [$module, $action] = array_pad(explode('.', $codigo, 2), 2, null);
        $eligibleRoles = [];
        foreach (config('access.role_modules') as $role => $modules) {
            if (in_array($action, $modules[$module] ?? [], true)) {
                $eligibleRoles[] = $role;
            }
        }

        return $this->roles()->whereIn('codigo', $eligibleRoles)
            ->whereHas('permisos', fn ($query) => $query->where('codigo', $module.'.ver'))
            ->whereHas('permisos', fn ($query) => $query->where('codigo', $codigo))->exists();
    }

    /** @return array<string, list<string>> */
    public function modulesByRole(): array
    {
        $roles = $this->roles()->with('permisos')->get();
        $isAdmin = $roles->contains('codigo', 'administrador');
        $result = [];
        foreach (array_keys(config('access.roles')) as $code) {
            $available = $code === 'administrador'
                ? array_keys(config('access.modules'))
                : array_keys(config('access.role_modules.'.$code, []));
            $role = $roles->firstWhere('codigo', $code);
            $result[$code] = $isAdmin ? $available : array_values(array_filter(
                $available,
                fn (string $module): bool => $role !== null && $role->permisos->contains('codigo', $module.'.ver'),
            ));
        }

        return $result;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
