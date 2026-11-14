{{-- resources/views/components/botao-sair.blade.php --}}
{{-- Botão de sair da conta (canto direito da navbar) + modal de confirmação.
     O logout é POST por segurança (token CSRF). Precisa do Bootstrap JS na página. --}}
@auth
    <div class="rm-logout-wrap">
        <button type="button" class="rm-logout-btn" data-bs-toggle="modal" data-bs-target="#rmLogoutModal"
            title="Sair da conta" aria-label="Sair da conta">
            <x-icone-sair />
            <span class="rm-logout-label" aria-hidden="true">Sair</span>
        </button>
    </div>

    {{-- Modal de confirmação: desce do topo ao clicar no botão --}}
    <div class="modal fade rm-logout-modal" id="rmLogoutModal" tabindex="-1" aria-labelledby="rmLogoutTitulo"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            
            <div class="modal-content">
                <div class="modal-body text-center">
                    <div class="rm-logout-modal-icon">
                        <x-icone-sair />
                    </div>

                    <h2 class="rm-logout-modal-title" id="rmLogoutTitulo">Deseja mesmo sair dessa conta?</h2>
                    <p class="rm-logout-modal-text">
                        Você está conectado como <strong>{{ auth()->user()->email }}</strong>.<br>
                        Para voltar, será preciso fazer login novamente.
                    </p>

                    <form method="POST" action="{{ route('logout') }}" class="rm-logout-modal-actions">
                        @csrf
                        <button type="button" class="btn rm-logout-cancel" data-bs-dismiss="modal">
                            Cancelar
                        </button>
                        <button type="submit" class="btn rm-logout-confirm">
                            <x-icone-sair class="me-1" /> Sim, sair
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endauth
