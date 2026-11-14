{{-- Campos do item de estoque, usados no cadastro e na edição. Recebe $item (null no cadastro).
     * = obrigatório (mesmas regras do InventoryController::validateItem).
     Tipo, modelo, ano e horímetro só aparecem para maquinário (js/radarmind.js, data-for). --}}
@php
    $item ??= null;
    $valor = fn (string $campo, mixed $padrao = null) => old($campo, $item?->{$campo} ?? $padrao);
    $categoria = $valor('category', 'peca');
@endphp

<p class="rm-obrigatorios-legenda"><span class="rm-obrigatorio" aria-hidden="true">*</span> Campos obrigatórios</p>

<div class="fields">
    <div>
        <label for="cat-sel">Categoria <span class="rm-obrigatorio" aria-hidden="true">*</span></label>
        <select id="cat-sel" name="category" required @class(['is-invalid' => $errors->has('category')])>
            <option value="peca" @selected($categoria === 'peca')>Peça avulsa</option>
            <option value="maq" @selected($categoria === 'maq')>Maquinário inteiro</option>
        </select>
        @error('category')<p class="rm-campo-erro">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="item-sku">Código/SKU <span class="rm-obrigatorio" aria-hidden="true">*</span></label>
        <input id="item-sku" name="sku" value="{{ $valor('sku') }}" placeholder="Ex.: PEC-0103" required maxlength="50"
            @class(['is-invalid' => $errors->has('sku')])>
        @error('sku')<p class="rm-campo-erro">{{ $message }}</p>@enderror
    </div>
    <div class="span-all">
        <label for="item-nome">Nome <span class="rm-obrigatorio" aria-hidden="true">*</span></label>
        <input id="item-nome" name="name" value="{{ $valor('name') }}" placeholder="Ex.: Filtro de óleo hidráulico" required
            maxlength="160" @class(['is-invalid' => $errors->has('name')])>
        @error('name')<p class="rm-campo-erro">{{ $message }}</p>@enderror
    </div>

    {{-- Só maquinário (opcionais) --}}
    <div data-for="maq">
        <label for="item-tipo">Tipo <span class="rm-opcional">opcional</span></label>
        <select id="item-tipo" name="machine_type">
            @foreach (['Trator', 'Colheitadeira', 'Pulverizador', 'Plantadeira', 'Outro'] as $tipo)
                <option @selected($valor('machine_type') === $tipo)>{{ $tipo }}</option>
            @endforeach
        </select>
    </div>
    <div data-for="maq">
        <label for="item-modelo">Modelo/aplicação <span class="rm-opcional">opcional</span></label>
        <input id="item-modelo" name="model_application" value="{{ $valor('model_application') }}" placeholder="Ex.: Modelo A-75, preparo de solo">
    </div>
    <div data-for="maq">
        <label for="item-ano">Ano <span class="rm-opcional">opcional</span></label>
        <input id="item-ano" name="year" type="number" value="{{ $valor('year') }}" placeholder="2026" @class(['is-invalid' => $errors->has('year')])>
        @error('year')<p class="rm-campo-erro">{{ $message }}</p>@enderror
    </div>
    <div data-for="maq">
        <label for="item-horimetro">Horímetro (h) <span class="rm-opcional">opcional</span></label>
        <input id="item-horimetro" name="hour_meter" type="number" step="0.1" min="0" value="{{ $valor('hour_meter') }}" placeholder="0"
            @class(['is-invalid' => $errors->has('hour_meter')])>
        @error('hour_meter')<p class="rm-campo-erro">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="item-local">Localização física <span class="rm-obrigatorio" aria-hidden="true">*</span></label>
        <input id="item-local" name="location" value="{{ $valor('location') }}" placeholder="Ex.: Corredor 2, prateleira B3 ou Pátio, vaga 4"
            required maxlength="160" @class(['is-invalid' => $errors->has('location')])>
        @error('location')<p class="rm-campo-erro">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="item-qtd">Quantidade disponível <span class="rm-obrigatorio" aria-hidden="true">*</span></label>
        <input id="item-qtd" name="quantity" type="number" min="0" value="{{ $valor('quantity', 0) }}" required
            @class(['is-invalid' => $errors->has('quantity')])>
        @error('quantity')<p class="rm-campo-erro">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="item-min">Quantidade mínima <span class="rm-obrigatorio" aria-hidden="true">*</span></label>
        <input id="item-min" name="minimum_quantity" type="number" min="0" value="{{ $valor('minimum_quantity', 0) }}" required
            @class(['is-invalid' => $errors->has('minimum_quantity')])>
        @error('minimum_quantity')<p class="rm-campo-erro">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="item-lote">Lote <span class="rm-obrigatorio" aria-hidden="true">*</span></label>
        <input id="item-lote" name="lot" value="{{ $valor('lot') }}" placeholder="Ex.: L2026-041" required maxlength="100"
            @class(['is-invalid' => $errors->has('lot')])>
        @error('lot')<p class="rm-campo-erro">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="item-serie">Número de série <span class="rm-obrigatorio" aria-hidden="true">*</span></label>
        <input id="item-serie" name="serial_number" value="{{ $valor('serial_number') }}" placeholder="Ex.: BAT150-2609114" required
            maxlength="120" @class(['is-invalid' => $errors->has('serial_number')])>
        @error('serial_number')<p class="rm-campo-erro">{{ $message }}</p>@enderror
    </div>

    <div>
        <label>Unidade</label>
        <input value="{{ $item?->unit?->name ?? ($minhaUnidade ?? null)?->name }}" disabled>
        @unless ($item)<p class="field-hint">O item entra no estoque da sua unidade.</p>@endunless
    </div>
    <div>
        <label for="item-status">Status <span class="rm-obrigatorio" aria-hidden="true">*</span></label>
        <select id="item-status" name="status" required @class(['is-invalid' => $errors->has('status')])>
            @foreach (['Ativo', 'Em manutenção', 'Inativo'] as $status)
                <option @selected($valor('status', 'Ativo') === $status)>{{ $status }}</option>
            @endforeach
        </select>
        @error('status')<p class="rm-campo-erro">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="item-giro">Classificação de giro <span class="rm-obrigatorio" aria-hidden="true">*</span></label>
        <select id="item-giro" name="turnover" required @class(['is-invalid' => $errors->has('turnover')])>
            @foreach (['Alto giro', 'Médio giro', 'Baixo giro'] as $giro)
                <option @selected($valor('turnover', 'Alto giro') === $giro)>{{ $giro }}</option>
            @endforeach
        </select>
        @error('turnover')<p class="rm-campo-erro">{{ $message }}</p>@enderror
    </div>
</div>
