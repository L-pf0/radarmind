@extends(config('radarmind.layout'))
@section('title', 'Editar item · RadarMind')

@section('content')
    <div class="rm-estoque">
        <h1>Editar item</h1>
        <p class="sub">{{ $item->sku }} · {{ $item->name }}</p>
        <section class="card">
            <form method="POST" action="{{ route('radarmind.inventory.update', $item) }}">
                @csrf
                @method('PUT')
                {{-- Mesmos campos e regras do cadastro --}}
                @include('radarmind.inventory.partials.campos-item', ['item' => $item])
                <div class="actions">
                    <button class="btn" type="submit">Salvar alterações</button>
                    <a class="btn ghost" href="{{ route('radarmind.inventory.index') }}">Cancelar</a>
                </div>
            </form>
        </section>
    </div>
@endsection
