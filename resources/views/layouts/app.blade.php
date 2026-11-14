{{-- resources/views/layouts/app.blade.php --}}
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

    {{-- CSS específico de cada página --}}
    @stack('styles')

    {{-- Tema visual dos slides do RadarMind; vem por último para prevalecer (a fonte Karla vem do app.css) --}}
    <link rel="stylesheet" href="@assetVersionado('css/tema-slide.css')">
</head>

<body>

    {{-- Navbar COM links --}}
    <x-navbar :active-route="$activeRoute ?? null" :menu="true" />

    {{-- rm-tema-slide: aplica o visual dos slides só nas telas internas (não afeta login nem navbar) --}}
    <main class="rm-tema-slide">
        {{-- Em qual painel o usuário está (matriz ou subempresa), em todas as telas.
             Os dashboards mostram o card no próprio cabeçalho (seção "sessao-no-conteudo"). --}}
        @sectionMissing('sessao-no-conteudo')
            <div class="rm-sessao-barra rm-dashboard container-fluid px-3 px-lg-4">
                <x-sessao-atual />
            </div>
        @endif

        @yield('content')
    </main>

    {{-- Notificação de novos alertas (canto inferior esquerdo) --}}
    <x-alertas-toast />

    {{-- Bootstrap JS --}}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    {{-- JS específico de cada página --}}
    @stack('scripts')
</body>

</html>