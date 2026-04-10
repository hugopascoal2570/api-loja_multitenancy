<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\InventoryMovementResource;
use App\Models\InventoryMovement;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class InventoryMovementController extends Controller
{
    /**
     * Reverte um movimento de estoque manual
     * POST /inventory-movements/{movement}/revert
     */
    public function revert(InventoryMovement $movement): JsonResponse
    {
        if (!$movement->canBeReverted()) {
            return response()->json([
                'message' => 'Este movimento não pode ser revertido. Apenas ajustes manuais não revertidos podem ser desfeitos.',
            ], 422);
        }

        DB::transaction(function () use ($movement) {
            $variant = $movement->variant;
            $stockBefore = $variant->stock;
            $stockAfter  = $movement->stock_before; // volta ao estado anterior

            // Ajusta o estoque de volta
            $variant->update(['stock' => $stockAfter]);

            // Registra o movimento de reversão
            InventoryMovement::create([
                'product_variant_id' => $variant->id,
                'type'               => $stockAfter >= $stockBefore ? 'in' : 'out',
                'quantity'           => abs($stockAfter - $stockBefore),
                'stock_before'       => $stockBefore,
                'stock_after'        => $stockAfter,
                'reason'             => 'manual_set',
                'notes'              => "Reversão do ajuste #{$movement->id}",
                'user_id'            => auth()->id(),
                'reversal_of_id'     => $movement->id,
            ]);

            // Marca o movimento original como revertido
            $movement->update([
                'reversed_at' => now(),
                'reversed_by' => trim(auth()->user()->name . ' ' . auth()->user()->last_name),
            ]);
        });

        return response()->json([
            'message'  => 'Movimento revertido com sucesso.',
            'movement' => new InventoryMovementResource($movement->refresh()),
        ]);
    }

    /**
     * Retorna o histórico de movimentações de uma variante
     */
    public function showByVariant(ProductVariant $variant, Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('per_page', 50), 100);

        $movements = $variant->movements()
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json(InventoryMovementResource::collection($movements));
    }

    /**
     * Retorna o histórico de movimentações de um produto (todas as variantes)
     */
    public function showByProduct(Request $request, $productId): JsonResponse
    {
        $perPage = min((int) $request->get('per_page', 50), 100);

        $movements = InventoryMovement::whereHas('variant', function ($query) use ($productId) {
            $query->where('product_id', $productId);
        })
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json(InventoryMovementResource::collection($movements));
    }

    /**
     * Retorna o histórico de movimentações de um pedido
     */
    public function showByOrder($orderId): JsonResponse
    {
        $movements = InventoryMovement::where('related_order_id', $orderId)
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return response()->json(InventoryMovementResource::collection($movements));
    }

    /**
     * Retorna resumo de movimentações (entrada/saída) de uma variante
     */
    public function summaryByVariant(ProductVariant $variant): JsonResponse
    {
        $movements = $variant->movements()->get();

        $summary = [
            'variant_id' => $variant->id,
            'sku' => $variant->sku,
            'current_stock' => $variant->stock,
            'total_in' => $movements->where('type', 'in')->sum('quantity'),
            'total_out' => $movements->where('type', 'out')->sum('quantity'),
            'total_movements' => $movements->count(),
            'by_reason' => $movements->groupBy('reason')->map(fn ($group) => $group->count()),
        ];

        return response()->json($summary);
    }

    /**
     * Histórico detalhado de movimentações com filtro de período
     *
     * Parâmetros:
     * - days: número de dias (padrão: 7)
     * - start_date: data inicial (formato: Y-m-d)
     * - end_date: data final (formato: Y-m-d)
     * - type: filtrar por tipo (in, out, adjustment)
     * - reason: filtrar por razão (sale, cancellation, refund, manual_add, manual_remove, manual_set)
     * - per_page: itens por página (padrão: 50)
     */
    public function history(Request $request): JsonResponse
    {
        $request->validate([
            'type'     => 'nullable|in:in,out,adjustment',
            'reason'   => 'nullable|in:sale,cancellation,refund,manual_add,manual_remove,manual_set',
            'per_page' => 'nullable|integer|min:1|max:200',
            'paginate' => 'nullable|in:all',
        ]);

        $query = InventoryMovement::with(['variant.product', 'order'])
            ->orderBy('created_at', 'desc');

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('reason')) {
            $query->where('reason', $request->reason);
        }

        // paginate=all → retorna tudo sem paginação
        if ($request->get('paginate') === 'all') {
            $movements = $query->get();

            return response()->json([
                'total' => $movements->count(),
                'data'  => InventoryMovementResource::collection($movements),
            ]);
        }

        // padrão → paginado
        $perPage   = $request->get('per_page', 50);
        $movements = $query->paginate($perPage);

        return response()->json([
            'data' => InventoryMovementResource::collection($movements),
            'pagination' => [
                'current_page' => $movements->currentPage(),
                'last_page'    => $movements->lastPage(),
                'per_page'     => $movements->perPage(),
                'total'        => $movements->total(),
            ],
        ]);
    }

    /**
     * Resumo geral de movimentações (totais de entrada e saída)
     *
     * Parâmetros:
     * - days: número de dias (padrão: 7)
     * - start_date: data inicial (formato: Y-m-d)
     * - end_date: data final (formato: Y-m-d)
     */
    public function generalSummary(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'nullable|date|date_format:Y-m-d',
            'end_date' => 'nullable|date|date_format:Y-m-d|after_or_equal:start_date',
        ]);

        // Determinar período (sem filtro padrão — exibe tudo se não informar datas)
        $query = InventoryMovement::query();

        if ($request->has('start_date')) {
            $startDate = Carbon::parse($request->start_date)->startOfDay();
            $query->where('created_at', '>=', $startDate);
        } else {
            $startDate = null;
        }

        if ($request->has('end_date')) {
            $endDate = Carbon::parse($request->end_date)->endOfDay();
            $query->where('created_at', '<=', $endDate);
        } else {
            $endDate = null;
        }

        $movements = $query->get();

        // Totais gerais
        $totalIn = $movements->where('type', 'in')->sum('quantity');
        $totalOut = $movements->where('type', 'out')->sum('quantity');
        $totalAdjustments = $movements->where('type', 'adjustment')->count();

        // Totais por razão
        $byReason = [
            'sales' => [
                'quantity' => $movements->where('reason', 'sale')->sum('quantity'),
                'count' => $movements->where('reason', 'sale')->count(),
            ],
            'cancellations' => [
                'quantity' => $movements->where('reason', 'cancellation')->sum('quantity'),
                'count' => $movements->where('reason', 'cancellation')->count(),
            ],
            'refunds' => [
                'quantity' => $movements->where('reason', 'refund')->sum('quantity'),
                'count' => $movements->where('reason', 'refund')->count(),
            ],
            'manual_additions' => [
                'quantity' => $movements->where('reason', 'manual_add')->sum('quantity'),
                'count' => $movements->where('reason', 'manual_add')->count(),
            ],
            'manual_removals' => [
                'quantity' => $movements->where('reason', 'manual_remove')->sum('quantity'),
                'count' => $movements->where('reason', 'manual_remove')->count(),
            ],
            'manual_adjustments' => [
                'quantity' => $movements->where('reason', 'manual_set')->sum('quantity'),
                'count' => $movements->where('reason', 'manual_set')->count(),
            ],
        ];

        // Resumo por dia
        $dailySummary = $movements->groupBy(fn ($m) => $m->created_at->format('Y-m-d'))
            ->map(function ($dayMovements, $date) {
                return [
                    'date' => $date,
                    'date_formatted' => Carbon::parse($date)->format('d/m/Y'),
                    'total_in' => $dayMovements->where('type', 'in')->sum('quantity'),
                    'total_out' => $dayMovements->where('type', 'out')->sum('quantity'),
                    'movements_count' => $dayMovements->count(),
                ];
            })
            ->sortByDesc('date')
            ->values();

        return response()->json([
            'period' => [
                'start_date'           => $startDate?->format('Y-m-d'),
                'end_date'             => $endDate?->format('Y-m-d'),
                'start_date_formatted' => $startDate?->format('d/m/Y'),
                'end_date_formatted'   => $endDate?->format('d/m/Y'),
                'all_time'             => is_null($startDate) && is_null($endDate),
            ],
            'totals' => [
                'total_in'         => $totalIn,
                'total_out'        => $totalOut,
                'net_change'       => $totalIn - $totalOut,
                'total_movements'  => $movements->count(),
                'total_adjustments'=> $totalAdjustments,
            ],
            'by_reason' => $byReason,
            'daily_summary' => $dailySummary,
        ]);
    }

    /**
     * Relatório semanal de vendas estimadas via regularização de estoque
     * GET /api/inventory-movements/weekly-report?date_from=2026-03-17&date_to=2026-03-22&format=xlsx
     *
     * Lógica: quando a loja regulariza o estoque (ex: era 15, ficou 10),
     * o sistema registra uma saída de 5 = 5 peças vendidas na semana.
     * O relatório agrupa por produto/variante e estima a receita pelo preço cadastrado.
     */
    public function weeklyReport(Request $request)
    {
        $request->validate([
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to'   => 'nullable|date_format:Y-m-d',
            'format'    => 'nullable|in:json,csv,xlsx',
        ]);

        $dateFrom = $request->date_from
            ? Carbon::parse($request->date_from)->startOfDay()
            : now()->subDays(6)->startOfDay();

        $dateTo = $request->date_to
            ? Carbon::parse($request->date_to)->endOfDay()
            : now()->endOfDay();

        $format = $request->get('format', 'json');

        // Busca todas as saídas do período (vendas, remoções manuais, regularizações)
        // Exclui saídas de pedidos cancelados (venda que foi revertida não deve contar)
        $movements = InventoryMovement::with(['variant.product', 'order'])
            ->where('type', 'out')
            ->whereNull('reversal_of_id')   // ignora reversões
            ->whereNull('reversed_at')      // ignora revertidos
            ->where(function ($q) {
                $q->where('reason', '!=', 'sale')
                  ->orWhereHas('order', fn($o) => $o->where('status', '!=', 'cancelled'))
                  ->orWhereNull('related_order_id');
            })
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->get();

        // Agrupa por variante e soma as saídas discriminadas por origem
        $report = $movements
            ->groupBy('product_variant_id')
            ->map(function ($group) {
                $first   = $group->first();
                $variant = $first->variant;
                $product = $variant?->product;

                $vendaSite   = $group->filter(fn($m) => $m->reason === 'sale' && $m->order?->source === 'online')->sum('quantity');
                $vendaBalcao = $group->filter(fn($m) => $m->reason === 'sale' && $m->order?->source === 'counter')->sum('quantity');
                $ajusteManual = $group->filter(fn($m) => in_array($m->reason, ['manual_remove', 'manual_set']))->sum('quantity');
                $qtdTotal    = $vendaSite + $vendaBalcao + $ajusteManual;

                $precoUnitario   = $product?->promotion_price ?? $product?->retail_price ?? $product?->wholesale_price ?? 0;
                $receitaEstimada = $qtdTotal * $precoUnitario;

                return [
                    'produto'          => $product?->name ?? 'Produto removido',
                    'cor'              => $variant?->color ?? '-',
                    'tamanho'          => $variant?->size ?? '-',
                    'sku'              => $variant?->sku ?? '-',
                    'estoque_atual'    => $variant?->stock ?? 0,
                    'qtd_vendida'      => $qtdTotal,
                    'venda_site'       => (int) $vendaSite,
                    'venda_balcao'     => (int) $vendaBalcao,
                    'ajuste_manual'    => (int) $ajusteManual,
                    'preco_unitario'   => (float) $precoUnitario,
                    'receita_estimada' => (float) $receitaEstimada,
                ];
            })
            ->values()
            ->sortByDesc('receita_estimada')
            ->values();

        $totais = [
            'total_pecas_vendidas'   => $report->sum('qtd_vendida'),
            'total_venda_site'       => $report->sum('venda_site'),
            'total_venda_balcao'     => $report->sum('venda_balcao'),
            'total_ajuste_manual'    => $report->sum('ajuste_manual'),
            'receita_total_estimada' => round($report->sum('receita_estimada'), 2),
            'total_produtos'         => $report->count(),
        ];

        if ($format === 'json') {
            return response()->json([
                'periodo' => [
                    'de'  => $dateFrom->format('d/m/Y'),
                    'ate' => $dateTo->format('d/m/Y'),
                ],
                'totais'  => $totais,
                'itens'   => $report,
            ]);
        }

        // CSV
        $filename = 'relatorio_semanal_' . $dateFrom->format('Ymd') . '_' . $dateTo->format('Ymd');
        $headers  = ['Produto', 'Cor', 'Tamanho', 'SKU', 'Estoque Atual', 'Total Saídas', 'Venda Site', 'Venda Balcão', 'Ajuste Manual', 'Preço Unit. (R$)', 'Receita Estimada (R$)'];

        if ($format === 'csv') {
            $callback = function () use ($report, $headers, $totais) {
                $out = fopen('php://output', 'w');
                fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8
                fputcsv($out, $headers, ';');
                foreach ($report as $row) {
                    fputcsv($out, [
                        $row['produto'],
                        $row['cor'],
                        $row['tamanho'],
                        $row['sku'],
                        $row['estoque_atual'],
                        $row['qtd_vendida'],
                        $row['venda_site'],
                        $row['venda_balcao'],
                        $row['ajuste_manual'],
                        number_format($row['preco_unitario'], 2, ',', '.'),
                        number_format($row['receita_estimada'], 2, ',', '.'),
                    ], ';');
                }
                fputcsv($out, [], ';');
                fputcsv($out, ['TOTAL', '', '', '', '', $totais['total_pecas_vendidas'], $totais['total_venda_site'], $totais['total_venda_balcao'], $totais['total_ajuste_manual'], '', number_format($totais['receita_total_estimada'], 2, ',', '.')], ';');
                fclose($out);
            };

            return response()->stream($callback, 200, [
                'Content-Type'        => 'text/csv; charset=UTF-8',
                'Content-Disposition' => "attachment; filename={$filename}.csv",
            ]);
        }

        // XLSX com 3 abas: ESTOQUE (estoque atual), ENTRADA e SAÍDA (período)
        $sizes   = ['PP', 'P', 'M', 'G', 'GG'];
        $palette = [
            'FFF2CC', 'FCE4D6', 'DDEBF7', 'E2EFDA', 'F3E6FF',
            'FFD7D7', 'D9F0FF', 'FFF0DD', 'E8FFE8', 'FFE8F5',
            'FFDCE1', 'D5F5E3', 'D6EAF8', 'FDEBD0', 'EAF2FF',
            'F9EBEA', 'E8DAEF', 'D5F5E3', 'FDFEFE', 'F0F3F4',
            'FDEDEC', 'EBF5FB', 'E9F7EF', 'FEF9E7', 'F5EEF8',
        ];

        // ── Dados ESTOQUE (todos os produtos, estoque atual) ──────────────
        $products = \App\Models\Product::with(['variants' => fn($q) =>
            $q->orderByRaw("FIELD(size, 'PP','P','M','G','GG')")
        ])->orderBy('name')->get();

        $estoqueRows   = [];
        $productColors = [];
        $pi = 0;
        foreach ($products as $product) {
            $palIdx = $pi % count($palette);
            $productColors[$product->id] = $palIdx;
            $pi++;
            foreach ($product->variants->groupBy('color') as $color => $variants) {
                $sizeMap = [];
                $total   = 0;
                foreach ($sizes as $s) {
                    $stock      = $variants->firstWhere('size', $s)?->stock ?? 0;
                    $sizeMap[$s] = $stock;
                    $total      += $stock;
                }
                $baseSku = preg_replace('/[-_]?(PP|XGG|XG|GG|G|M|P)$/i', '', $variants->first()?->sku ?? '');
                $price   = (float)($product->promotion_price ?? $product->retail_price ?? $product->wholesale_price ?? 0);
                $estoqueRows[] = [
                    'pal'    => $palIdx,
                    'sku'    => $baseSku,
                    'produto'=> $product->name,
                    'cor'    => $color ?? '',
                    'valor'  => $price,
                    'sizes'  => $sizeMap,
                    'saldo'  => $total,
                    'valor_total' => round($total * $price, 2),
                ];
            }
        }

        // ── Movimentos do período para ENTRADA e SAÍDA ────────────────────
        $allMov   = InventoryMovement::with(['variant.product'])->whereBetween('created_at', [$dateFrom, $dateTo])->orderBy('created_at')->get();
        $entradas = $allMov->where('type', 'in')->values();
        $saidas   = $allMov->where('type', 'out')->whereNull('reversal_of_id')->values();

        // ── Helpers XML ───────────────────────────────────────────────────
        $pc  = count($palette);
        $xe  = fn($s) => htmlspecialchars((string)$s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $sc  = fn($col, $row, $val, $s = 0) =>
            '<c r="'.$col.$row.'" t="inlineStr"'.($s ? ' s="'.$s.'"' : '').'><is><t>'.$xe($val).'</t></is></c>';
        $nc  = fn($col, $row, $val, $s = 0) =>
            '<c r="'.$col.$row.'"'.($s ? ' s="'.$s.'"' : '').'><v>'.(float)$val.'</v></c>';
        // $mc = célula numérica com formato de moeda (R$)
        // xf de moeda = pal_idx + 2 + $pc  (segunda metade dos xfs)
        $mc  = fn($col, $row, $val, $s = 0) =>
            '<c r="'.$col.$row.'"'.($s ? ' s="'.$s.'"' : '').'><v>'.(float)$val.'</v></c>';

        // ── Styles ────────────────────────────────────────────────────────
        // numFmt 165 = moeda BR  (#,##0.00)
        // xf 0            = padrão
        // xf 1            = cabeçalho bold
        // xf 2..(pc+1)    = cores sem moeda
        // xf (pc+2)..(2pc+1) = cores COM formato de moeda
        // xf (2pc+2)      = cabeçalho bold + moeda (para totais)
        $fillsXml = '<fills count="'.($pc+2).'">'
            .'<fill><patternFill patternType="none"/></fill>'
            .'<fill><patternFill patternType="gray125"/></fill>';
        foreach ($palette as $hex) {
            $fillsXml .= '<fill><patternFill patternType="solid"><fgColor rgb="FF'.$hex.'"/></patternFill></fill>';
        }
        $fillsXml .= '</fills>';

        // xfs: 0=default, 1=bold header, 2..pc+1=cores normais, pc+2..2pc+1=cores+moeda, 2pc+2=bold+moeda
        $xfsCount = 2 + $pc * 2 + 1;
        $xfsXml   = '<cellXfs count="'.$xfsCount.'">'
            .'<xf numFmtId="0"   fontId="0" fillId="0" borderId="0"/>'                         // 0
            .'<xf numFmtId="0"   fontId="1" fillId="0" borderId="0" applyFont="1"/>';           // 1
        foreach (range(0, $pc-1) as $i) {
            $xfsXml .= '<xf numFmtId="0"   fontId="0" fillId="'.($i+2).'" borderId="0" applyFill="1"/>'; // 2..pc+1
        }
        foreach (range(0, $pc-1) as $i) {
            $xfsXml .= '<xf numFmtId="165" fontId="0" fillId="'.($i+2).'" borderId="0" applyFill="1" applyNumberFormat="1"/>'; // pc+2..2pc+1
        }
        $xfsXml .= '<xf numFmtId="165" fontId="1" fillId="0" borderId="0" applyFont="1" applyNumberFormat="1"/>';              // 2pc+2
        $xfsXml .= '</cellXfs>';

        $stylesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<numFmts count="1"><numFmt numFmtId="165" formatCode="#,##0.00"/></numFmts>
<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>
'.$fillsXml.'
<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>
<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
'.$xfsXml.'</styleSheet>';

        // Helpers para índices de estilo
        $xfNormal   = fn(int $palIdx): int => $palIdx + 2;           // cor sem moeda
        $xfMoeda    = fn(int $palIdx): int => $palIdx + 2 + $pc;     // cor COM moeda
        $xfHdrMoeda = 2 + $pc * 2;                                   // bold + moeda (total)

        // ── Sheet 1: ESTOQUE ──────────────────────────────────────────────
        $s1  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
             . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        $s1 .= '<row r="1">';
        foreach (['A'=>'SKU','B'=>'PRODUTO','C'=>'COR','D'=>'VALOR (R$)','E'=>'PP','F'=>'P','G'=>'M','H'=>'G','I'=>'GG','J'=>'SALDO TOTAL','K'=>'VALOR TOTAL (R$)'] as $col => $lbl) {
            $s1 .= $sc($col, 1, $lbl, 1);
        }
        $s1 .= '</row>';
        $r = 2; $sumSaldo = 0; $sumValor = 0.0;
        foreach ($estoqueRows as $row) {
            $xfN = $xfNormal($row['pal']);
            $xfM = $xfMoeda($row['pal']);
            $s1 .= '<row r="'.$r.'">';
            $s1 .= $sc('A',$r,$row['sku'],$xfN).$sc('B',$r,$row['produto'],$xfN).$sc('C',$r,$row['cor'],$xfN).$nc('D',$r,$row['valor'],$xfM);
            foreach (['PP'=>'E','P'=>'F','M'=>'G','G'=>'H','GG'=>'I'] as $sz => $col) {
                $s1 .= $nc($col,$r,$row['sizes'][$sz]??0,$xfN);
            }
            $s1 .= $nc('J',$r,$row['saldo'],$xfN).$nc('K',$r,$row['valor_total'],$xfM).'</row>';
            $sumSaldo += $row['saldo']; $sumValor += $row['valor_total']; $r++;
        }
        $s1 .= '<row r="'.$r.'">'.$sc('A',$r,'TOTAL',1).$nc('J',$r,$sumSaldo,1).$nc('K',$r,round($sumValor,2),$xfHdrMoeda).'</row>';
        $s1 .= '</sheetData></worksheet>';

        // ── Sheet 2: ENTRADA ──────────────────────────────────────────────
        $reasonLabels = [
            'sale'=>'Venda','manual_add'=>'Adição manual','manual_remove'=>'Remoção manual',
            'manual_set'=>'Regularização','cancellation'=>'Cancelamento','refund'=>'Reembolso',
        ];

        $s2  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
             . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        $s2 .= '<row r="1">';
        foreach (['A'=>'SKU','B'=>'DATA','C'=>'PRODUTO','D'=>'COR','E'=>'TAMANHO','F'=>'QUANTIDADE','G'=>'MOTIVO'] as $col => $lbl) {
            $s2 .= $sc($col, 1, $lbl, 1);
        }
        $s2 .= '</row>';
        $r = 2;
        foreach ($entradas as $mov) {
            $variant = $mov->variant; $product = $variant?->product;
            $palIdx  = $productColors[$product?->id] ?? 0;
            $xfN     = $xfNormal($palIdx);
            $s2 .= '<row r="'.$r.'">'
                .$sc('A',$r,$variant?->sku??'',$xfN).$sc('B',$r,$mov->created_at->format('d/m/Y'),$xfN)
                .$sc('C',$r,$product?->name??'',$xfN).$sc('D',$r,$variant?->color??'',$xfN)
                .$sc('E',$r,$variant?->size??'',$xfN).$nc('F',$r,$mov->quantity,$xfN)
                .$sc('G',$r,$reasonLabels[$mov->reason]??($mov->reason??''),$xfN)
                .'</row>';
            $r++;
        }
        $s2 .= '</sheetData></worksheet>';

        // ── Sheet 3: SAÍDA (com valor unitário e total) ───────────────────
        $s3  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
             . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        $s3 .= '<row r="1">';
        foreach (['A'=>'SKU','B'=>'DATA','C'=>'PRODUTO','D'=>'COR','E'=>'TAMANHO','F'=>'QUANTIDADE','G'=>'MOTIVO','H'=>'VALOR UNIT. (R$)','I'=>'VALOR TOTAL (R$)'] as $col => $lbl) {
            $s3 .= $sc($col, 1, $lbl, 1);
        }
        $s3 .= '</row>';
        $r = 2; $sumReceita = 0.0;
        foreach ($saidas as $mov) {
            $variant    = $mov->variant; $product = $variant?->product;
            $palIdx     = $productColors[$product?->id] ?? 0;
            $xfN        = $xfNormal($palIdx);
            $xfM        = $xfMoeda($palIdx);
            $preco      = (float)($product?->promotion_price ?? $product?->retail_price ?? $product?->wholesale_price ?? 0);
            $total      = round($preco * $mov->quantity, 2);
            $sumReceita += $total;
            $s3 .= '<row r="'.$r.'">'
                .$sc('A',$r,$variant?->sku??'',$xfN).$sc('B',$r,$mov->created_at->format('d/m/Y'),$xfN)
                .$sc('C',$r,$product?->name??'',$xfN).$sc('D',$r,$variant?->color??'',$xfN)
                .$sc('E',$r,$variant?->size??'',$xfN).$nc('F',$r,$mov->quantity,$xfN)
                .$sc('G',$r,$reasonLabels[$mov->reason]??($mov->reason??''),$xfN)
                .$nc('H',$r,$preco,$xfM).$nc('I',$r,$total,$xfM)
                .'</row>';
            $r++;
        }
        // Linha de total da receita
        $s3 .= '<row r="'.$r.'">'.$sc('A',$r,'TOTAL',1).$nc('I',$r,round($sumReceita,2),$xfHdrMoeda).'</row>';
        $s3 .= '</sheetData></worksheet>';

        // ── Empacotar XLSX ────────────────────────────────────────────────
        $tempFile = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new \ZipArchive();
        $zip->open($tempFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
<Default Extension="xml" ContentType="application/xml"/>
<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
<Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
<Override PartName="/xl/worksheets/sheet3.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>
<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet3.xml"/>
<Relationship Id="rId4" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
<sheets>
<sheet name="ESTOQUE" sheetId="1" r:id="rId1"/>
<sheet name="ENTRADA" sheetId="2" r:id="rId2"/>
<sheet name="SAÍDA" sheetId="3" r:id="rId3"/>
</sheets>
</workbook>');
        $zip->addFromString('xl/styles.xml', $stylesXml);
        $zip->addFromString('xl/worksheets/sheet1.xml', $s1);
        $zip->addFromString('xl/worksheets/sheet2.xml', $s2);
        $zip->addFromString('xl/worksheets/sheet3.xml', $s3);
        $zip->close();

        $content = file_get_contents($tempFile);
        unlink($tempFile);

        return response($content, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename={$filename}.xlsx",
        ]);
    }

    /**
     * Exporta planilha completa de estoque com 3 abas: ESTOQUE, ENTRADA, SAÍDA
     * Cada produto recebe uma cor diferente nas linhas para facilitar leitura.
     *
     * GET /api/inventory/export-stock
     * Parâmetros opcionais: date_from, date_to (filtram ENTRADA e SAÍDA)
     */
    public function exportStock(Request $request)
    {
        $request->validate([
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to'   => 'nullable|date_format:Y-m-d',
        ]);

        $sizes = ['PP', 'P', 'M', 'G', 'GG'];

        // Paleta de cores pastel (10 cores rotativas por produto)
        $palette = [
            'FFF2CC', // amarelo
            'FCE4D6', // pêssego
            'DDEBF7', // azul claro
            'E2EFDA', // verde claro
            'F3E6FF', // lilás
            'FFD7D7', // rosa
            'D9F0FF', // azul céu
            'FFF0DD', // creme
            'E8FFE8', // menta
            'FFE8F5', // rosa claro
        ];

        // ── ESTOQUE ──────────────────────────────────────────────────────
        $products = \App\Models\Product::with(['variants' => fn($q) =>
            $q->orderByRaw("FIELD(size, 'PP','P','M','G','GG')")
        ])->orderBy('name')->get();

        $estoqueRows   = [];
        $productColors = []; // product_id => palette index
        $pi = 0;

        foreach ($products as $product) {
            $palIdx = $pi % count($palette);
            $productColors[$product->id] = $palIdx;
            $pi++;

            $byColor = $product->variants->groupBy('color');

            foreach ($byColor as $color => $variants) {
                $sizeMap = [];
                $total   = 0;
                foreach ($sizes as $s) {
                    $stock      = $variants->firstWhere('size', $s)?->stock ?? 0;
                    $sizeMap[$s] = $stock;
                    $total      += $stock;
                }

                $firstSku = $variants->first()?->sku ?? '';
                $baseSku  = preg_replace('/[-_]?(PP|XGG|XG|GG|G|M|P)$/i', '', $firstSku);
                $price    = (float)($product->promotion_price ?? $product->retail_price ?? $product->wholesale_price ?? 0);

                $estoqueRows[] = [
                    'pal'         => $palIdx,
                    'sku'         => $baseSku,
                    'produto'     => $product->name,
                    'cor'         => $color ?? '',
                    'valor'       => $price,
                    'sizes'       => $sizeMap,
                    'saldo'       => $total,
                    'valor_total' => round($total * $price, 2),
                ];
            }
        }

        // ── MOVIMENTAÇÕES ────────────────────────────────────────────────
        $dateFrom = $request->date_from ? Carbon::parse($request->date_from)->startOfDay() : null;
        $dateTo   = $request->date_to   ? Carbon::parse($request->date_to)->endOfDay()     : null;

        $movQ = InventoryMovement::with(['variant.product'])->orderBy('created_at');
        if ($dateFrom) $movQ->where('created_at', '>=', $dateFrom);
        if ($dateTo)   $movQ->where('created_at', '<=', $dateTo);
        $all = $movQ->get();

        $entradas = $all->where('type', 'in')->values();
        $saidas   = $all->where('type', 'out')->values();

        // ── HELPERS XML ──────────────────────────────────────────────────
        $xe = fn($s) => htmlspecialchars((string)$s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $sc = fn($col, $row, $val, $s = 0) =>
            '<c r="'.$col.$row.'" t="inlineStr"'.($s ? ' s="'.$s.'"' : '').'><is><t>'.$xe($val).'</t></is></c>';
        $nc = fn($col, $row, $val, $s = 0) =>
            '<c r="'.$col.$row.'"'.($s ? ' s="'.$s.'"' : '').'><v>'.(float)$val.'</v></c>';

        // ── STYLES ───────────────────────────────────────────────────────
        // xf 0=padrão, 1=cabeçalho bold, 2..N=cores da paleta
        $fillsXml = '<fills count="'.(count($palette) + 2).'">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>';
        foreach ($palette as $hex) {
            $fillsXml .= '<fill><patternFill patternType="solid"><fgColor rgb="FF'.$hex.'"/></patternFill></fill>';
        }
        $fillsXml .= '</fills>';

        $xfsXml = '<cellXfs count="'.(count($palette) + 2).'">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" applyFont="1"/>';
        foreach (range(0, count($palette) - 1) as $i) {
            $xfsXml .= '<xf numFmtId="0" fontId="0" fillId="'.($i + 2).'" borderId="0" applyFill="1"/>';
        }
        $xfsXml .= '</cellXfs>';

        $stylesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<fonts count="2">
<font><sz val="11"/><name val="Calibri"/></font>
<font><b/><sz val="11"/><name val="Calibri"/></font>
</fonts>
'.$fillsXml.'
<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>
<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
'.$xfsXml.'
</styleSheet>';

        // ── SHEET 1: ESTOQUE ─────────────────────────────────────────────
        // A=SKU B=PRODUTO C=COR D=VALOR E=PP F=P G=M H=G I=GG J=SALDO K=VALOR TOTAL
        $s1  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
             . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        $s1 .= '<row r="1">';
        foreach (['A'=>'SKU','B'=>'PRODUTO','C'=>'COR','D'=>'VALOR (R$)','E'=>'PP','F'=>'P','G'=>'M','H'=>'G','I'=>'GG','J'=>'SALDO TOTAL','K'=>'VALOR TOTAL'] as $col => $lbl) {
            $s1 .= $sc($col, 1, $lbl, 1);
        }
        $s1 .= '</row>';

        $r = 2; $sumSaldo = 0; $sumValor = 0.0;
        foreach ($estoqueRows as $row) {
            $xf = $row['pal'] + 2;
            $s1 .= '<row r="'.$r.'">';
            $s1 .= $sc('A', $r, $row['sku'],     $xf);
            $s1 .= $sc('B', $r, $row['produto'],  $xf);
            $s1 .= $sc('C', $r, $row['cor'],      $xf);
            $s1 .= $nc('D', $r, $row['valor'],    $xf);
            foreach (['PP'=>'E','P'=>'F','M'=>'G','G'=>'H','GG'=>'I'] as $sz => $col) {
                $s1 .= $nc($col, $r, $row['sizes'][$sz] ?? 0, $xf);
            }
            $s1 .= $nc('J', $r, $row['saldo'],       $xf);
            $s1 .= $nc('K', $r, $row['valor_total'],  $xf);
            $s1 .= '</row>';
            $sumSaldo += $row['saldo'];
            $sumValor += $row['valor_total'];
            $r++;
        }
        $s1 .= '<row r="'.$r.'">'.$sc('A', $r, 'TOTAL', 1).$nc('J', $r, $sumSaldo, 1).$nc('K', $r, round($sumValor, 2), 1).'</row>';
        $s1 .= '</sheetData></worksheet>';

        // ── SHEET 2: ENTRADA / SHEET 3: SAÍDA ────────────────────────────
        $reasonLabels = [
            'sale'          => 'Venda',
            'manual_add'    => 'Adição manual',
            'manual_remove' => 'Remoção manual',
            'manual_set'    => 'Regularização',
            'cancellation'  => 'Cancelamento',
            'refund'        => 'Reembolso',
        ];

        $buildMovSheet = function ($movements) use ($sc, $nc, $productColors, $reasonLabels): string {
            $xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                  . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
            $xml .= '<row r="1">';
            foreach (['A'=>'SKU','B'=>'DATA','C'=>'PRODUTO','D'=>'COR','E'=>'TAMANHO','F'=>'QUANTIDADE','G'=>'MOTIVO'] as $col => $lbl) {
                $xml .= $sc($col, 1, $lbl, 1);
            }
            $xml .= '</row>';
            $r = 2;
            foreach ($movements as $mov) {
                $variant = $mov->variant;
                $product = $variant?->product;
                $xf = isset($productColors[$product?->id]) ? $productColors[$product->id] + 2 : 0;
                $xml .= '<row r="'.$r.'">';
                $xml .= $sc('A', $r, $variant?->sku ?? '',                    $xf);
                $xml .= $sc('B', $r, $mov->created_at->format('d/m/Y'),       $xf);
                $xml .= $sc('C', $r, $product?->name ?? '',                   $xf);
                $xml .= $sc('D', $r, $variant?->color ?? '',                  $xf);
                $xml .= $sc('E', $r, $variant?->size ?? '',                   $xf);
                $xml .= $nc('F', $r, $mov->quantity,                          $xf);
                $xml .= $sc('G', $r, $reasonLabels[$mov->reason] ?? ($mov->reason ?? ''), $xf);
                $xml .= '</row>';
                $r++;
            }
            $xml .= '</sheetData></worksheet>';
            return $xml;
        };

        $s2 = $buildMovSheet($entradas);
        $s3 = $buildMovSheet($saidas);

        // ── EMPACOTAR XLSX ───────────────────────────────────────────────
        $tempFile = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new \ZipArchive();
        $zip->open($tempFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
<Default Extension="xml" ContentType="application/xml"/>
<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
<Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
<Override PartName="/xl/worksheets/sheet3.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>');

        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>');

        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>
<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet3.xml"/>
<Relationship Id="rId4" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>');

        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
<sheets>
<sheet name="ESTOQUE" sheetId="1" r:id="rId1"/>
<sheet name="ENTRADA" sheetId="2" r:id="rId2"/>
<sheet name="SAÍDA" sheetId="3" r:id="rId3"/>
</sheets>
</workbook>');

        $zip->addFromString('xl/styles.xml',               $stylesXml);
        $zip->addFromString('xl/worksheets/sheet1.xml',    $s1);
        $zip->addFromString('xl/worksheets/sheet2.xml',    $s2);
        $zip->addFromString('xl/worksheets/sheet3.xml',    $s3);
        $zip->close();

        $filename = 'estoque_' . now()->format('Ymd_His');
        $content  = file_get_contents($tempFile);
        unlink($tempFile);

        return response($content, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename={$filename}.xlsx",
        ]);
    }
}
