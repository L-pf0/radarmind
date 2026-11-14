<?php

namespace App\Http\Controllers\RadarMind;

use App\Http\Controllers\Controller;
use App\Http\Requests\GerarRelatorioRequest;
use App\Models\RadarMind\Unit;
use App\Support\Relatorios\ExportadorCsv;
use App\Support\Relatorios\MontadorDeRelatorios;
use App\Support\Relatorios\Relatorio;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Emissão de relatórios (só a matriz). O formato é escolhido pelo botão:
 * "Visualizar / imprimir PDF" abre o relatório na tela; "Baixar CSV" gera o arquivo para o Excel.
 */
class ReportController extends Controller
{
    public function __construct(private MontadorDeRelatorios $montador) {}

    public function index(): View
    {
        return view('radarmind.reports.index', ['units' => Unit::orderBy('id')->get()]);
    }

    public function preview(GerarRelatorioRequest $request): View
    {
        return view('radarmind.reports.preview', [
            'relatorio' => $this->montar($request),
            // mesmo relatório em CSV, com os mesmos parâmetros
            'linkDoCsv' => route('radarmind.reports.export', array_filter($request->validated())),
        ]);
    }

    public function export(GerarRelatorioRequest $request, ExportadorCsv $exportador): StreamedResponse
    {
        return $exportador->baixar($this->montar($request));
    }

    private function montar(GerarRelatorioRequest $request): Relatorio
    {
        return $this->montador->montar($request->tipo(), $request->unidade(), $request->inicio(), $request->fim(), $request->user());
    }
}
