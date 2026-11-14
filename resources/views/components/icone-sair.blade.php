{{-- resources/views/components/icone-sair.blade.php --}}
{{-- Ícone de sair: porta aberta, batente e seta vazada. Usa a cor do texto (currentColor). --}}
<svg {{ $attributes->merge(['class' => 'rm-icone-sair']) }} viewBox="110 165 760 650" fill="none" stroke="currentColor"
    stroke-width="44" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
    {{-- porta --}}
    <path d="M140 235 L380 197 L380 783 L140 745 Z" />
    {{-- maçaneta --}}
    <circle cx="315" cy="490" r="22" />
    {{-- batente (cantos de cima e de baixo) --}}
    <path d="M455 197 H555 V330" />
    <path d="M555 650 V783 H455" />
    {{-- seta saindo --}}
    <path class="rm-icone-sair-seta" d="M495 440 H700 V385 L835 490 L700 595 V540 H495 Z" />
</svg>
