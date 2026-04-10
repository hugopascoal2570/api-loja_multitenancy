<?php

namespace App\Repositories;

use App\Models\DeliverySetting;

class DeliverySettingRepository
{
    /**
     * Obtém a configuração atual de entrega
     */
    public function getCurrent(): ?DeliverySetting
    {
        return DeliverySetting::current();
    }

    /**
     * Atualiza ou cria a configuração de entrega
     */
    public function updateOrCreate(array $data): DeliverySetting
    {
        return DeliverySetting::updateOrCreateSettings($data);
    }

    /**
     * Verifica se a entrega está habilitada
     */
    public function isDeliveryEnabled(): bool
    {
        $setting = $this->getCurrent();
        return $setting ? $setting->is_delivery_enabled : false;
    }

    /**
     * Obtém o valor da taxa de entrega
     */
    public function getDeliveryFee(): float
    {
        $setting = $this->getCurrent();
        return $setting && $setting->is_delivery_enabled ? (float) $setting->delivery_fee : 0;
    }

    /**
     * Obtém o valor mínimo do pedido
     */
    public function getMinimumOrderValue(): float
    {
        $setting = $this->getCurrent();
        return $setting ? (float) $setting->minimum_order_value : 0;
    }

    /**
     * Obtém a mensagem de valor mínimo
     */
    public function getMinimumOrderMessage(): ?string
    {
        $setting = $this->getCurrent();
        return $setting?->minimum_order_message;
    }

    /**
     * Verifica status do prazo de entrega
     */
    public function checkCutoffStatus(): array
    {
        $setting = $this->getCurrent();

        if (!$setting) {
            return [
                'can_deliver_this_week' => true,
                'message' => null,
                'cutoff_day' => null,
                'cutoff_time' => null,
                'next_delivery_date' => null,
            ];
        }

        $isWithinCutoff = $setting->isWithinCutoff();

        return [
            'can_deliver_this_week' => $isWithinCutoff,
            'message' => $isWithinCutoff ? null : $setting->next_delivery_message,
            'cutoff_day' => $setting->cutoff_day_name,
            'cutoff_time' => $setting->cutoff_time ? date('H:i', strtotime($setting->cutoff_time)) : null,
            'next_delivery_date' => !$isWithinCutoff ? $setting->getNextDeliveryDate() : null,
        ];
    }
}
