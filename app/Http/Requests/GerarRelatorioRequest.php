<?php

namespace App\Http\Requests;

use App\Models\RadarMind\Unit;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Parâmetros da emissão de relatórios (visualizar/PDF e CSV usam os mesmos).
 * A rota já é exclusiva da matriz (middleware perfil:matriz).
 */
class GerarRelatorioRequest extends FormRequest
{
    public const TIPOS = ['estoque', 'movimentacoes', 'desempenho'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(self::TIPOS)],
            'unit_id' => ['nullable', 'integer', 'exists:radarmind_units,id'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            // só compara com a data inicial quando ela foi informada
            'end_date' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('start_date'), 'after_or_equal:start_date')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.required' => 'Escolha o tipo de relatório.',
            'type.in' => 'Escolha um dos relatórios da lista.',
            'unit_id.exists' => 'A unidade escolhida não existe.',
            'start_date.date_format' => 'Data inicial inválida.',
            'end_date.date_format' => 'Data final inválida.',
            'end_date.after_or_equal' => 'A data final deve ser igual ou posterior à data inicial.',
        ];
    }

    public function tipo(): string
    {
        return $this->validated('type');
    }

    public function unidade(): ?Unit
    {
        return $this->filled('unit_id') ? Unit::find($this->integer('unit_id')) : null;
    }

    public function inicio(): ?CarbonImmutable
    {
        return $this->filled('start_date') ? CarbonImmutable::createFromFormat('Y-m-d', $this->validated('start_date'))->startOfDay() : null;
    }

    public function fim(): ?CarbonImmutable
    {
        return $this->filled('end_date') ? CarbonImmutable::createFromFormat('Y-m-d', $this->validated('end_date'))->endOfDay() : null;
    }
}
