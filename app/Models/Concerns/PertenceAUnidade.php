<?php

namespace App\Models\Concerns;

use App\Models\RadarMind\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Regras de acesso por unidade (coluna unit_id), usadas por estoque, movimentações,
 * O.S. e pedidos de reposição:
 * - a matriz VÊ os registros de todas as unidades;
 * - a subempresa vê só os da própria unidade;
 * - cada um só ALTERA os registros da própria unidade.
 */
trait PertenceAUnidade
{
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Item::visivelPara($user)->get(): tudo para a matriz, só a própria unidade para a subempresa.
     */
    public function scopeVisivelPara(Builder $query, User $user): void
    {
        if (! $user->ehMatriz()) {
            $query->where($this->qualifyColumn('unit_id'), $user->unit_id);
        }
    }

    public function podeSerVistoPor(User $user): bool
    {
        return $user->ehMatriz() || $this->pertenceAUnidadeDe($user);
    }

    public function pertenceAUnidadeDe(User $user): bool
    {
        return $this->unit_id !== null && (int) $this->unit_id === (int) $user->unit_id;
    }
}
