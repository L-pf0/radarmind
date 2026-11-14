<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Movimentacao extends Model
{
    protected $table = 'movimentacoes';

    protected $fillable = ['estoque_id', 'tipo', 'quantidade', 'unidade', 'destino', 'os_id', 'user_id'];

    public function estoque()
    {
        return $this->belongsTo(Estoque::class);
    }

    public function os()
    {
        return $this->belongsTo(Os::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
