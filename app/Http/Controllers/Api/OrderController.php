<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Order\CancelOrderRequest;
use App\Http\Requests\Api\Order\UpdateStatusRequest;
use App\Http\Resources\OrderResource;
use App\Mail\OrderCancelledMail;
use App\Repositories\OrderRepository;
use App\Services\MercadoPagoService;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        private OrderRepository $orders,
        private MercadoPagoService $mp
    ) {}

    public function cancel(CancelOrderRequest $request, string $orderId)
    {
        // 1) buscar pedido (padrão repository)
        $order = $this->orders->findWithRelations($orderId);

        // 2) cancelar — ML não passa pelo reembolso MercadoPago
        if ($order->source === 'mercadolivre') {
            $order->status      = 'cancelled';
            $order->cancel_reason = $request->validated()['reason'] ?? 'Cancelado pelo administrador';
            $order->canceled_at = now();
            $order->save();
            $freshOrder = $order->fresh(['user', 'items']);
        } else {
            $result = $this->mp->cancelAndRefund(
                order:  $order,
                amount: $request->validated()['amount'] ?? null,
                reason: $request->validated()['reason'] ?? null
            );
            $freshOrder = $result['order'];
        }

        // 3) e-mail para o usuário (se houver)
        $emailSent = false;
        $emailError = null;

        if ($freshOrder->user && $freshOrder->user->email) {
            \Log::info('=== TENTANDO ENVIAR EMAIL DE CANCELAMENTO ===', [
                'order_id' => $freshOrder->id,
                'to' => $freshOrder->user->email,
                'user_name' => $freshOrder->user->name,
                'from' => config('mail.from.address'),
                'mailer' => config('mail.default'),
                'host' => config('mail.mailers.smtp.host'),
                'port' => config('mail.mailers.smtp.port'),
            ]);

            try {
                Mail::to($freshOrder->user->email)->send(new OrderCancelledMail($freshOrder));
                $emailSent = true;

                \Log::info('=== EMAIL DE CANCELAMENTO ENVIADO COM SUCESSO ===', [
                    'order_id' => $freshOrder->id,
                    'to' => $freshOrder->user->email,
                ]);

            } catch (\Symfony\Component\Mailer\Exception\TransportException $e) {
                $emailError = $e->getMessage();
                \Log::error('=== ERRO DE TRANSPORTE SMTP (CANCELAMENTO) ===', [
                    'order_id' => $freshOrder->id,
                    'to' => $freshOrder->user->email,
                    'error_message' => $e->getMessage(),
                    'error_code' => $e->getCode(),
                ]);

            } catch (\Exception $e) {
                $emailError = $e->getMessage();
                \Log::error('=== ERRO GERAL AO ENVIAR EMAIL DE CANCELAMENTO ===', [
                    'order_id' => $freshOrder->id,
                    'to' => $freshOrder->user->email,
                    'error_class' => get_class($e),
                    'error_message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        } else {
            \Log::warning('=== PEDIDO SEM EMAIL DE USUÁRIO ===', [
                'order_id' => $freshOrder->id,
                'has_user' => (bool) $freshOrder->user,
            ]);
        }

        // 4) resposta no padrão (Resource)
        return response()->json([
            'message' => 'Pedido cancelado/reembolsado com sucesso.',
            'email_sent' => $emailSent,
            'email_error' => config('app.debug') ? $emailError : null,
            'order'   => new OrderResource($freshOrder),
        ]);
    }

    

    /**
     * Lista todos os pedidos (admin)
     * GET /api/orders?period=24h&status=approved
     */
    public function index(Request $request)
    {
        $request->validate([
            'status'          => 'nullable|in:pending,approved,rejected,cancelled,refunded,shipped,delivered',
            'source'          => 'nullable|in:online,counter',
            'delivery_method' => 'nullable|in:counter,excursion,pickup,shipping',
            'period'          => 'nullable|in:24h,7d,30d,all',
        ]);

        $period         = $request->query('period');
        $status         = $request->query('status');
        $source         = $request->query('source');
        $deliveryMethod = $request->query('delivery_method');
        $perPage = min((int) $request->query('per_page', 50), 200);
        $orders = $this->orders->getAllWithRelations($period, $status, $source, $deliveryMethod, $perPage);
        return OrderResource::collection($orders);
    }

    /**
     * Exibe detalhes de um pedido específico (admin)
     */
    public function show(string $orderId)
    {
        $order = $this->orders->findWithRelations($orderId);
        return new OrderResource($order);
    }

    /**
     * Retorna dados formatados para impressão do pedido
     * GET /api/orders/{orderId}/print
     */
    public function print(string $orderId)
    {
        $order = $this->orders->findWithRelations($orderId);

        $items = $order->items->map(function ($item) {
            $itemData = [
                'id' => $item->id,
                'type' => $item->type,
                'quantity' => $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'total_price' => (float) $item->total_price,
                'product_name' => $item->product->name ?? 'Produto não encontrado',
                'product_image' => $item->product->image_url ?? null,
            ];

            // Se for um item normal com variante
            if ($item->variant && !$item->kit) {
                $itemData['variant'] = [
                    'color' => $item->variant->color,
                    'size' => $item->variant->size,
                    'sku' => $item->variant->sku,
                ];
                $itemData['description'] = "{$item->product->name} - {$item->variant->color} / {$item->variant->size}";
            }

            // Se for um kit, expandir as variações
            if ($item->kit) {
                $itemData['is_kit'] = true;
                $itemData['kit_name'] = $item->kit->name;
                $itemData['kit_description'] = $item->kit->description;
                $itemData['kit_total_quantity'] = $item->kit->total_quantity;

                // Mapear cada item do kit com suas variações
                $kitItems = [];
                if ($item->kit->items) {
                    foreach ($item->kit->items as $kitItem) {
                        $kitItems[] = [
                            'quantity' => $kitItem->quantity,
                            'color' => $kitItem->variant->color ?? null,
                            'size' => $kitItem->variant->size ?? null,
                            'sku' => $kitItem->variant->sku ?? null,
                            'description' => $kitItem->variant
                                ? "{$kitItem->quantity}x {$kitItem->variant->color} / {$kitItem->variant->size}"
                                : "{$kitItem->quantity}x (variante não encontrada)",
                        ];
                    }
                }
                $itemData['kit_items'] = $kitItems;

                // Criar descrição completa do kit para impressão
                $kitDescriptions = collect($kitItems)->pluck('description')->join(', ');
                $itemData['description'] = "{$item->kit->name}: {$kitDescriptions}";
            }

            return $itemData;
        });

        return response()->json([
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'status_label' => match($order->status) {
                    'pending'    => 'Pendente',
                    'processing' => 'Em processamento',
                    'approved'   => 'Aprovado',
                    'paid'       => 'Pago',
                    'completed'  => 'Concluído',
                    'delivered'  => 'Entregue',
                    'rejected'   => 'Rejeitado',
                    'cancelled'  => 'Cancelado',
                    'refunded'   => 'Reembolsado',
                    default      => $order->status ?? 'Desconhecido',
                },
                'created_at' => $order->created_at->format('d/m/Y H:i'),
                'payment_method' => match($order->payment_method) {
                    'pix', 'pix_manual' => 'PIX',
                    'credit_card'         => 'Cartão de Crédito',
                    'credit_card_machine' => 'Cartão de Crédito (Maquininha)',
                    'debit_card'          => 'Cartão de Débito',
                    'cash'                => 'Dinheiro',
                    default               => $order->payment_method ?? 'Não informado',
                },
            ],
            'customer' => [
                'name'  => $order->user->name  ?? $order->customer_name  ?? 'Consumidor Final',
                'email' => $order->user->email ?? null,
                'phone' => $order->user->phone ?? $order->customer_phone ?? null,
            ],
            'items' => $items,
            'totals' => [
                'subtotal' => (float) ($order->total_amount - $order->delivery_fee + ($order->discount_amount ?? 0)),
                'delivery_fee' => (float) $order->delivery_fee,
                'discount' => (float) ($order->discount_amount ?? 0),
                'total' => (float) $order->total_amount,
            ],
            'coupon' => $order->coupon_code ? [
                'code' => $order->coupon_code,
                'discount' => (float) ($order->discount_amount ?? 0),
            ] : null,
            'excursion_info' => $order->excursion_info,
        ]);
    }

    /**
     * Atualiza o status de um pedido (admin)
     */
    public function updateStatus(UpdateStatusRequest $request, string $orderId)
    {
        $status = $request->validated()['status'];
        $order = $this->orders->updateStatus($orderId, $status);

        return response()->json([
            'message' => 'Status do pedido atualizado com sucesso.',
            'order' => new OrderResource($order),
        ]);
    }

    /**
     * Retorna estatísticas e analytics dos pedidos
     * GET /api/orders/analytics?period=30
     */
    public function analytics(Request $request)
    {
        $request->validate([
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to'   => 'nullable|date_format:Y-m-d|after_or_equal:date_from',
            'period'    => 'nullable|integer|min:1|max:365',
        ]);

        $period   = $request->query('period');
        $dateFrom = $request->query('date_from');
        $dateTo   = $request->query('date_to');

        $query = Order::query();

        if ($dateFrom) {
            $query->where('created_at', '>=', \Carbon\Carbon::parse($dateFrom)->startOfDay());
            $to = $dateTo ? \Carbon\Carbon::parse($dateTo)->endOfDay() : now()->endOfDay();
            $query->where('created_at', '<=', $to);
        } elseif ($period) {
            $query->where('created_at', '>=', now()->subDays((int) $period));
        }

        $orderIds = $query->pluck('id');

        // 1. Produtos mais vendidos
        $topProducts = OrderItem::whereIn('order_id', $orderIds)
            ->select('product_id', DB::raw('SUM(quantity) as total_quantity'))
            ->with('product:id,name,slug')
            ->groupBy('product_id')
            ->orderByDesc('total_quantity')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                return [
                    'product_id' => $item->product_id,
                    'product_name' => $item->product->name ?? 'Produto não encontrado',
                    'product_slug' => $item->product->slug ?? null,
                    'total_sold' => (int) $item->total_quantity,
                ];
            });

        // 2. Cores mais vendidas
        $allItems = OrderItem::whereIn('order_id', $orderIds)->get();

        $topColors = $allItems
            ->filter(fn ($item) => !empty($item->color))
            ->groupBy('color')
            ->map(function ($items, $color) {
                return [
                    'color' => $color,
                    'total_sold' => $items->sum('quantity'),
                ];
            })
            ->sortByDesc('total_sold')
            ->take(10)
            ->values();

        // 3. Tamanhos mais vendidos
        $topSizes = $allItems
            ->filter(fn ($item) => !empty($item->size))
            ->groupBy('size')
            ->map(function ($items, $size) {
                return [
                    'size' => $size,
                    'total_sold' => $items->sum('quantity'),
                ];
            })
            ->sortByDesc('total_sold')
            ->take(10)
            ->values();

        // 4. Regiões por DDD
        $regionsByDDD = Order::whereIn('id', $orderIds)
            ->with('user:id,phone')
            ->get()
            ->map(function ($order) {
                $phone = preg_replace('/\D/', '', $order->user->phone ?? '');
                $ddd = strlen($phone) >= 10 ? substr($phone, 0, 2) : null;
                return $ddd;
            })
            ->filter()
            ->countBy()
            ->map(function ($count, $ddd) {
                return [
                    'ddd' => $ddd,
                    'total_orders' => $count,
                    'region' => $this->getDDDRegion($ddd),
                ];
            })
            ->sortByDesc('total_orders')
            ->values();

        // 5. Clientes que mais fizeram pedidos
        $topCustomers = Order::whereIn('id', $orderIds)
            ->select('user_id', DB::raw('COUNT(*) as total_orders'), DB::raw('SUM(total_amount) as total_spent'))
            ->with('user:id,name,email')
            ->groupBy('user_id')
            ->orderByDesc('total_orders')
            ->limit(10)
            ->get()
            ->map(function ($order) {
                return [
                    'user_id' => $order->user_id,
                    'user_name' => $order->user->name ?? 'Usuário não encontrado',
                    'user_email' => $order->user->email ?? null,
                    'total_orders' => (int) $order->total_orders,
                    'total_spent' => (float) $order->total_spent,
                ];
            });

        // 6. Clientes que menos fizeram pedidos (mas fizeram pelo menos 1)
        $bottomCustomers = Order::whereIn('id', $orderIds)
            ->select('user_id', DB::raw('COUNT(*) as total_orders'), DB::raw('SUM(total_amount) as total_spent'))
            ->with('user:id,name,email')
            ->groupBy('user_id')
            ->orderBy('total_orders', 'asc')
            ->limit(10)
            ->get()
            ->map(function ($order) {
                return [
                    'user_id' => $order->user_id,
                    'user_name' => $order->user->name ?? 'Usuário não encontrado',
                    'user_email' => $order->user->email ?? null,
                    'total_orders' => (int) $order->total_orders,
                    'total_spent' => (float) $order->total_spent,
                ];
            });

        return response()->json([
            'period_days' => $period ? (int) $period : 'all',
            'total_orders' => $orderIds->count(),
            'top_products' => $topProducts,
            'top_colors' => $topColors,
            'top_sizes' => $topSizes,
            'regions_by_ddd' => $regionsByDDD,
            'top_customers' => $topCustomers,
            'bottom_customers' => $bottomCustomers,
        ]);
    }

    /**
     * Exporta pedidos para CSV
     * GET /api/orders/export?format=csv&period=30
     */
    public function export(Request $request)
    {
        $request->validate([
            'date_from' => 'required_without:period|nullable|date_format:Y-m-d',
            'date_to'   => 'nullable|date_format:Y-m-d|after_or_equal:date_from',
            'period'    => 'required_without:date_from|nullable|integer|min:1|max:365',
            'format'    => 'nullable|in:csv,xlsx',
        ]);

        $format   = $request->query('format', 'csv');
        $period   = $request->query('period');
        $dateFrom = $request->query('date_from');
        $dateTo   = $request->query('date_to');

        $query = Order::with(['user', 'items.product', 'items.variant']);

        if ($dateFrom) {
            $query->where('created_at', '>=', \Carbon\Carbon::parse($dateFrom)->startOfDay());
            $to = $dateTo ? \Carbon\Carbon::parse($dateTo)->endOfDay() : now()->endOfDay();
            $query->where('created_at', '<=', $to);
        } else {
            $query->where('created_at', '>=', now()->subDays((int) $period));
        }

        if ($format === 'csv') {
            return $this->exportCSV($query->orderBy('created_at', 'desc'));
        }

        if ($format === 'xlsx') {
            // XLSX é gerado em memória (ZIP): limita a 5000 registros
            $orders = $query->orderBy('created_at', 'desc')->limit(5000)->get();
            return $this->exportXLSX($orders);
        }

        return response()->json(['message' => 'Formato não suportado'], 400);
    }

    /**
     * Gera arquivo CSV dos pedidos
     */
    private function exportCSV($query)
    {
        $filename = 'pedidos_' . now()->format('Y-m-d_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($query) {
            $file = fopen('php://output', 'w');
            $orders = $query->cursor(); // lazy loading — uma linha por vez na memória

            // BOM para UTF-8
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // Cabeçalho
            fputcsv($file, [
                'Número do Pedido',
                'Data',
                'Cliente',
                'Email',
                'Telefone',
                'Produtos',
                'Cor',
                'Tamanho',
                'Quantidade',
                'Valor Unitário',
                'Subtotal',
                'Taxa de Entrega',
                'Desconto',
                'Total',
                'Método de Pagamento',
                'Status',
                'DDD',
            ]);

            // Dados
            foreach ($orders as $order) {
                foreach ($order->items as $item) {
                    $phone = preg_replace('/\D/', '', $order->user->phone ?? '');
                    $ddd = strlen($phone) >= 10 ? substr($phone, 0, 2) : '';

                    fputcsv($file, [
                        $order->order_number,
                        $order->created_at->format('d/m/Y H:i'),
                        $order->user->name ?? '',
                        $order->user->email ?? '',
                        $order->user->phone ?? '',
                        $item->product->name ?? '',
                        $item->variant->color ?? '',
                        $item->variant->size ?? '',
                        $item->quantity,
                        number_format($item->unit_price, 2, ',', '.'),
                        number_format($item->total_price, 2, ',', '.'),
                        number_format($order->delivery_fee, 2, ',', '.'),
                        number_format($order->discount_amount, 2, ',', '.'),
                        number_format($order->total_amount, 2, ',', '.'),
                        $order->payment_method === 'pix' ? 'PIX' : 'Cartão de Crédito',
                        match($order->status) {
                            'pending' => 'Pendente',
                            'approved' => 'Aprovado',
                            'rejected' => 'Rejeitado',
                            'cancelled' => 'Cancelado',
                            'refunded' => 'Reembolsado',
                            default => $order->status,
                        },
                        $ddd,
                    ]);
                }
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Gera arquivo XLSX dos pedidos
     */
    private function exportXLSX($orders)
    {
        $filename = 'pedidos_' . now()->format('Y-m-d_His') . '.xlsx';

        $headers = [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($orders) {
            // Criar estrutura XLSX (ZIP com XMLs)
            $zip = new \ZipArchive();
            $tempFile = tempnam(sys_get_temp_dir(), 'xlsx');

            if ($zip->open($tempFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                throw new \Exception('Não foi possível criar arquivo XLSX');
            }

            // [Content_Types].xml
            $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
    <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
    <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>';
            $zip->addFromString('[Content_Types].xml', $contentTypes);

            // _rels/.rels
            $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>';
            $zip->addFromString('_rels/.rels', $rels);

            // xl/_rels/workbook.xml.rels
            $workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
    <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>';
            $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRels);

            // xl/workbook.xml
            $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <sheets>
        <sheet name="Pedidos" sheetId="1" r:id="rId1"/>
    </sheets>
</workbook>';
            $zip->addFromString('xl/workbook.xml', $workbook);

            // xl/styles.xml (com estilos para datas e números)
            $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <numFmts count="2">
        <numFmt numFmtId="164" formatCode="dd/mm/yyyy hh:mm"/>
        <numFmt numFmtId="165" formatCode="#,##0.00"/>
    </numFmts>
    <fonts count="2">
        <font><sz val="11"/><name val="Calibri"/></font>
        <font><b/><sz val="11"/><name val="Calibri"/></font>
    </fonts>
    <fills count="2">
        <fill><patternFill patternType="none"/></fill>
        <fill><patternFill patternType="gray125"/></fill>
    </fills>
    <borders count="1">
        <border><left/><right/><top/><bottom/><diagonal/></border>
    </borders>
    <cellXfs count="4">
        <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
        <xf numFmtId="0" fontId="1" fillId="0" borderId="0"/>
        <xf numFmtId="164" fontId="0" fillId="0" borderId="0"/>
        <xf numFmtId="165" fontId="0" fillId="0" borderId="0"/>
    </cellXfs>
</styleSheet>';
            $zip->addFromString('xl/styles.xml', $styles);

            // xl/worksheets/sheet1.xml (dados)
            $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <sheetData>';

            // Cabeçalho (linha 1)
            $sheet .= '<row r="1">';
            $headers = [
                'Número do Pedido', 'Data', 'Cliente', 'Email', 'Telefone',
                'Produtos', 'Cor', 'Tamanho', 'Quantidade', 'Valor Unitário',
                'Subtotal', 'Taxa de Entrega', 'Desconto', 'Total',
                'Método de Pagamento', 'Status', 'DDD'
            ];

            $col = 'A';
            foreach ($headers as $header) {
                $sheet .= '<c r="' . $col . '1" s="1" t="inlineStr"><is><t>' . htmlspecialchars($header, ENT_XML1) . '</t></is></c>';
                $col++;
            }
            $sheet .= '</row>';

            // Dados
            $row = 2;
            foreach ($orders as $order) {
                foreach ($order->items as $item) {
                    $phone = preg_replace('/\D/', '', $order->user->phone ?? '');
                    $ddd = strlen($phone) >= 10 ? substr($phone, 0, 2) : '';

                    $status = match($order->status) {
                        'pending' => 'Pendente',
                        'approved' => 'Aprovado',
                        'rejected' => 'Rejeitado',
                        'cancelled' => 'Cancelado',
                        'refunded' => 'Reembolsado',
                        default => $order->status,
                    };

                    $sheet .= '<row r="' . $row . '">';

                    // A - Número do Pedido
                    $sheet .= '<c r="A' . $row . '" t="inlineStr"><is><t>' . htmlspecialchars($order->order_number, ENT_XML1) . '</t></is></c>';

                    // B - Data (formato texto)
                    $sheet .= '<c r="B' . $row . '" t="inlineStr"><is><t>' . $order->created_at->format('d/m/Y H:i') . '</t></is></c>';

                    // C - Cliente
                    $sheet .= '<c r="C' . $row . '" t="inlineStr"><is><t>' . htmlspecialchars($order->user->name ?? '', ENT_XML1) . '</t></is></c>';

                    // D - Email
                    $sheet .= '<c r="D' . $row . '" t="inlineStr"><is><t>' . htmlspecialchars($order->user->email ?? '', ENT_XML1) . '</t></is></c>';

                    // E - Telefone
                    $sheet .= '<c r="E' . $row . '" t="inlineStr"><is><t>' . htmlspecialchars($order->user->phone ?? '', ENT_XML1) . '</t></is></c>';

                    // F - Produtos
                    $sheet .= '<c r="F' . $row . '" t="inlineStr"><is><t>' . htmlspecialchars($item->product->name ?? '', ENT_XML1) . '</t></is></c>';

                    // G - Cor
                    $sheet .= '<c r="G' . $row . '" t="inlineStr"><is><t>' . htmlspecialchars($item->variant->color ?? '', ENT_XML1) . '</t></is></c>';

                    // H - Tamanho
                    $sheet .= '<c r="H' . $row . '" t="inlineStr"><is><t>' . htmlspecialchars($item->variant->size ?? '', ENT_XML1) . '</t></is></c>';

                    // I - Quantidade
                    $sheet .= '<c r="I' . $row . '"><v>' . $item->quantity . '</v></c>';

                    // J - Valor Unitário (número)
                    $sheet .= '<c r="J' . $row . '" s="3"><v>' . $item->unit_price . '</v></c>';

                    // K - Subtotal (número)
                    $sheet .= '<c r="K' . $row . '" s="3"><v>' . $item->total_price . '</v></c>';

                    // L - Taxa de Entrega (número)
                    $sheet .= '<c r="L' . $row . '" s="3"><v>' . $order->delivery_fee . '</v></c>';

                    // M - Desconto (número)
                    $sheet .= '<c r="M' . $row . '" s="3"><v>' . $order->discount_amount . '</v></c>';

                    // N - Total (número)
                    $sheet .= '<c r="N' . $row . '" s="3"><v>' . $order->total_amount . '</v></c>';

                    // O - Método de Pagamento
                    $paymentMethod = $order->payment_method === 'pix' ? 'PIX' : 'Cartão de Crédito';
                    $sheet .= '<c r="O' . $row . '" t="inlineStr"><is><t>' . $paymentMethod . '</t></is></c>';

                    // P - Status
                    $sheet .= '<c r="P' . $row . '" t="inlineStr"><is><t>' . htmlspecialchars($status, ENT_XML1) . '</t></is></c>';

                    // Q - DDD
                    $sheet .= '<c r="Q' . $row . '" t="inlineStr"><is><t>' . $ddd . '</t></is></c>';

                    $sheet .= '</row>';
                    $row++;
                }
            }

            $sheet .= '</sheetData></worksheet>';

            $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
            $zip->close();

            // Ler arquivo e retornar
            $content = file_get_contents($tempFile);
            unlink($tempFile);

            echo $content;
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Mapeia DDD para região
     */
    private function getDDDRegion(string $ddd): string
    {
        $regions = [
            '11' => 'São Paulo - SP',
            '12' => 'São José dos Campos - SP',
            '13' => 'Santos - SP',
            '14' => 'Bauru - SP',
            '15' => 'Sorocaba - SP',
            '16' => 'Ribeirão Preto - SP',
            '17' => 'São José do Rio Preto - SP',
            '18' => 'Presidente Prudente - SP',
            '19' => 'Campinas - SP',
            '21' => 'Rio de Janeiro - RJ',
            '22' => 'Campos dos Goytacazes - RJ',
            '24' => 'Volta Redonda - RJ',
            '27' => 'Vitória - ES',
            '28' => 'Cachoeiro de Itapemirim - ES',
            '31' => 'Belo Horizonte - MG',
            '32' => 'Juiz de Fora - MG',
            '33' => 'Governador Valadares - MG',
            '34' => 'Uberlândia - MG',
            '35' => 'Poços de Caldas - MG',
            '37' => 'Divinópolis - MG',
            '38' => 'Montes Claros - MG',
            '41' => 'Curitiba - PR',
            '42' => 'Ponta Grossa - PR',
            '43' => 'Londrina - PR',
            '44' => 'Maringá - PR',
            '45' => 'Foz do Iguaçu - PR',
            '46' => 'Francisco Beltrão - PR',
            '47' => 'Joinville - SC',
            '48' => 'Florianópolis - SC',
            '49' => 'Chapecó - SC',
            '51' => 'Porto Alegre - RS',
            '53' => 'Pelotas - RS',
            '54' => 'Caxias do Sul - RS',
            '55' => 'Santa Maria - RS',
            '61' => 'Brasília - DF',
            '62' => 'Goiânia - GO',
            '63' => 'Palmas - TO',
            '64' => 'Rio Verde - GO',
            '65' => 'Cuiabá - MT',
            '66' => 'Rondonópolis - MT',
            '67' => 'Campo Grande - MS',
            '68' => 'Rio Branco - AC',
            '69' => 'Porto Velho - RO',
            '71' => 'Salvador - BA',
            '73' => 'Ilhéus - BA',
            '74' => 'Juazeiro - BA',
            '75' => 'Feira de Santana - BA',
            '77' => 'Barreiras - BA',
            '79' => 'Aracaju - SE',
            '81' => 'Recife - PE',
            '82' => 'Maceió - AL',
            '83' => 'João Pessoa - PB',
            '84' => 'Natal - RN',
            '85' => 'Fortaleza - CE',
            '86' => 'Teresina - PI',
            '87' => 'Petrolina - PE',
            '88' => 'Juazeiro do Norte - CE',
            '89' => 'Picos - PI',
            '91' => 'Belém - PA',
            '92' => 'Manaus - AM',
            '93' => 'Santarém - PA',
            '94' => 'Marabá - PA',
            '95' => 'Boa Vista - RR',
            '96' => 'Macapá - AP',
            '97' => 'Coari - AM',
            '98' => 'São Luís - MA',
            '99' => 'Imperatriz - MA',
        ];

        return $regions[$ddd] ?? "DDD {$ddd}";
    }
}
