<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    protected $fillable = [
        'name',
        'active',
        'head_user_id',
        'parent_id',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function head(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'head_user_id');
    }

    public function parent(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Department::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Department::class, 'parent_id');
    }

    /**
     * Jefe efectivo: el propio, o el del departamento padre si es subdepartamento.
     * Ej.: VENTAS hereda el jefe de COMERCIAL.
     */
    public function effectiveHead(): ?User
    {
        return $this->head ?? $this->parent?->head;
    }

    /**
     * Recalcula head_user_id a partir de un usuario que cambió.
     * Reglas:
     *  - Solo un Jefe (role=user) activo de un departamento RAÍZ ejerce jefatura.
     *  - Un usuario de subdepartamento (Ventas) nunca es jefe: su área hereda
     *    el jefe del padre (Comercial) vía effectiveHead().
     *  - Si un departamento pierde a su jefe, se busca otro Jefe activo del área.
     *  - Un segundo Jefe no desplaza a un jefe vigente.
     */
    public static function syncHeadFor(User $user): void
    {
        // Departamento donde este usuario DEBE ser jefe (o null si no le corresponde)
        $target = null;

        if ($user->role === 'user' && $user->active && $user->department_id) {
            $dept = static::find($user->department_id);

            if ($dept && ! $dept->parent_id) {
                $target = $dept;
            }
        }

        // 1) Liberar jefaturas que ya no le corresponden y buscar reemplazo
        $released = static::where('head_user_id', $user->id)
            ->when($target, fn ($q) => $q->where('id', '!=', $target->id))
            ->get();

        foreach ($released as $dept) {
            $dept->update(['head_user_id' => null]);
            $dept->refillHead();
        }

        // 2) Tomar la jefatura de su departamento solo si está vacante
        if ($target && ! $target->head_user_id) {
            $target->update(['head_user_id' => $user->id]);
        }
    }

    /**
     * Asigna como jefe al primer Jefe activo del departamento (si lo hay).
     * Los subdepartamentos nunca tienen jefe propio.
     */
    public function refillHead(): void
    {
        if ($this->parent_id) {
            $this->update(['head_user_id' => null]);
            return;
        }

        $candidate = User::where('role', 'user')
            ->where('active', true)
            ->where('department_id', $this->id)
            ->orderBy('id')
            ->first();

        $this->update(['head_user_id' => $candidate?->id]);
    }

}