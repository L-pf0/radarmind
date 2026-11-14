{{-- Card "Logado em Dashboard ...": mostra em qual painel o usuário está.
     Aparece no topo de todas as telas internas (layouts) e no cabeçalho dos dashboards.
     Matriz: "Logado em Dashboard Matriz" | Subempresa: "Logado em Dashboard <nome da unidade>" --}}
@auth
    @php($usuario = auth()->user())
    <p {{ $attributes->class(['rm-local-tag', 'mb-0']) }}>
        <i class="bi {{ $usuario->ehMatriz() ? 'bi-building-fill-check' : 'bi-buildings-fill' }}" aria-hidden="true"></i>
        Logado em Dashboard {{ $usuario->ehMatriz() ? 'Matriz' : ($usuario->unidade?->name ?? 'Subempresa') }}
    </p>
@endauth
