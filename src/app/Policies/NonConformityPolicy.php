<?php

namespace App\Policies;

use App\Enums\NcActionStatus;
use App\Enums\NcStage;
use App\Models\NcAction;
use App\Models\NonConformity;
use App\Models\User;

/**
 * Permisos del módulo No Conformidad.
 * - Calidad: ve y edita todo, en cualquier etapa.
 * - Soporte (admin): ve todo (soporte técnico), pero no toma decisiones de Calidad.
 * - Jefe de Departamento: ve lo suyo / de su depto; edita SOLO cuando el flujo se lo pide.
 */
class NonConformityPolicy
{
    private const MODULE = 'no-conformidad';

    /** Decisiones de Calidad (aceptar, devolver, revisar, verificar, cerrar) */
    private function isQuality(User $user): bool
    {
        return $user->active && $user->isCalidad();
    }

    private function isLeader(User $user, NonConformity $nc): bool
    {
        return $nc->leader_id === $user->id;
    }

    /* ───────────── Consulta ───────────── */

    public function viewAny(User $user): bool
    {
        return $this->isQuality($user) || $user->canRead(self::MODULE);
    }

    public function view(User $user, NonConformity $nc): bool
    {
        return $this->viewAny($user) && $nc->isVisibleTo($user);
    }

    /* ───────────── Levantamiento ───────────── */

    public function create(User $user): bool
    {
        return $this->isQuality($user) || $user->canCreate(self::MODULE);
    }

    /** El emisor corrige su solicitud cuando Calidad se la devolvió */
    public function correct(User $user, NonConformity $nc): bool
    {
        return $this->isQuality($user)
            || ($nc->issued_by === $user->id && $nc->stage === NcStage::DevueltaEmisor);
    }

    /* ───────────── Acciones del líder ───────────── */

    public function uploadReport(User $user, NonConformity $nc): bool
    {
        return $this->isQuality($user)
            || ($this->isLeader($user, $nc)
                && in_array($nc->stage, [NcStage::PendienteReporte, NcStage::ReporteDevuelto], true));
    }

    public function manageActions(User $user, NonConformity $nc): bool
    {
        return $this->isQuality($user)
            || ($this->isLeader($user, $nc) && $nc->stage === NcStage::CapturaAcciones);
    }

    /** Uso: Gate::allows('uploadEvidence', [$nc, $action]) */
    public function uploadEvidence(User $user, NonConformity $nc, NcAction $action): bool
    {
        if ($action->non_conformity_id !== $nc->id) {
            return false;
        }

        return $this->isQuality($user)
            || ($this->isLeader($user, $nc)
                && $nc->stage === NcStage::EnImplementacion
                && in_array($action->status, [NcActionStatus::Pendiente, NcActionStatus::Rechazada], true));
    }

    /* ───────────── Acciones de Calidad ───────────── */

    /** Aceptar/devolver solicitud, revisar reporte y evidencias, verificar efectividad */
    public function review(User $user, NonConformity $nc): bool
    {
        return $this->isQuality($user);
    }

    /** Edición libre en cualquier etapa (solo Calidad) */
    public function update(User $user, NonConformity $nc): bool
    {
        return $this->isQuality($user);
    }
}