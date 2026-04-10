<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class ProductDetailThemeSeeder extends Seeder
{
    private const FONTS        = ['Inter', 'Roboto', 'Poppins', 'Montserrat', 'Lato', 'Raleway', 'Nunito', 'Playfair Display', 'Merriweather', 'Open Sans'];
    private const FONT_SIZES   = ['12px', '14px', '16px', '18px', '20px', '24px', '28px', '32px', '36px', '48px'];
    private const FONT_WEIGHTS = ['300', '400', '500', '600', '700', '800'];
    private const RADII        = ['0px', '4px', '8px', '12px', '16px', '24px', '9999px'];

    public function run(): void
    {
        $settings = [

            // ═══════════════════════════════════════════════════════════════
            // GRUPO 1 — GALERIA DE IMAGENS
            // ═══════════════════════════════════════════════════════════════
            ['key' => 'product_detail.gallery.bg_color',               'label' => 'Cor de fundo',               'value' => '#F9FAFB', 'type' => 'color',  'options' => null],
            ['key' => 'product_detail.gallery.border_radius',          'label' => 'Arredondamento',             'value' => '12px',    'type' => 'select', 'options' => self::RADII],
            ['key' => 'product_detail.gallery.thumbnail_border_color', 'label' => 'Borda das miniaturas',       'value' => '#E5E7EB', 'type' => 'color',  'options' => null],
            ['key' => 'product_detail.gallery.thumbnail_active_color', 'label' => 'Borda da miniatura ativa',  'value' => '#E91E8C', 'type' => 'color',  'options' => null],

            // ═══════════════════════════════════════════════════════════════
            // GRUPO 2 — INFORMAÇÕES DO PRODUTO
            // ═══════════════════════════════════════════════════════════════
            ['key' => 'product_detail.info.name_font_family',          'label' => 'Fonte do nome',              'value' => 'Inter',   'type' => 'select', 'options' => self::FONTS],
            ['key' => 'product_detail.info.name_font_size',            'label' => 'Tamanho do nome',            'value' => '28px',    'type' => 'select', 'options' => self::FONT_SIZES],
            ['key' => 'product_detail.info.name_font_weight',          'label' => 'Peso do nome',               'value' => '700',     'type' => 'select', 'options' => self::FONT_WEIGHTS],
            ['key' => 'product_detail.info.name_color',                'label' => 'Cor do nome',                'value' => '#1A1A1A', 'type' => 'color',  'options' => null],
            ['key' => 'product_detail.info.category_color',            'label' => 'Cor da categoria',           'value' => '#6B7280', 'type' => 'color',  'options' => null],
            ['key' => 'product_detail.info.price_color',               'label' => 'Cor do preço',               'value' => '#E91E8C', 'type' => 'color',  'options' => null],
            ['key' => 'product_detail.info.price_font_size',           'label' => 'Tamanho do preço',           'value' => '28px',    'type' => 'select', 'options' => self::FONT_SIZES],
            ['key' => 'product_detail.info.promotional_price_color',   'label' => 'Cor do preço promocional',   'value' => '#E91E8C', 'type' => 'color',  'options' => null],
            ['key' => 'product_detail.info.original_price_color',      'label' => 'Cor do preço original',      'value' => '#9CA3AF', 'type' => 'color',  'options' => null],
            ['key' => 'product_detail.info.description_font_family',   'label' => 'Fonte da descrição',         'value' => 'Inter',   'type' => 'select', 'options' => self::FONTS],
            ['key' => 'product_detail.info.description_color',         'label' => 'Cor da descrição',           'value' => '#374151', 'type' => 'color',  'options' => null],

            // ═══════════════════════════════════════════════════════════════
            // GRUPO 3 — SELETOR DE VARIANTES (tamanho / cor)
            // ═══════════════════════════════════════════════════════════════
            ['key' => 'product_detail.variants.size_label',            'label' => 'Rótulo tamanho',             'value' => 'Tamanho', 'type' => 'text',   'options' => null],
            ['key' => 'product_detail.variants.color_label',           'label' => 'Rótulo cor',                 'value' => 'Cor',     'type' => 'text',   'options' => null],
            ['key' => 'product_detail.variants.size_bg_color',         'label' => 'Fundo do tamanho',           'value' => '#F3F4F6', 'type' => 'color',  'options' => null],
            ['key' => 'product_detail.variants.size_text_color',       'label' => 'Texto do tamanho',           'value' => '#1A1A1A', 'type' => 'color',  'options' => null],
            ['key' => 'product_detail.variants.size_active_bg',        'label' => 'Fundo do tamanho ativo',     'value' => '#E91E8C', 'type' => 'color',  'options' => null],
            ['key' => 'product_detail.variants.size_active_text',      'label' => 'Texto do tamanho ativo',     'value' => '#FFFFFF', 'type' => 'color',  'options' => null],
            ['key' => 'product_detail.variants.border_radius',         'label' => 'Arredondamento',             'value' => '8px',     'type' => 'select', 'options' => self::RADII],
            ['key' => 'product_detail.variants.out_of_stock_color',    'label' => 'Cor sem estoque',            'value' => '#D1D5DB', 'type' => 'color',  'options' => null],

            // ═══════════════════════════════════════════════════════════════
            // GRUPO 4 — BOTÕES DE AÇÃO
            // ═══════════════════════════════════════════════════════════════
            ['key' => 'product_detail.actions.buy_button_text',        'label' => 'Texto — Comprar',            'value' => 'Comprar Agora',       'type' => 'text',   'options' => null],
            ['key' => 'product_detail.actions.buy_button_bg_color',    'label' => 'Cor de fundo — Comprar',     'value' => '#E91E8C',             'type' => 'color',  'options' => null],
            ['key' => 'product_detail.actions.buy_button_text_color',  'label' => 'Cor do texto — Comprar',     'value' => '#FFFFFF',             'type' => 'color',  'options' => null],
            ['key' => 'product_detail.actions.cart_button_text',       'label' => 'Texto — Adicionar ao carrinho', 'value' => 'Adicionar ao Carrinho', 'type' => 'text', 'options' => null],
            ['key' => 'product_detail.actions.cart_button_bg_color',   'label' => 'Cor de fundo — Carrinho',    'value' => '#9C27B0',             'type' => 'color',  'options' => null],
            ['key' => 'product_detail.actions.cart_button_text_color', 'label' => 'Cor do texto — Carrinho',    'value' => '#FFFFFF',             'type' => 'color',  'options' => null],
            ['key' => 'product_detail.actions.border_radius',          'label' => 'Arredondamento dos botões',  'value' => '8px',                 'type' => 'select', 'options' => self::RADII],
            ['key' => 'product_detail.actions.font_weight',            'label' => 'Peso da fonte dos botões',   'value' => '600',                 'type' => 'select', 'options' => self::FONT_WEIGHTS],

            // ═══════════════════════════════════════════════════════════════
            // GRUPO 5 — TABELA DE MEDIDAS
            // ═══════════════════════════════════════════════════════════════
            ['key' => 'product_detail.measurements.title',             'label' => 'Título',                     'value' => 'Tabela de Medidas',   'type' => 'text',   'options' => null],
            ['key' => 'product_detail.measurements.title_color',       'label' => 'Cor do título',              'value' => '#1A1A1A',             'type' => 'color',  'options' => null],
            ['key' => 'product_detail.measurements.font_family',       'label' => 'Fonte',                      'value' => 'Inter',               'type' => 'select', 'options' => self::FONTS],
            ['key' => 'product_detail.measurements.header_bg_color',   'label' => 'Cor de fundo do cabeçalho', 'value' => '#E91E8C',             'type' => 'color',  'options' => null],
            ['key' => 'product_detail.measurements.header_text_color', 'label' => 'Cor do texto do cabeçalho', 'value' => '#FFFFFF',             'type' => 'color',  'options' => null],
            ['key' => 'product_detail.measurements.row_bg_color',      'label' => 'Cor das linhas',             'value' => '#FFFFFF',             'type' => 'color',  'options' => null],
            ['key' => 'product_detail.measurements.row_alt_bg_color',  'label' => 'Cor das linhas alternadas',  'value' => '#F9FAFB',             'type' => 'color',  'options' => null],
            ['key' => 'product_detail.measurements.border_color',      'label' => 'Cor da borda',               'value' => '#E5E7EB',             'type' => 'color',  'options' => null],

            // ═══════════════════════════════════════════════════════════════
            // GRUPO 6 — SEÇÃO DE KITS
            // ═══════════════════════════════════════════════════════════════
            ['key' => 'product_detail.kits.title',                     'label' => 'Título',                     'value' => 'Kits Disponíveis',    'type' => 'text',   'options' => null],
            ['key' => 'product_detail.kits.subtitle',                  'label' => 'Subtítulo',                  'value' => 'Monte seu kit e economize', 'type' => 'text', 'options' => null],
            ['key' => 'product_detail.kits.title_color',               'label' => 'Cor do título',              'value' => '#1A1A1A',             'type' => 'color',  'options' => null],
            ['key' => 'product_detail.kits.subtitle_color',            'label' => 'Cor do subtítulo',           'value' => '#6B7280',             'type' => 'color',  'options' => null],
            ['key' => 'product_detail.kits.card_bg_color',             'label' => 'Cor de fundo do card',       'value' => '#FFF9F0',             'type' => 'color',  'options' => null],
            ['key' => 'product_detail.kits.card_border_color',         'label' => 'Cor da borda do card',       'value' => '#E5E7EB',             'type' => 'color',  'options' => null],
            ['key' => 'product_detail.kits.price_color',               'label' => 'Cor do preço do kit',        'value' => '#E91E8C',             'type' => 'color',  'options' => null],
            ['key' => 'product_detail.kits.font_family',               'label' => 'Fonte',                      'value' => 'Inter',               'type' => 'select', 'options' => self::FONTS],
            ['key' => 'product_detail.kits.title_size',                'label' => 'Tamanho do título',          'value' => '24px',                'type' => 'select', 'options' => self::FONT_SIZES],
        ];

        foreach ($settings as $setting) {
            Setting::firstOrCreate(
                ['key' => $setting['key']],
                array_merge($setting, [
                    'group'         => 'product_detail',
                    'description'   => null,
                    'default_value' => $setting['value'],
                ])
            );
        }
    }
}
