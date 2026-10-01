<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable // implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'role',
        'department_id',
        'active',
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
            'active' => 'boolean',
        ];
    }

    /**
     * Departamento/área al que pertenece el usuario.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Módulos a los que el usuario tiene acceso, con sus niveles de permiso.
     */
    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class)
            ->withPivot(['can_read', 'can_create', 'can_edit'])
            ->withTimestamps();
    }

    /**
     * ¿Es administrador de plataforma (Sistemas)?
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * ¿Es del área de Calidad (control administrativo del SGA)?
     */
    public function isCalidad(): bool
    {
        return $this->role === 'calidad';
    }

    public function roleLabel(): string
    {
        return match ($this->role) {
            'admin'   => 'Soporte',
            'calidad' => 'Calidad',
            default   => 'Jefe de Departamento',
        };
    }

    /**
     * Comprueba si el usuario tiene cierto nivel de permiso sobre un módulo.
     * Los administradores (Sistemas) tienen acceso total por su rol.
     *
     * @param  string  $moduleKey  clave estable del módulo (ej. 'no-conformidad')
     * @param  string  $ability    'read' | 'create' | 'edit'
     */
    public function hasModuleAccess(string $moduleKey, string $ability = 'read'): bool
    {
        // Un usuario inactivo no tiene acceso a nada
        if (! $this->active) {
            return false;
        }

        // El admin puede todo, sin mirar la matriz
        if ($this->isAdmin()) {
            return true;
        }

        $module = $this->modules->firstWhere('key', $moduleKey);

        if (! $module) {
            return false; // Sin fila = sin acceso
        }

        return match ($ability) {
            'create' => (bool) $module->pivot->can_create,
            'edit'   => (bool) $module->pivot->can_edit,
            default  => (bool) $module->pivot->can_read,
        };
    }

    /**
     * Atajos legibles.
     */
    public function canRead(string $moduleKey): bool
    {
        return $this->hasModuleAccess($moduleKey, 'read');
    }

    public function canCreate(string $moduleKey): bool
    {
        return $this->hasModuleAccess($moduleKey, 'create');
    }

    public function canEdit(string $moduleKey): bool
    {
        return $this->hasModuleAccess($moduleKey, 'edit');
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->map(fn (string $name) => Str::of($name)->substr(0, 1))
            ->implode('');
    }

    public function headedDepartments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Department::class, 'head_user_id');
    }

    public function isDepartmentHead(): bool
    {
        return $this->headedDepartments()->exists();
    }

    /**
     * IDs de departamentos cuya información ve este usuario:
     * su propio depto + los que encabeza + los subdepartamentos de éstos.
     * Ej.: el jefe de COMERCIAL también ve VENTAS.
     *
     * @return list<int>
     */
    public function visibleDepartmentIds(): array
    {
        return once(function () {
            $headed = $this->headedDepartments()->pluck('id');

            $children = $headed->isEmpty()
                ? collect()
                : Department::whereIn('parent_id', $headed)->pluck('id');

            return collect([$this->department_id])
                ->merge($headed)
                ->merge($children)
                ->filter()
                ->unique()
                ->values()
                ->all();
        });
    }

}