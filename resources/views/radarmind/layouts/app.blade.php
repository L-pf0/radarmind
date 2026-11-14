{{-- resources/views/radarmind/layouts/app.blade.php --}}
{{-- Layout das telas do módulo operacional: mesma navbar e CSS do sistema + CSS do módulo isolado em .rm-modulo --}}
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'RadarMind - Agronegócio')</title>

    {{-- Bootstrap 5 --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    {{-- Bootstrap Icons --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    {{-- Font Awesome (ícones de trator e chave do estoque; mesma versão usada no login) --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    {{-- CSS global do projeto --}}
    <link rel="stylesheet" href="@assetVersionado('css/app.css')">
    {{-- CSS do módulo operacional --}}
    <link rel="stylesheet" href="@assetVersionado('css/radarmind.css')">

    @stack('styles')

    {{-- Tema visual dos slides do RadarMind; vem por último para prevalecer (a fonte Karla vem do app.css) --}}
    <link rel="stylesheet" href="@assetVersionado('css/tema-slide.css')">
</head>

<body>

    {{-- Navbar COM links (mesmo componente do restante do sistema) --}}
    <x-navbar :menu="true" />

    {{-- rm-tema-slide: aplica o visual dos slides só nas telas internas (não afeta login nem navbar) --}}
    <main class="rm-tema-slide rm-dashboard container-fluid px-3 px-lg-4 py-4">

        {{-- Em qual painel o usuário está (matriz ou subempresa), em todas as telas --}}
        <div class="rm-sessao-barra-modulo">
            <x-sessao-atual />
        </div>

        <div class="rm-toolbar d-flex flex-wrap align-items-center justify-content-between gap-3">
            <a href="{{ route('home') }}" class="rm-panel-header-link"><i class="bi bi-arrow-left"></i>
                <span>Voltar<span class="d-none d-sm-inline"> ao dashboard</span></span></a>
            {{-- Nas telas de relatórios o usuário já está lá: o botão não aparece --}}
            @unless (request()->routeIs('radarmind.reports.*'))
                <a href="{{ route('radarmind.reports.index') }}" class="btn btn-rm-submit rm-header-btn">
                    <i class="bi bi-file-earmark-text"></i> Emitir relatórios
                </a>
            @endunless
        </div>

        <div class="rm-flash">
            @if (session('success'))
                <div class="alert alert-success rm-form-alert" role="alert">
                    <i class="bi bi-check-circle-fill me-1"></i> {{ session('success') }}
                </div>
            @endif

            @if (session('error'))
                <div class="alert alert-danger rm-form-alert" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i> {{ session('error') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger rm-form-alert" role="alert">
                    <ul class="mb-0 ps-3">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        <div class="rm-modulo">
            @yield('content')
        </div>
    </main>

    {{-- Notificação de novos alertas (canto inferior esquerdo) --}}
    <x-alertas-toast />

    {{-- Bootstrap JS --}}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    {{-- JS do módulo operacional --}}
    <script src="@assetVersionado('js/radarmind.js')"></script>

    @stack('scripts')
</body>

</html>
