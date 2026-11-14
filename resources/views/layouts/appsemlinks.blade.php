{{-- resources/views/layouts/appsemlinks.blade.php --}}
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

    {{-- CSS global do projeto --}}
    <link rel="stylesheet" href="@assetVersionado('css/app.css')">

    {{-- CSS específico de cada página --}}
    @stack('styles')
</head>

<body>

    {{-- Navbar SEM links (mesmo layout, só a marca) --}}
    <x-navbar :menu="false" />

    <main>
        @yield('content')
    </main>

    {{-- Bootstrap JS --}}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    {{-- JS específico de cada página --}}
    @stack('scripts')
</body>

</html>