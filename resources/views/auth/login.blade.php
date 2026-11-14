{{-- resources/views/auth/login.blade.php --}}
@extends('layouts.appsemlinks')

@section('title', 'Login')

{{-- CSS extra só desta página (Font Awesome, usado no ícone do Perfil Matriz) --}}
@push('styles')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
@endpush

@section('content')
    {{-- A navbar sem links já vem do layout appsemlinks --}}
    <div class="row g-0">

        {{-- Painel esquerdo: marca --}}
        <div class="col-lg-5 rm-left-panel d-flex align-items-center justify-content-center">
            <div class="text-center px-4" style="max-width: 380px;">
                <div class="rm-logo-mark">
                    <img src="{{ asset('images/logoCortada_RadarMind.png') }}" alt="Logo RadarMind">
                </div>
                <div class="rm-logo-title">
                    <span class="text-navy">Radar</span><span class="text-green">Mind</span>
                </div>
                <p class="rm-tagline mb-0">
                    Gestão inteligente e integrada para um futuro mais eficiente
                </p>
            </div>
        </div>

        {{-- Painel direito: formulário --}}
        <div class="col-lg-7 rm-right-panel">
            <div class="mb-4">
                <h1 class="rm-welcome-title mb-1">Login</h1>
                <p class="rm-welcome-subtitle mb-0">Para efetuar o login é necessário seu e-mail e chave de acesso...</p>
            </div>

            @if ($errors->any())
                <div class="alert alert-danger" role="alert">
                    @foreach ($errors->all() as $erro)
                        <div>{{ $erro }}</div>
                    @endforeach
                </div>
            @endif

            <form action="{{ route('login.autenticar') }}" method="POST" id="loginForm">
                @csrf

                <div class="row g-3 mb-4">
                    <div class="col-sm-6">
                        <label class="w-100 h-100">
                            <input type="radio" name="perfil" value="matriz" class="d-none" checked>
                            <div class="rm-profile-card is-selected" data-profile="matriz">
                                <div class="rm-profile-icon icon-admin">
                                    <i class="fa-brands fa-web-awesome"></i>
                                </div>
                                <div class="rm-profile-title">Perfil Matriz</div>

                                <ul class="rm-profile-list">
                                    <li>Funcionalidades:</li>
                                    <li><i class="bi bi-check2"></i> Visualiza todas as unidades</li>
                                    <li><i class="bi bi-check2"></i> Relatórios consolidados</li>
                                    <li><i class="bi bi-check2"></i> Controle geral de alertas e operações</li>
                                </ul>
                            </div>
                        </label>
                    </div>

                    <div class="col-sm-6">
                        <label class="w-100 h-100">
                            <input type="radio" name="perfil" value="subempresa" class="d-none">
                            <div class="rm-profile-card" data-profile="subempresa">
                                <div class="rm-profile-icon icon-sub">
                                    <i class="bi bi-buildings-fill"></i>
                                </div>
                                <div class="rm-profile-title">Perfil Subempresas</div>

                                <ul class="rm-profile-list">
                                    <li>Funcionalidades:</li>
                                    <li><i class="bi bi-check2"></i> Gestão da própria unidade</li>
                                    <li><i class="bi bi-check2"></i> Controle de estoque e movimentações</li>
                                    <li><i class="bi bi-check2"></i> Registro de O.S. facilitado</li>
                                </ul>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="row g-3 mb-2">
                    <div class="col-md-6">
                        <label for="email" class="rm-form-label mb-1">E-mail:</label>
                        <div class="input-group rm-input-group border">
                            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                            <input type="email" class="form-control" id="email" name="email"
                                value="{{ old('email') }}" placeholder="seu@email.com" required autofocus>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label for="password" class="rm-form-label mb-1">Chave de acesso:</label>
                        <div class="input-group rm-input-group border">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input type="password" class="form-control" id="password" name="password"
                                placeholder="••••••••">
                            <span class="input-group-text bg-white" style="cursor:pointer;border-left:none;"
                                id="togglePassword">
                                <i class="bi bi-eye"></i>
                            </span>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-rm-submit w-100">Acessar</button>
            </form>
        </div>
    </div>
@endsection

{{-- JS só desta página (o Bootstrap JS já é carregado pelo layout) --}}
@push('scripts')
    <script>
        // Seleção visual do cartão de perfil (Matriz / Subempresa)
        document.querySelectorAll('.rm-profile-card').forEach(function (card) {
            card.addEventListener('click', function () {
                document.querySelectorAll('.rm-profile-card').forEach(function (c) {
                    c.classList.remove('is-selected');
                });
                card.classList.add('is-selected');
                card.closest('label').querySelector('input[type="radio"]').checked = true;
            });
        });

        // Mostrar/ocultar senha
        const toggle = document.getElementById('togglePassword');
        const senha = document.getElementById('password');
        toggle.addEventListener('click', function () {
            const isPassword = senha.getAttribute('type') === 'password';
            senha.setAttribute('type', isPassword ? 'text' : 'password');
            toggle.querySelector('i').classList.toggle('bi-eye');
            toggle.querySelector('i').classList.toggle('bi-eye-slash');
        });
    </script>
@endpush