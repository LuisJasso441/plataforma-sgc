<?php

namespace App\Models;

use App\Enums\NcActionStatus;
use App\Enums\NcOrigin;
use App\Enums\NcStage;
use App\Enums\NcStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class NonConformity extends Model
{
    protected $fillable = [
        'folio', 'folio_year', 'folio_number', 'parent_id',
        'issued_by', 'department_id', 'nc_process_id', 'nc_subprocess_id', 'leader_id', 'origin',
        'initial_description', 'description', 'report_data',
        'stage', 'status',
        'trigger_date', 'commitment_date', 'verification_date', 'actual_close_date',
        'is_effective', 'observations', 'lessons_learned',
        'accepted_by', 'accepted_at', 'closed_by', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'origin'            => NcOrigin::class,
            'stage'             => NcStage::class,
            'status'            => NcStatus::class,
            'trigger_date'      => 'date',
            'commitment_date'   => 'date',
            'verification_date' => 'date',
            'actual_close_date' => 'date',
            'is_effective'      => 'boolean',
            'report_data'       => 'array',
            'accepted_at'       => 'datetime',
            'closed_at'         => 'datetime',
        ];
    }

    /* ───────────── Relaciones ───────────── */

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** NC nuevas abiertas a partir de ésta (cuando fue No efectiva) */
    public function reopenings(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function process(): BelongsTo
    {
        return $this->belongsTo(NcProcess::class, 'nc_process_id');
    }

    public function subprocess(): BelongsTo
    {
        return $this->belongsTo(NcSubprocess::class, 'nc_subprocess_id');
    }

    public function leader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'leader_id');
    }

    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(NcAction::class)->orderBy('number');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(NcAttachment::class, 'attachable');
    }

    /** Versiones del reporte Excel, la más reciente primero */
    public function reports(): MorphMany
    {
        return $this->attachments()
            ->where('type', NcAttachment::TYPE_REPORTE)
            ->latest();
    }

    public function affectedProcesses(): BelongsToMany
    {
        return $this->belongsToMany(NcProcess::class, 'nc_affected_process');
    }

    public function logs(): HasMany
    {
        // id como desempate: eventos registrados en el mismo segundo
        return $this->hasMany(NcLog::class)->latest()->latest('id');
    }

    /* ───────────── Visibilidad ───────────── */

    /**
     * Ven la NC: Soporte, Calidad, el emisor, el líder y el departamento responsable.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin() || $user->isCalidad()) {
            return $query;
        }

        $departmentIds = $user->visibleDepartmentIds();

        return $query->where(function (Builder $q) use ($user, $departmentIds) {
            $q->where('issued_by', $user->id)
              ->orWhere('leader_id', $user->id);

            if ($departmentIds) {
                $q->orWhereIn('department_id', $departmentIds);
            }
        });
    }

    public function isVisibleTo(User $user): bool
    {
        return $user->isAdmin()
            || $user->isCalidad()
            || $this->issued_by === $user->id
            || $this->leader_id === $user->id
            || in_array($this->department_id, $user->visibleDepartmentIds(), true);
    }

    /* ───────────── Folio ───────────── */

    /**
     * Crea una NC asignando el folio AC-YY-NN de forma segura (bloqueo de fila).
     */
    public static function createWithFolio(array $attributes, ?Carbon $date = null): self
    {
        return DB::transaction(function () use ($attributes, $date) {
            $year = (int) ($date ?? now())->format('Y');

            DB::table('nc_folio_sequences')->insertOrIgnore([
                'year'        => $year,
                'last_number' => 0,
            ]);

            $sequence = DB::table('nc_folio_sequences')
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            $number = $sequence->last_number + 1;

            DB::table('nc_folio_sequences')
                ->where('year', $year)
                ->update(['last_number' => $number]);

            return self::create(array_merge($attributes, [
                'folio_year'   => $year,
                'folio_number' => $number,
                'folio'        => sprintf('AC-%02d-%02d', $year % 100, $number),
                'stage'        => $attributes['stage'] ?? NcStage::Solicitada,
                'status'       => $attributes['status'] ?? NcStatus::Abierta,
            ]));
        });
    }

    /* ───────────── Cálculos automáticos ───────────── */

    /**
     * Implementación = última fecha compromiso; verificación = +1 mes;
     * fecha real de cierre = última fecha real, solo si TODAS las acciones están validadas.
     */
    public function refreshDates(): void
    {
        $actions = $this->actions()->get();

        $commitment = $actions->max('commitment_date');

        $this->commitment_date   = $commitment;
        $this->verification_date = $commitment
            ? Carbon::parse($commitment)->addMonthNoOverflow()
            : null;

        $allValidated = $actions->isNotEmpty()
            && $actions->every(fn (NcAction $a) => $a->status === NcActionStatus::Validada);

        $this->actual_close_date = $allValidated ? $actions->max('actual_end_date') : null;

        $this->save();
    }

    /**
     * Estatus de bitácora: Cerrada / No efectiva por etapa;
     * Vencida si alguna acción no validada pasó su fecha compromiso; si no, Abierta.
     */
    public function refreshStatus(): void
    {
        $this->status = match ($this->stage) {
            NcStage::Cerrada    => NcStatus::Cerrada,
            NcStage::NoEfectiva => NcStatus::NoEfectiva,
            default => $this->actions()
                ->where('status', '!=', NcActionStatus::Validada->value)
                ->whereDate('commitment_date', '<', today())
                ->exists() ? NcStatus::Vencida : NcStatus::Abierta,
        };

        $this->save();
    }

    /* ───────────── Bitácora (línea de tiempo) ───────────── */

    public function log(
        string $event,
        ?string $comment = null,
        ?NcStage $from = null,
        ?NcStage $to = null,
        array $meta = [],
        ?User $user = null,
    ): NcLog {
        return $this->logs()->create([
            'user_id'    => $user?->id ?? auth()->id(),
            'event'      => $event,
            'from_stage' => $from?->value,
            'to_stage'   => $to?->value,
            'comment'    => $comment,
            'meta'       => $meta ?: null,
        ]);
    }
}