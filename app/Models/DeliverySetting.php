<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliverySetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'is_delivery_enabled',
        'delivery_fee',
        'minimum_order_value',
        'minimum_order_message',
        'description',
        'cutoff_day',
        'cutoff_time',
        'start_day',
        'next_delivery_message',
        'is_pickup_enabled',
        'pickup_address',
        'pickup_instructions',
        'is_dynamic_shipping_enabled',
        'store_notice',
        'is_store_open',
        'origin_zip_code',
        'default_weight',
        'default_width',
        'default_height',
        'default_length',
    ];

    protected $casts = [
        'is_delivery_enabled' => 'boolean',
        'is_pickup_enabled' => 'boolean',
        'is_dynamic_shipping_enabled' => 'boolean',
        'is_store_open' => 'boolean',
        'delivery_fee' => 'decimal:2',
        'minimum_order_value' => 'decimal:2',
        'cutoff_time' => 'datetime:H:i:s',
        'default_weight' => 'decimal:3',
        'default_width' => 'decimal:1',
        'default_height' => 'decimal:1',
        'default_length' => 'decimal:1',
    ];

    /**
     * Obtém a configuração atual de entrega (sempre há apenas 1 registro)
     */
    public static function current(): ?self
    {
        return self::first();
    }

    /**
     * Cria ou atualiza a configuração de entrega
     */
    public static function updateOrCreateSettings(array $data): self
    {
        $setting = self::first();

        if ($setting) {
            // Atualizar campo por campo para garantir
            $setting->is_delivery_enabled = $data['is_delivery_enabled'];
            $setting->delivery_fee = $data['delivery_fee'];
            $setting->minimum_order_value = $data['minimum_order_value'] ?? $setting->minimum_order_value ?? 0;
            $setting->minimum_order_message = $data['minimum_order_message'] ?? $setting->minimum_order_message;
            $setting->description = $data['description'] ?? null;
            $setting->cutoff_day = $data['cutoff_day'] ?? $setting->cutoff_day;
            $setting->cutoff_time = $data['cutoff_time'] ?? $setting->cutoff_time;
            $setting->start_day = $data['start_day'] ?? $setting->start_day ?? 'monday';
            $setting->next_delivery_message = $data['next_delivery_message'] ?? $setting->next_delivery_message;
            $setting->is_pickup_enabled = $data['is_pickup_enabled'] ?? $setting->is_pickup_enabled ?? false;
            $setting->pickup_address = $data['pickup_address'] ?? $setting->pickup_address;
            $setting->pickup_instructions = $data['pickup_instructions'] ?? $setting->pickup_instructions;
            $setting->is_dynamic_shipping_enabled = $data['is_dynamic_shipping_enabled'] ?? $setting->is_dynamic_shipping_enabled ?? false;
            $setting->store_notice = array_key_exists('store_notice', $data) ? $data['store_notice'] : $setting->store_notice;
            $setting->is_store_open = $data['is_store_open'] ?? $setting->is_store_open ?? true;
            $setting->origin_zip_code = $data['origin_zip_code'] ?? $setting->origin_zip_code;
            $setting->default_weight = $data['default_weight'] ?? $setting->default_weight ?? 0.300;
            $setting->default_width = $data['default_width'] ?? $setting->default_width ?? 20.0;
            $setting->default_height = $data['default_height'] ?? $setting->default_height ?? 10.0;
            $setting->default_length = $data['default_length'] ?? $setting->default_length ?? 30.0;
            $setting->save();

            return $setting->fresh();
        }

        return self::create($data);
    }

    /**
     * Verifica se o pedido está dentro do prazo de entrega
     * Agora considera o dia de início (start_day) e o dia limite (cutoff_day)
     */
    public function isWithinCutoff(): bool
    {
        $now = now();

        // Mapear dias da semana
        $daysMap = [
            'sunday' => 0,
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6,
        ];

        $startDayNumber = $daysMap[strtolower($this->start_day ?? 'monday')] ?? 1;
        $cutoffDayNumber = $daysMap[strtolower($this->cutoff_day)] ?? 5;
        $currentDayNumber = $now->dayOfWeek;

        // CASO 1: start_day <= cutoff_day (Ex: Segunda a Sexta)
        if ($startDayNumber <= $cutoffDayNumber) {
            // Está antes do início da semana
            if ($currentDayNumber < $startDayNumber) {
                return false; // Prazo ainda não começou
            }

            // Está depois do cutoff
            if ($currentDayNumber > $cutoffDayNumber) {
                return false; // Prazo encerrado
            }

            // É o dia de cutoff, verificar horário
            if ($currentDayNumber === $cutoffDayNumber) {
                $cutoffDateTime = $now->copy()->setTimeFromTimeString($this->cutoff_time);
                return $now->lessThanOrEqualTo($cutoffDateTime);
            }

            // Está entre start_day e cutoff_day
            return true;
        }

        // CASO 2: cutoff_day < start_day (Ex: Sexta a Segunda - atravessa final de semana)
        // Neste caso, o prazo está ABERTO se estiver >= start_day OU <= cutoff_day
        if ($currentDayNumber >= $startDayNumber || $currentDayNumber < $cutoffDayNumber) {
            // Está no período válido, mas precisa verificar se é o cutoff_day
            if ($currentDayNumber === $cutoffDayNumber) {
                $cutoffDateTime = $now->copy()->setTimeFromTimeString($this->cutoff_time);
                return $now->lessThanOrEqualTo($cutoffDateTime);
            }
            return true;
        }

        // Está no período fechado (entre cutoff e start)
        return false;
    }

    /**
     * Obtém a data da próxima entrega
     */
    public function getNextDeliveryDate(): string
    {
        $now = now();

        $daysMap = [
            'sunday' => 0,
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6,
        ];

        $startDayNumber = $daysMap[strtolower($this->start_day ?? 'monday')] ?? 1;

        // Calcular próxima ocorrência do dia de início
        $nextStart = $now->copy()->next($startDayNumber);

        return $nextStart->format('Y-m-d');
    }

    /**
     * Obtém nome do dia em português
     */
    public function getCutoffDayNameAttribute(): string
    {
        $daysTranslation = [
            'sunday' => 'Domingo',
            'monday' => 'Segunda-feira',
            'tuesday' => 'Terça-feira',
            'wednesday' => 'Quarta-feira',
            'thursday' => 'Quinta-feira',
            'friday' => 'Sexta-feira',
            'saturday' => 'Sábado',
        ];

        return $daysTranslation[strtolower($this->cutoff_day)] ?? 'Sexta-feira';
    }

    /**
     * Obtém nome do dia de início em português
     */
    public function getStartDayNameAttribute(): string
    {
        $daysTranslation = [
            'sunday' => 'Domingo',
            'monday' => 'Segunda-feira',
            'tuesday' => 'Terça-feira',
            'wednesday' => 'Quarta-feira',
            'thursday' => 'Quinta-feira',
            'friday' => 'Sexta-feira',
            'saturday' => 'Sábado',
        ];

        return $daysTranslation[strtolower($this->start_day ?? 'monday')] ?? 'Segunda-feira';
    }
}
