{{-- Notificação de novos alertas no canto inferior esquerdo (todas as telas internas, matriz e subempresa) --}}
@auth
    <link rel="stylesheet" href="@assetVersionado('css/alertas-toast.css')">

    <div id="rm-toasts" class="rm-toasts" aria-live="polite" aria-atomic="false"
        data-url-novos="{{ route('alertas.novos') }}" data-url-central="{{ route('alertas.index') }}"></div>

    <script src="@assetVersionado('js/alertas-toast.js')" defer></script>
@endauth
