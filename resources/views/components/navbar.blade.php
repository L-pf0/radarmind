{{-- resources/views/components/navbar.blade.php --}}

{{-- Props:
     $activeRoute = nome da rota atual (para destacar o link)
     $menu        = mostra ou não os links (false só na tela de login)

     O menu é SEMPRE o mesmo em todas as páginas: só muda conforme o perfil logado
     (matriz ou subempresa), por causa das permissões de acesso de cada um.
     Nenhuma página deve passar links ou textos próprios para cá. --}}
@props(['activeRoute' => null, 'menu' => true])

@php
    // Links da navbar. O campo 'rota' deve ser igual ao ->name() em routes/web.php ou routes/radarmind.php
    // 'ativo' = padrão de rotas que deixam o link destacado
    // Matriz e subempresa usam as mesmas páginas: cada uma mostra só o que o perfil pode ver/alterar
    // (a matriz vê todas as unidades; a subempresa só a dela). O "Dashboard" leva ao painel de cada perfil.
    $links = [
        ['rota' => 'home', 'ativo' => ['matriz', 'subempresa'], 'texto' => 'Dashboard', 'icone' => 'bi-speedometer2'],
        ['rota' => 'radarmind.inventory.index', 'ativo' => 'radarmind.inventory.*', 'texto' => 'Estoque', 'icone' => 'bi-box-seam'],
        ['rota' => 'radarmind.movements.index', 'ativo' => 'radarmind.movements.*', 'texto' => 'Movimentações', 'icone' => 'bi-arrow-left-right'],
        ['rota' => 'alertas.index', 'ativo' => 'alertas.*', 'texto' => 'Alertas', 'icone' => 'bi-bell'],
        ['rota' => 'pedidos-reposicao.index', 'ativo' => 'pedidos-reposicao.*', 'texto' => 'Pedidos de reposição', 'icone' => 'bi-cart3'],
        ['rota' => 'os.index', 'ativo' => 'os.*', 'texto' => 'Registros de O.S.', 'icone' => 'bi-tools'],
    ];
@endphp

{{-- navbar-expand-xl: a partir de 1200px os links ficam na linha; abaixo disso viram um menu que abre no botão ☰ --}}
<nav class="rm-topbar navbar navbar-expand-xl" aria-label="Navegação principal">
    {{-- Marca: aparece sempre --}}
    <a href="{{ route('home') }}" class="rm-brand-link d-flex align-items-center gap-2 text-decoration-none">
        <span class="rm-brand-icon">
            <img src="{{ asset('images/logoCortada_RadarMind.png') }}" alt="Logo RadarMind">
        </span>
        <span class="rm-brand-text">
            <span class="rm-title d-block">RadarMind</span>
            <span class="rm-subtitle d-block">GERÊNCIA DO AGRONEGÓCIO</span>
        </span>
    </a>

    {{-- Links: só aparecem quando $menu for true --}}
    @if ($menu)
        <button class="rm-nav-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#rmNavMenu"
            aria-controls="rmNavMenu" aria-expanded="false" aria-label="Abrir menu de navegação">
            <i class="bi bi-list rm-nav-toggler-open" aria-hidden="true"></i>
            <i class="bi bi-x-lg rm-nav-toggler-close" aria-hidden="true"></i>
        </button>

        <div class="collapse navbar-collapse rm-nav-collapse" id="rmNavMenu">
            <ul class="rm-nav nav">
                @foreach ($links as $link)
                    @php
                        $ativo = $activeRoute === $link['rota'] || request()->routeIs(...(array) $link['ativo']);
                    @endphp
                    <li class="nav-item">
                        {{-- Se a rota ainda não existir, o link vai para '#' em vez de dar erro --}}
                        <a href="{{ Route::has($link['rota']) ? route($link['rota']) : '#' }}"
                            class="{{ $ativo ? 'active' : '' }}" @if ($ativo) aria-current="page" @endif>
                            <i class="bi {{ $link['icone'] }}" aria-hidden="true"></i>
                            <span>{{ $link['texto'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Sair da conta: só aparece para quem está logado, sempre no canto direito --}}
    <x-botao-sair />
</nav>
