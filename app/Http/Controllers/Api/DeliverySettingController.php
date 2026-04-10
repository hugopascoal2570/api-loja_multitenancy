<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\DeliverySettingRequest;
use App\Repositories\DeliverySettingRepository;
use Illuminate\Http\JsonResponse;

class DeliverySettingController extends Controller
{
    public function __construct(
        private DeliverySettingRepository $repository
    ) {}

    /**
     * Obtém a configuração atual de entrega (rota pública)
     */
    public function show(): JsonResponse
    {
        $setting = $this->repository->getCurrent();

        if (!$setting) {
            return response()->json([
                'is_delivery_enabled' => false,
                'delivery_fee' => 0,
                'description' => null,
                'cutoff_day' => null,
                'cutoff_time' => null,
                'start_day' => null,
                'start_day_name' => null,
                'next_delivery_message' => null,
                'minimum_order_value' => 0,
                'minimum_order_message' => null,
                'is_pickup_enabled' => false,
                'pickup_address' => null,
                'pickup_instructions' => null,
                'is_dynamic_shipping_enabled' => false,
                'origin_zip_code' => null,
                'store_notice' => null,
                'is_store_open' => true,
            ]);
        }

        return response()->json([
            'is_delivery_enabled' => $setting->is_delivery_enabled,
            'delivery_fee' => $setting->delivery_fee,
            'description' => $setting->description,
            'cutoff_day' => $setting->cutoff_day,
            'cutoff_day_name' => $setting->cutoff_day_name,
            'cutoff_time' => $setting->cutoff_time ? date('H:i', strtotime($setting->cutoff_time)) : null,
            'start_day' => $setting->start_day,
            'start_day_name' => $setting->start_day_name,
            'next_delivery_message' => $setting->next_delivery_message,
            'minimum_order_value' => (float) $setting->minimum_order_value,
            'minimum_order_message' => $setting->minimum_order_message,
            'is_pickup_enabled' => $setting->is_pickup_enabled,
            'pickup_address' => $setting->pickup_address,
            'pickup_instructions' => $setting->pickup_instructions,
            'is_dynamic_shipping_enabled' => $setting->is_dynamic_shipping_enabled,
            'origin_zip_code' => $setting->origin_zip_code,
            'default_weight' => (float) $setting->default_weight,
            'default_width' => (float) $setting->default_width,
            'default_height' => (float) $setting->default_height,
            'default_length' => (float) $setting->default_length,
            'store_notice' => $setting->store_notice,
            'is_store_open' => $setting->is_store_open,
        ]);
    }

    /**
     * Verifica se está dentro do prazo de entrega (rota pública)
     */
    public function checkCutoff(): JsonResponse
    {
        $status = $this->repository->checkCutoffStatus();

        return response()->json($status);
    }

    /**
     * Atualiza ou cria a configuração de entrega (rota admin)
     */
    public function update(DeliverySettingRequest $request): JsonResponse
    {
        \Log::info('DeliverySetting UPDATE - Request:', $request->validated());

        $setting = $this->repository->updateOrCreate($request->validated());

        \Log::info('DeliverySetting UPDATE - After Save:', [
            'id' => $setting->id,
            'is_delivery_enabled' => $setting->is_delivery_enabled,
            'delivery_fee' => $setting->delivery_fee,
            'description' => $setting->description,
        ]);

        return response()->json([
            'message' => 'Configuração de entrega atualizada com sucesso.',
            'data' => [
                'id' => $setting->id,
                'is_delivery_enabled' => $setting->is_delivery_enabled,
                'delivery_fee' => $setting->delivery_fee,
                'description' => $setting->description,
                'cutoff_day' => $setting->cutoff_day,
                'cutoff_time' => $setting->cutoff_time ? date('H:i', strtotime($setting->cutoff_time)) : null,
                'start_day' => $setting->start_day,
                'next_delivery_message' => $setting->next_delivery_message,
                'minimum_order_value' => (float) $setting->minimum_order_value,
                'minimum_order_message' => $setting->minimum_order_message,
                'is_pickup_enabled' => $setting->is_pickup_enabled,
                'pickup_address' => $setting->pickup_address,
                'pickup_instructions' => $setting->pickup_instructions,
                'is_dynamic_shipping_enabled' => $setting->is_dynamic_shipping_enabled,
                'origin_zip_code' => $setting->origin_zip_code,
                'default_weight' => (float) $setting->default_weight,
                'default_width' => (float) $setting->default_width,
                'default_height' => (float) $setting->default_height,
                'default_length' => (float) $setting->default_length,
                'store_notice' => $setting->store_notice,
                'is_store_open' => $setting->is_store_open,
            ],
        ]);
    }
}
