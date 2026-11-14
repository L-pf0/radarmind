<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Estoque extends Model
{
    protected $fillable = [
        'categoria',
        'codigo',
        'nome',
        'tipo_maquinario',
        'ano',
        'horimetro',
        'modelo_aplicacao',
        'quantidade',
        'quantidade_minima',
        'localizacao',
        'lote',
        'numero_serie',
        'alto_giro',
    ];

    protected $casts = ['alto_giro' => 'boolean'];

    public function movimentacoes()
    {
        return $this->hasMany(Movimentacao::class);
    }

    // para a página de alertas
    public function scopeAbaixoDoMinimo($q)
    {
        return $q->whereColumn('quantidade', '<=', 'quantidade_minima');
    }
}
