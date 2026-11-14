<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\RadarMind\Unit;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

// 'tipo' foi acrescentado: sem ele, o seeder não consegue gravar o campo
// 'unit_id' = unidade da subempresa (a matriz fica sem unidade)
#[Fillable(['name', 'email', 'password', 'tipo', 'unit_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

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

    // Atalho: o usuário é da matriz? (usado no login e nas rotas)
    public function ehMatriz(): bool
    {
        return $this->tipo === 'matriz';
    }

    // Unidade do usuário: a da subempresa ou a "Matriz" (código MAT) para a matriz
    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    // Para cadastrar estoque, movimentações, O.S. e pedidos o usuário precisa ter unidade
    public function unidadeObrigatoria(): Unit
    {
        abort_if($this->unidade === null, 403, 'Este usuário não está vinculado a nenhuma unidade.');

        return $this->unidade;
    }
}
