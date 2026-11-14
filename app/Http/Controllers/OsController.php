<?php

namespace App\Http\Controllers;

use App\Models\Os;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OsController extends Controller
{
    // Bloqueia quem não é da matriz (usado em index, edit e updateStatus)
    private function exigirMatriz(): void
    {
        abort_unless(auth()->user()?->tipo === 'matriz', 403);
    }

    // Lista de O.S: a matriz vê todas; a subempresa vê só as da própria unidade (sem poder alterar).
    // As O.S. "Feita" ficam no bloco "Finalizadas", abaixo das ordens em aberto.
    public function index()
    {
        $visiveis = Os::with('unit')->visivelPara(auth()->user());

        return view('os.index', [
            'ordens' => (clone $visiveis)->emAberto()->latest()->get(),
            'finalizadas' => (clone $visiveis)->feitas()->latest('finalizada_em')->latest('id')->get(),
            'ehMatriz' => auth()->user()->ehMatriz(),
        ]);
    }

    // Formulário de registro (matriz e subempresa)
    public function create()
    {
        return view('os.cadastrar');
    }

    // Salvar a O.S (matriz e subempresa)
    public function store(Request $request)
    {
        $dados = $request->validate([
            'solicitante' => ['required', 'string', 'max:255'],
            'maquina' => ['required', 'string', 'max:255'],
            'categoria' => ['required', Rule::in(['maquinario', 'peca'])],
            // O formulário deixa digitar ou escolher da lista, então aceita texto livre
            'tipo_problema' => ['required', 'string', 'max:255'],
            'descricao' => ['required', 'string', 'min:10', 'max:500'],
            'prioridade' => ['required', Rule::in(['Baixa', 'Média', 'Alta', 'Urgente'])],
        ]);

        // O servidor define estes, o formulário não decide
        $dados['status'] = 'Aguardando autorização';
        $dados['user_id'] = auth()->id();
        $dados['unit_id'] = auth()->user()->unit_id; // unidade de quem registrou

        Os::create($dados);

        // Matriz e subempresa voltam para a lista (cada uma vê as O.S. que pode ver)
        return redirect()->route('os.index')->with('sucesso', 'O.S registrada com sucesso.');
    }

    // Tela de edição do status (só matriz)
    public function edit(Os $os)
    {
        $this->exigirMatriz();
        $this->exigirEmAberto($os);

        return view('os.edit', ['os' => $os, 'statusLista' => Os::STATUS]);
    }

    // Salvar o novo status (só matriz). "Feita" finaliza a O.S. e avisa a unidade que a registrou.
    public function updateStatus(Request $request, Os $os)
    {
        $this->exigirMatriz();
        $this->exigirEmAberto($os);

        $dados = $request->validate([
            'status' => ['required', Rule::in(Os::STATUS)],
        ], [
            'status.in' => 'Escolha um dos status da lista.',
        ]);

        $os->mudarStatus($dados['status']);

        $mensagem = $os->estaFeita()
            ? "O.S. #{$os->id} finalizada. Ela foi para o bloco \"Finalizadas\" e a unidade foi avisada."
            : 'Status atualizado.';

        return redirect()->route('os.index')->with('sucesso', $mensagem);
    }

    // O.S. finalizada não muda mais (evita reabrir e repetir o alerta de finalizada)
    private function exigirEmAberto(Os $os): void
    {
        abort_if($os->estaFeita(), 403, 'Esta O.S. já foi finalizada e não pode mais ser alterada.');
    }
}
