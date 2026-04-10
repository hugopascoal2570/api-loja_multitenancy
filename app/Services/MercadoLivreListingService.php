<?php

namespace App\Services;

use App\Models\MercadoLivreListing;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class MercadoLivreListingService
{
    public function __construct(private MercadoLivreService $ml) {}

    // -------------------------------------------------------------------------
    // Publicar / atualizar
    // -------------------------------------------------------------------------

    public function publishProduct(Product $product): MercadoLivreListing
    {
        $existing = MercadoLivreListing::where('product_id', $product->id)->first();

        if ($existing) {
            // Item fechado no ML não pode ser reativado — remove o registro local e recria do zero
            if ($existing->status === 'closed') {
                $existing->delete();
            } else {
                return $this->updateListing($existing, $product);
            }
        }

        $product->load(['variants', 'images', 'measurements']);

        // Cria chart de medidas da peça antes de montar o payload
        $pieceChartId = $this->syncPieceSizeChart($product);

        $payload = $this->buildPayload($product, $pieceChartId);

        Log::info('ML: payload enviado para /items', $payload);

        $response   = $this->ml->post('/items', $payload);
        $mlItemId   = $response['id'];
        $mlCategory = $response['category_id'] ?? $payload['category_id'];

        // Descrição em chamada separada (ML exige)
        $this->syncDescription($mlItemId, $product->description ?? '', $product);

        $listing = MercadoLivreListing::create([
            'product_id'        => $product->id,
            'ml_item_id'        => $mlItemId,
            'ml_category_id'    => $mlCategory,
            'ml_piece_chart_id' => $pieceChartId,
            'status'            => 'active',
            'synced_at'         => now(),
        ]);

        return $listing;
    }

    public function updateListing(MercadoLivreListing $listing, Product $product): MercadoLivreListing
    {
        $product->load(['variants', 'images', 'measurements']);

        // Recria/atualiza chart de medidas da peça
        $pieceChartId = $this->syncPieceSizeChart($product, $listing->ml_piece_chart_id);

        $payload = $this->buildPayload($product, $pieceChartId);

        // ML não permite atualizar category_id — removemos do payload de update
        unset($payload['category_id']);

        try {
            $this->ml->put("/items/{$listing->ml_item_id}", $payload);
            $this->syncDescription($listing->ml_item_id, $product->description ?? '');

            $listing->update([
                'ml_piece_chart_id' => $pieceChartId,
                'status'            => 'active',
                'synced_at'         => now(),
                'last_error'        => null,
            ]);
        } catch (\Exception $e) {
            $listing->update(['last_error' => $e->getMessage()]);
            throw $e;
        }

        return $listing->fresh();
    }

    // -------------------------------------------------------------------------
    // Pausar / reativar
    // -------------------------------------------------------------------------

    public function pauseListing(MercadoLivreListing $listing): void
    {
        $this->ml->put("/items/{$listing->ml_item_id}", ['status' => 'paused']);
        $listing->update(['status' => 'paused', 'synced_at' => now()]);
    }

    public function activateListing(MercadoLivreListing $listing): void
    {
        $this->ml->put("/items/{$listing->ml_item_id}", ['status' => 'active']);
        $listing->update(['status' => 'active', 'synced_at' => now()]);
    }

    // -------------------------------------------------------------------------
    // Sincronizar estoque de uma variante
    // -------------------------------------------------------------------------

    public function syncVariantStock(MercadoLivreListing $listing, ProductVariant $variant): void
    {
        // Busca o ID da variação ML que tem o SKU do nosso variant
        $mlItem = $this->ml->get("/items/{$listing->ml_item_id}");

        $mlVariation = collect($mlItem['variations'] ?? [])
            ->first(fn($v) => ($v['seller_custom_field'] ?? null) === $variant->sku);

        if (!$mlVariation) {
            Log::warning('ML: variação não encontrada para sync de estoque', [
                'ml_item_id' => $listing->ml_item_id,
                'sku'        => $variant->sku,
            ]);
            return;
        }

        $this->ml->put("/items/{$listing->ml_item_id}/variations/{$mlVariation['id']}", [
            'available_quantity' => max(0, $variant->stock),
        ]);
    }

    // -------------------------------------------------------------------------
    // Builders internos
    // -------------------------------------------------------------------------

    public function buildPayload(Product $product, ?string $pieceChartId = null): array
    {
        $price       = $this->resolvePrice($product);
        $pictureIds  = $this->uploadPictures($product);
        [$categoryId] = $this->predictCategoryAndDomain($product);
        $sizeChart   = $this->resolveSizeChart();
        $sizeGridId  = $sizeChart['chart_id'] ?? null;
        $attributes  = $this->buildAttributes($product, $categoryId);

        // Chart do corpo (medidas do corpo)
        if ($sizeGridId) {
            $attributes[] = ['id' => 'SIZE_GRID_ID', 'value_name' => $sizeGridId];
        }

        // Chart da peça (medidas da roupa) — habilita aba "Da peça" no ML
        if ($pieceChartId) {
            $attributes[] = ['id' => 'SIZE_GRID_ID', 'value_name' => $pieceChartId];
        }

        return [
            'title'              => $product->name,
            'category_id'        => $categoryId,
            'price'              => $price,
            'currency_id'        => 'BRL',
            'available_quantity' => $product->variants->sum('stock'),
            'buying_mode'        => 'buy_it_now',
            'condition'          => 'new',
            'listing_type_id'    => env('ML_LISTING_TYPE', 'gold_special'),
            'pictures'           => array_map(fn($id) => ['id' => $id], $pictureIds),
            'variations'         => $this->buildVariations($product, $price, $sizeChart['rows'] ?? [], $pictureIds),
            'shipping'           => $this->buildShipping($product),
            'attributes'         => $attributes,
        ];
    }

    /**
     * Cria ou atualiza o chart de medidas da PEÇA no ML.
     * Retorna o ID do chart criado, ou null se o produto não tiver medidas.
     *
     * Mapeamento dos campos do ProductMeasurement para atributos do ML:
     *   waist/waistband → WAIST   (cós da peça)
     *   hip             → HIP     (quadril da peça)
     *   length          → LENGTH  (comprimento da peça)
     *   inseam          → INSEAM  (entreperna)
     *   thigh           → THIGH_WIDTH (coxa)
     *   bust            → CHEST   (busto/peito da peça)
     *   shoulder        → SHOULDER_WIDTH (ombro)
     */
    public function syncPieceSizeChart(Product $product, ?string $existingChartId = null): ?string
    {
        $measurements = $product->measurements ?? collect();

        if ($measurements->isEmpty()) {
            return null;
        }

        // Mapeamento campo → atributo ML
        $fieldMap = [
            'waistband' => 'WAIST',
            'waist'     => 'WAIST',
            'hip'       => 'HIP',
            'length'    => 'LENGTH',
            'inseam'    => 'INSEAM',
            'thigh'     => 'THIGH_WIDTH',
            'bust'      => 'CHEST',
            'shoulder'  => 'SHOULDER_WIDTH',
        ];

        // Descobre quais campos têm valor
        $activeFields = [];
        foreach ($fieldMap as $field => $mlAttr) {
            if ($measurements->whereNotNull($field)->isNotEmpty()) {
                $activeFields[$field] = $mlAttr;
            }
        }

        if (empty($activeFields)) {
            return null;
        }

        // Monta as linhas do chart — uma por tamanho
        $rows = [];
        foreach ($measurements->sortBy('sort_order') as $m) {
            $attrs = [
                ['id' => 'SIZE', 'values' => [['name' => (string) $m->size]]],
            ];

            $seen = []; // evita duplicar WAIST se ambos waistband e waist existirem
            foreach ($activeFields as $field => $mlAttr) {
                if (isset($seen[$mlAttr])) continue;
                if ($m->$field !== null) {
                    $attrs[] = ['id' => $mlAttr, 'values' => [['name' => (string) $m->$field]]];
                    $seen[$mlAttr] = true;
                }
            }

            $rows[] = ['attributes' => $attrs];
        }

        $payload = [
            'site_id' => 'MLB',
            'name'    => $product->name . ' - Medidas da Peça',
            'rows'    => $rows,
        ];

        try {
            // Se já existe um chart, tenta atualizar; senão cria novo
            if ($existingChartId) {
                $this->ml->put("/size_charts/{$existingChartId}", $payload);
                Log::info('ML: piece size chart atualizado', ['chart_id' => $existingChartId]);
                return $existingChartId;
            }

            $response = $this->ml->post('/size_charts', $payload);
            $chartId  = (string) ($response['id'] ?? '');

            if ($chartId) {
                Log::info('ML: piece size chart criado', ['chart_id' => $chartId, 'product' => $product->name]);
            }

            return $chartId ?: null;
        } catch (\Throwable $e) {
            Log::warning('ML: falha ao criar/atualizar piece size chart', [
                'product' => $product->name,
                'error'   => $e->getMessage(),
            ]);
            return $existingChartId; // mantém o existente se update falhar
        }
    }

    /**
     * Monta o bloco de shipping com dimensões e peso do produto.
     * O ML usa essas informações para calcular o frete corretamente.
     * Peso em gramas, dimensões em cm.
     */
    private function buildShipping(Product $product): array
    {
        $shipping = [
            'mode'          => 'me2',
            'local_pick_up' => false,
            'free_shipping' => false,
        ];

        // Só envia dimensions se o produto tiver peso e ao menos uma dimensão cadastrada
        if ($product->weight && ($product->width || $product->height || $product->length)) {
            $shipping['dimensions'] = sprintf(
                '%dx%dx%d,%d',
                (int) round($product->width  ?? 1),  // largura em cm
                (int) round($product->height ?? 1),  // altura em cm
                (int) round($product->length ?? 1),  // comprimento em cm
                (int) round($product->weight * 1000) // peso em gramas (produto salvo em kg)
            );
        }

        return $shipping;
    }

    /**
     * Monta os atributos do item buscando valores válidos da categoria no ML.
     * Atributos de lista (PANT_TYPE, etc.) são detectados pelo nome do produto.
     */
    private function buildAttributes(Product $product, string $categoryId): array
    {
        $fixed = [
            ['id' => 'BRAND',         'value_name' => env('ML_BRAND', 'Clochic')],
            ['id' => 'MAIN_MATERIAL', 'value_name' => 'Tecido'],
            ['id' => 'MODEL',         'value_name' => $product->name],
            ['id' => 'GENDER',        'value_name' => 'Feminino'],
        ];

        // Atributos já tratados — não tentar matching automático
        $skip = ['BRAND', 'MAIN_MATERIAL', 'MODEL', 'GENDER', 'SIZE_GRID_ID',
                 'SIZE_GRID_ROW_ID', 'SIZE', 'COLOR', 'FILTRABLE_SIZE'];

        $categoryAttrs = $this->fetchCategoryAttributes($categoryId);

        // Palavras normalizadas do nome do produto para matching
        $nameWords = collect(
            explode(' ', mb_strtolower(preg_replace('/[^a-zA-ZÀ-ú\s]/u', ' ', $product->name)))
        )->filter()->values();

        foreach ($categoryAttrs as $attr) {
            if (in_array($attr['id'], $skip)) {
                continue;
            }

            // Só processa atributos de lista com valores conhecidos
            if (($attr['value_type'] ?? '') !== 'list' || empty($attr['values'])) {
                continue;
            }

            $matched = collect($attr['values'])->first(function ($value) use ($nameWords) {
                $valueLower = mb_strtolower($value['name'] ?? '');
                return $nameWords->contains(
                    fn($word) => strlen($word) >= 4 &&
                                 (str_contains($valueLower, $word) || str_contains($word, $valueLower))
                );
            });

            if ($matched) {
                $fixed[] = ['id' => $attr['id'], 'value_name' => $matched['name']];
                Log::info("ML: atributo detectado [{$attr['id']}={$matched['name']}]", ['produto' => $product->name]);
            }
        }

        return $fixed;
    }

    private function fetchCategoryAttributes(string $categoryId): array
    {
        $cacheKey = "ml_category_attrs_{$categoryId}";
        $cached   = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        try {
            $attrs = $this->ml->get("/categories/{$categoryId}/attributes");
            Cache::put($cacheKey, $attrs, now()->addDays(7));
            return $attrs;
        } catch (\Throwable $e) {
            Log::warning('ML: falha ao buscar atributos da categoria', ['category' => $categoryId]);
            return [];
        }
    }

    private function uploadPictures(Product $product): array
    {
        $base        = rtrim(str_replace('http://', 'https://', env('APP_API_URL', env('APP_URL'))), '/');
        $accessToken = $this->ml->getValidToken()->access_token;
        $ids         = [];

        foreach ($product->images->take(10) as $img) {
            $resp = Http::withToken($accessToken)
                ->post('https://api.mercadolibre.com/pictures', [
                    'source' => $base . $img->url,
                ]);

            if ($resp->successful() && !empty($resp->json()['id'])) {
                $ids[] = $resp->json()['id'];
            }
        }

        Log::info('ML: imagens enviadas', ['count' => count($ids), 'ids' => $ids]);

        return $ids;
    }

    /**
     * Retorna ['chart_id' => '5026750', 'rows' => ['PP' => '5026750:1', ...]]
     * Prioriza ML_SIZE_CHART_ID do .env; faz GET no ML e cacheia.
     */
    private function resolveSizeChart(): ?array
    {
        $envChartId = env('ML_SIZE_CHART_ID');
        if (!$envChartId) {
            return null;
        }

        $cacheKey = "ml_size_chart_fetched_{$envChartId}";
        $cached   = Cache::get($cacheKey);
        if (is_array($cached) && !empty($cached['chart_id'])) {
            return $cached;
        }

        try {
            $accessToken = $this->ml->getValidToken()->access_token;
            $resp = Http::withToken($accessToken)
                ->get("https://api.mercadolibre.com/catalog/charts/{$envChartId}");

            Log::info('ML: GET catalog/charts', [
                'chart_id' => $envChartId,
                'status'   => $resp->status(),
            ]);

            if ($resp->successful() && !empty($resp->json()['id'])) {
                $result = $this->parseChart($resp->json());
                Cache::put($cacheKey, $result, now()->addDays(30));
                return $result;
            }
        } catch (\Throwable $e) {
            Log::error('ML: erro ao buscar size chart', ['error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Medidas corporais padrão brasileiras (ABNT NBR 13377) por tamanho.
     * Quadril (hip) e cintura (waist) em cm.
     */
    private const BR_SIZE_CHART = [
        'PP'   => ['hip_from' =>  84, 'hip_to' =>  87, 'waist_from' => 60, 'waist_to' => 63],
        'P'    => ['hip_from' =>  88, 'hip_to' =>  91, 'waist_from' => 64, 'waist_to' => 67],
        'M'    => ['hip_from' =>  92, 'hip_to' =>  95, 'waist_from' => 68, 'waist_to' => 71],
        'G'    => ['hip_from' =>  96, 'hip_to' =>  99, 'waist_from' => 72, 'waist_to' => 75],
        'GG'   => ['hip_from' => 100, 'hip_to' => 103, 'waist_from' => 76, 'waist_to' => 79],
        'XGG'  => ['hip_from' => 104, 'hip_to' => 107, 'waist_from' => 80, 'waist_to' => 83],
        'XGGG' => ['hip_from' => 108, 'hip_to' => 111, 'waist_from' => 84, 'waist_to' => 87],
        '34'   => ['hip_from' =>  84, 'hip_to' =>  87, 'waist_from' => 60, 'waist_to' => 63],
        '36'   => ['hip_from' =>  87, 'hip_to' =>  90, 'waist_from' => 63, 'waist_to' => 66],
        '38'   => ['hip_from' =>  90, 'hip_to' =>  93, 'waist_from' => 66, 'waist_to' => 69],
        '40'   => ['hip_from' =>  93, 'hip_to' =>  96, 'waist_from' => 69, 'waist_to' => 72],
        '42'   => ['hip_from' =>  96, 'hip_to' =>  99, 'waist_from' => 72, 'waist_to' => 75],
        '44'   => ['hip_from' =>  99, 'hip_to' => 102, 'waist_from' => 75, 'waist_to' => 78],
        '46'   => ['hip_from' => 102, 'hip_to' => 105, 'waist_from' => 78, 'waist_to' => 81],
        '48'   => ['hip_from' => 105, 'hip_to' => 108, 'waist_from' => 81, 'waist_to' => 84],
        '50'   => ['hip_from' => 108, 'hip_to' => 111, 'waist_from' => 84, 'waist_to' => 87],
    ];

    /**
     * Cria guia de tamanhos via POST /catalog/charts com medidas padrão brasileiras.
     * Retorna ['chart_id' => '123', 'rows' => ['PP' => '123:1', ...]]
     */
    private function getOrCreateSizeChart(array $sizes, string $domainId, int $sellerId): ?array
    {
        $cacheKey = "ml_size_chart_v5_{$sellerId}_{$domainId}";
        $cached   = Cache::get($cacheKey);
        if (is_array($cached) && !empty($cached['chart_id'])) {
            Log::info('ML: size chart cache hit', ['chart_id' => $cached['chart_id']]);
            return $cached;
        }

        $accessToken = $this->ml->getValidToken()->access_token;

        // 1. Busca guias existentes do vendedor para o domínio
        $searchResp = Http::withToken($accessToken)
            ->post('https://api.mercadolibre.com/catalog/charts/search', [
                'domain_id'        => $domainId,
                'site_id'          => 'MLB',
                'seller_id'        => $sellerId,
                'filter_attributes' => [
                    ['id' => 'GENDER', 'values' => [['id' => '339665']]],
                ],
            ]);

        Log::info('ML: catalog/charts/search', [
            'status' => $searchResp->status(),
            'body'   => $searchResp->json(),
        ]);

        if ($searchResp->successful() && !empty($searchResp->json()['charts'][0])) {
            $result = $this->parseChart($searchResp->json()['charts'][0]);
            Cache::put($cacheKey, $result, now()->addDays(30));
            Log::info('ML: size chart encontrado via search', ['chart_id' => $result['chart_id']]);
            return $result;
        }

        // 2. Cria novo chart
        $rows = collect($sizes)->unique()->values()
            ->map(fn($s) => ['attributes' => $this->buildChartRowAttributes($s)])
            ->toArray();

        $createResp = Http::withToken($accessToken)
            ->post('https://api.mercadolibre.com/catalog/charts', [
                'names'               => ['MLB' => 'Guia de Tamanhos'],
                'domain_id'           => $domainId,
                'site_id'             => 'MLB',
                'type'                => 'SPECIFIC',
                'seller_id'           => $sellerId,
                'measure_type'        => 'BODY_MEASURE',
                'main_attribute'      => ['attributes' => [['site_id' => 'MLB', 'id' => 'SIZE']]],
                'secondary_attribute' => ['attributes' => []],
                'attributes'          => [
                    ['id' => 'GENDER', 'values' => [['id' => '339665', 'name' => 'Feminino']]],
                ],
                'rows' => $rows,
            ]);

        Log::info('ML: catalog/charts POST', [
            'status' => $createResp->status(),
            'body'   => $createResp->json(),
        ]);

        if ($createResp->successful() && !empty($createResp->json()['id'])) {
            $result = $this->parseChart($createResp->json());
            Cache::put($cacheKey, $result, now()->addDays(30));
            return $result;
        }

        // Se o nome já está em uso, busca o chart existente do vendedor
        $errors = collect($createResp->json()['errors'] ?? []);
        if ($errors->contains('code', 'chart_name_unavailable')) {
            $listResp = Http::withToken($accessToken)
                ->get("https://api.mercadolibre.com/users/{$sellerId}/catalog/charts", [
                    'site_id'   => 'MLB',
                    'domain_id' => $domainId,
                ]);

            Log::info('ML: listando charts do vendedor', [
                'status' => $listResp->status(),
                'body'   => $listResp->json(),
            ]);

            $charts = $listResp->json()['charts'] ?? ($listResp->json() ?? []);
            $firstChart = is_array($charts) && isset($charts[0]) ? $charts[0] : null;

            if ($firstChart && !empty($firstChart['id'])) {
                // Busca o chart completo com as rows
                $chartResp = Http::withToken($accessToken)
                    ->get("https://api.mercadolibre.com/catalog/charts/{$firstChart['id']}");

                Log::info('ML: buscando chart existente', [
                    'chart_id' => $firstChart['id'],
                    'status'   => $chartResp->status(),
                ]);

                if ($chartResp->successful() && !empty($chartResp->json()['id'])) {
                    $result = $this->parseChart($chartResp->json());
                    Cache::put($cacheKey, $result, now()->addDays(30));
                    return $result;
                }
            }
        }

        return null;
    }

    /** Monta os atributos de uma linha da guia com medidas padrão brasileiras. */
    private function buildChartRowAttributes(string $size): array
    {
        $m = self::BR_SIZE_CHART[strtoupper($size)] ?? null;

        $attrs = [
            ['id' => 'SIZE',           'values' => [['name' => $size]]],
            ['id' => 'FILTRABLE_SIZE', 'values' => [['name' => $size]]],
        ];

        if ($m) {
            $attrs[] = ['id' => 'HIP_CIRCUMFERENCE_FROM',   'values' => [['name' => $m['hip_from']   . ' cm']]];
            $attrs[] = ['id' => 'HIP_CIRCUMFERENCE_TO',     'values' => [['name' => $m['hip_to']     . ' cm']]];
            $attrs[] = ['id' => 'WAIST_CIRCUMFERENCE_FROM', 'values' => [['name' => $m['waist_from'] . ' cm']]];
            $attrs[] = ['id' => 'WAIST_CIRCUMFERENCE_TO',   'values' => [['name' => $m['waist_to']   . ' cm']]];
        }

        return $attrs;
    }

    /** Extrai chart_id e mapeamento tamanho → row_id do objeto chart retornado pelo ML. */
    private function parseChart(array $chart): array
    {
        $chartId = (string) $chart['id'];
        $rows    = [];

        foreach ($chart['rows'] ?? [] as $i => $row) {
            $rowId = (string) ($row['id'] ?? ($chartId . ':' . ($i + 1)));
            foreach ($row['attributes'] ?? [] as $attr) {
                if ($attr['id'] === 'SIZE') {
                    $size = $attr['values'][0]['name'] ?? null;
                    if ($size) {
                        $rows[$size] = $rowId;
                    }
                    break;
                }
            }
        }

        return ['chart_id' => $chartId, 'rows' => $rows];
    }

    private function resolvePrice(Product $product): float
    {
        if ($product->ml_price) {
            return (float) $product->ml_price;
        }
        if ($product->is_promotion && $product->promotion_price) {
            return (float) $product->promotion_price;
        }
        if ($product->retail_price) {
            return (float) $product->retail_price;
        }
        if ($product->wholesale_price) {
            return (float) $product->wholesale_price;
        }

        throw new RuntimeException("Produto '{$product->name}' não tem preço definido.");
    }

    private function buildImages(Product $product): array
    {
        $base = rtrim(str_replace('http://', 'https://', env('APP_API_URL', env('APP_URL'))), '/');

        return $product->images
            ->map(fn($img) => [
                'source' => $base . $img->url,
            ])
            ->take(10)
            ->values()
            ->toArray();
    }

    private function buildVariations(Product $product, float $basePrice, array $sizeGridRows = [], array $pictureIds = []): array
    {
        return $product->variants->map(function ($variant) use ($basePrice, $sizeGridRows, $pictureIds) {
            $combo = [];

            if ($variant->size) {
                $combo[] = ['id' => 'SIZE', 'value_name' => $variant->size];
            }

            if ($variant->color) {
                $combo[] = ['id' => 'COLOR', 'value_name' => mb_convert_case($variant->color, MB_CASE_TITLE, 'UTF-8')];
            }

            $variation = [
                'attribute_combinations' => $combo,
                'price'                  => $basePrice,
                'available_quantity'     => max(0, $variant->stock),
                'seller_custom_field'    => $variant->sku,
            ];

            if (!empty($pictureIds)) {
                $variation['picture_ids'] = $pictureIds;
            }

            if (!empty($sizeGridRows) && $variant->size && isset($sizeGridRows[$variant->size])) {
                $variation['attributes'] = [
                    ['id' => 'SIZE_GRID_ROW_ID', 'value_name' => $sizeGridRows[$variant->size]],
                ];
            }

            return $variation;
        })->values()->toArray();
    }

    /** Retorna [category_id, domain_id] via domain_discovery do ML. */
    private function predictCategoryAndDomain(Product $product): array
    {
        try {
            $results = $this->ml->get('/sites/MLB/domain_discovery/search', [
                'q'     => $product->name,
                'limit' => 1,
            ]);
            if (!empty($results[0])) {
                // domainId sem prefixo de site: "MLB-PANTS" → "PANTS"
                $domainId = preg_replace('/^MLB-/', '', $results[0]['domain_id'] ?? 'CLOTHES');
                return [$results[0]['category_id'] ?? 'MLB1430', $domainId];
            }
        } catch (\Exception $e) {
            Log::warning('ML: falha ao predizer categoria', ['produto' => $product->name]);
        }

        return ['MLB1430', 'CLOTHES'];
    }

    private function syncDescription(string $mlItemId, string $html, ?\App\Models\Product $product = null): void
    {
        // 1. Decode entidades HTML (&nbsp; → espaço, &amp; → &, etc.)
        $plainText = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // 2. Substitui tags de bloco por quebra de linha antes de strip_tags
        $plainText = preg_replace('/<\/?(p|br|div|li|h[1-6])[^>]*>/i', "\n", $plainText);

        // 3. Remove todas as tags HTML restantes
        $plainText = strip_tags($plainText);

        // 4. Remove espaço não-quebrável (U+00A0) que vem do &nbsp;
        $plainText = preg_replace('/\xc2\xa0/u', ' ', $plainText);

        // 5. Remove emojis e símbolos especiais (✅ ✨ – — etc.) que o ML rejeita
        $plainText = preg_replace('/[\x{2000}-\x{206F}\x{2E00}-\x{2E7F}]/u', '', $plainText); // pontuação geral
        $plainText = preg_replace('/[\x{1F300}-\x{1FFFF}]/u', '', $plainText);                 // emojis
        $plainText = preg_replace('/[\x{2600}-\x{27BF}]/u', '', $plainText);                   // símbolos diversos (✅✨)
        $plainText = preg_replace('/[\x{2010}-\x{2015}]/u', '-', $plainText);                  // traços especiais → hífen normal
        $plainText = preg_replace('/[\x{2018}\x{2019}\x{201C}\x{201D}]/u', '"', $plainText);   // aspas especiais → aspas normais

        // 6. Remove caracteres de controle inválidos
        $plainText = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $plainText);

        // 7. Normaliza espaços e quebras de linha
        $plainText = preg_replace('/[ \t]+/', ' ', $plainText);
        $plainText = preg_replace('/\n{3,}/', "\n\n", $plainText);
        $plainText = preg_replace('/ \n/', "\n", $plainText);
        $plainText = preg_replace('/\n /', "\n", $plainText);
        $plainText = trim($plainText);

        // Adiciona tabela de medidas se o produto tiver
        if ($product) {
            $measureText = $this->buildMeasurementsText($product);
            if ($measureText) {
                $plainText = $plainText ? $plainText . "\n\n" . $measureText : $measureText;
            }
        }

        if (empty($plainText)) {
            return;
        }

        try {
            $this->ml->post("/items/{$mlItemId}/description", [
                'plain_text' => $plainText,
            ]);
        } catch (\Exception $e) {
            Log::warning('ML: falha ao sincronizar descricao', ['item' => $mlItemId]);
        }
    }

    private function buildMeasurementsText(\App\Models\Product $product): string
    {
        $measurements = $product->measurements ?? $product->measurements()->get();

        if ($measurements->isEmpty()) {
            return '';
        }

        // Descobre quais campos têm valor nessa coleção
        $fields = [
            'bust'      => 'Busto',
            'waist'     => 'Cintura',
            'hip'       => 'Quadril',
            'waistband' => 'Cós',
            'rise'      => 'Gancho',
            'inseam'    => 'Entreperna',
            'thigh'     => 'Coxa',
            'length'    => 'Comprimento',
            'shoulder'  => 'Ombro',
            'sleeve'    => 'Manga',
        ];

        $activeFields = array_filter($fields, fn($field) =>
            $measurements->whereNotNull($field)->isNotEmpty(), ARRAY_FILTER_USE_KEY
        );

        if (empty($activeFields)) {
            return '';
        }

        $lines   = [];
        $lines[] = 'TABELA DE MEDIDAS (em cm)';
        $lines[] = str_repeat('-', 40);

        $header = 'Tamanho';
        foreach ($activeFields as $label) {
            $header .= ' | ' . $label;
        }
        $lines[] = $header;

        foreach ($measurements as $m) {
            $row = $m->size ?? '-';
            foreach (array_keys($activeFields) as $field) {
                $row .= ' | ' . ($m->$field ?? '-');
            }
            $lines[] = $row;
        }

        return implode("\n", $lines);
    }
}
