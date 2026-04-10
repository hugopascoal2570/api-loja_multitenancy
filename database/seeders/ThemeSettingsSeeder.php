<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class ThemeSettingsSeeder extends Seeder
{
    private const FONTS = ['Inter', 'Roboto', 'Poppins', 'Montserrat', 'Lato', 'Raleway', 'Nunito', 'Playfair Display', 'Merriweather', 'Open Sans'];
    private const FONT_SIZES = ['12px', '14px', '16px', '18px', '20px', '24px', '28px', '32px', '36px', '48px'];
    private const FONT_WEIGHTS = ['300', '400', '500', '600', '700', '800'];
    private const BORDER_RADIUS = ['0px', '4px', '8px', '12px', '16px', '24px', '9999px'];

    public function run(): void
    {
        $settings = [

            // ═══════════════════════════════════════════════════════════════
            // GRUPO 1 — LOJA (identidade visual global)
            // ═══════════════════════════════════════════════════════════════
            ['key' => 'home.store.name',            'label' => 'Nome da loja',         'value' => 'CloChic',   'type' => 'text',   'options' => null, 'description' => 'Nome exibido no header e título do site'],
            ['key' => 'home.store.logo',             'label' => 'Logo da loja',         'value' => null,        'type' => 'image',  'options' => null, 'description' => 'URL da logo (deixe vazio para usar ícone padrão)'],
            ['key' => 'home.store.favicon',          'label' => 'Favicon',              'value' => null,        'type' => 'image',  'options' => null, 'description' => 'Ícone da aba do navegador'],
            ['key' => 'home.store.primary_color',    'label' => 'Cor primária',         'value' => '#E91E8C',   'type' => 'color',  'options' => null, 'description' => 'Cor principal (botões, destaques, links ativos)'],
            ['key' => 'home.store.secondary_color',  'label' => 'Cor secundária',       'value' => '#9C27B0',   'type' => 'color',  'options' => null, 'description' => 'Cor secundária (gradientes, badges)'],
            ['key' => 'home.store.accent_color',     'label' => 'Cor de destaque',      'value' => '#FF9800',   'type' => 'color',  'options' => null, 'description' => 'Cor de destaque (tags, novidades, promoções)'],
            ['key' => 'home.store.background_color', 'label' => 'Cor de fundo',         'value' => '#FFFFFF',   'type' => 'color',  'options' => null, 'description' => 'Cor de fundo geral da página'],
            ['key' => 'home.store.text_color',       'label' => 'Cor do texto',         'value' => '#1A1A1A',   'type' => 'color',  'options' => null, 'description' => 'Cor do texto principal'],
            ['key' => 'home.store.font_family',      'label' => 'Fonte principal',      'value' => 'Inter',     'type' => 'select', 'options' => self::FONTS, 'description' => 'Família de fonte usada no site'],
            ['key' => 'home.store.font_size',        'label' => 'Tamanho da fonte',     'value' => '16px',      'type' => 'select', 'options' => self::FONT_SIZES, 'description' => 'Tamanho base da fonte'],

            // ═══════════════════════════════════════════════════════════════
            // GRUPO 2 — BOTÕES
            // ═══════════════════════════════════════════════════════════════
            ['key' => 'home.button.bg_color',        'label' => 'Cor de fundo',         'value' => '#E91E8C',   'type' => 'color',  'options' => null, 'description' => 'Cor de fundo dos botões primários'],
            ['key' => 'home.button.text_color',      'label' => 'Cor do texto',         'value' => '#FFFFFF',   'type' => 'color',  'options' => null, 'description' => 'Cor do texto dos botões'],
            ['key' => 'home.button.hover_color',     'label' => 'Cor ao passar o mouse', 'value' => '#C2185B',  'type' => 'color',  'options' => null, 'description' => 'Cor do botão ao hover'],
            ['key' => 'home.button.border_radius',   'label' => 'Arredondamento',       'value' => '8px',       'type' => 'select', 'options' => self::BORDER_RADIUS, 'description' => 'Arredondamento das bordas dos botões'],
            ['key' => 'home.button.font_weight',     'label' => 'Peso da fonte',        'value' => '600',       'type' => 'select', 'options' => self::FONT_WEIGHTS, 'description' => 'Espessura do texto dos botões'],

            // ═══════════════════════════════════════════════════════════════
            // GRUPO 3 — LINKS
            // ═══════════════════════════════════════════════════════════════
            ['key' => 'home.link.color',             'label' => 'Cor dos links',        'value' => '#E91E8C',   'type' => 'color',  'options' => null, 'description' => 'Cor padrão dos links'],
            ['key' => 'home.link.hover_color',       'label' => 'Cor ao passar o mouse', 'value' => '#9C27B0',  'type' => 'color',  'options' => null, 'description' => 'Cor dos links ao hover'],

            // ═══════════════════════════════════════════════════════════════
            // GRUPO 4 — CARDS DE PRODUTO
            // ═══════════════════════════════════════════════════════════════
            ['key' => 'home.card.bg_color',          'label' => 'Cor de fundo',         'value' => '#FFFFFF',   'type' => 'color',  'options' => null, 'description' => 'Cor de fundo dos cards de produto'],
            ['key' => 'home.card.border_color',      'label' => 'Cor da borda',         'value' => '#E5E7EB',   'type' => 'color',  'options' => null, 'description' => 'Cor da borda dos cards'],
            ['key' => 'home.card.border_radius',     'label' => 'Arredondamento',       'value' => '12px',      'type' => 'select', 'options' => self::BORDER_RADIUS, 'description' => 'Arredondamento dos cards'],
            ['key' => 'home.card.title_color',       'label' => 'Cor do nome',          'value' => '#1A1A1A',   'type' => 'color',  'options' => null, 'description' => 'Cor do nome do produto no card'],
            ['key' => 'home.card.price_color',       'label' => 'Cor do preço',         'value' => '#E91E8C',   'type' => 'color',  'options' => null, 'description' => 'Cor do preço no card'],
            ['key' => 'home.card.font_family',       'label' => 'Fonte',                'value' => 'Inter',     'type' => 'select', 'options' => self::FONTS, 'description' => 'Fonte usada nos cards'],

            // ═══════════════════════════════════════════════════════════════
            // GRUPO 5 — HEADER / NAVBAR
            // ═══════════════════════════════════════════════════════════════
            ['key' => 'home.header.bg_color',        'label' => 'Cor de fundo',         'value' => '#FFFFFF',   'type' => 'color',  'options' => null, 'description' => 'Cor de fundo do cabeçalho'],
            ['key' => 'home.header.text_color',      'label' => 'Cor do texto',         'value' => '#1A1A1A',   'type' => 'color',  'options' => null, 'description' => 'Cor dos itens do menu'],
            ['key' => 'home.header.font_family',     'label' => 'Fonte',                'value' => 'Inter',     'type' => 'select', 'options' => self::FONTS, 'description' => 'Fonte do menu'],
            ['key' => 'home.header.font_size',       'label' => 'Tamanho da fonte',     'value' => '16px',      'type' => 'select', 'options' => self::FONT_SIZES, 'description' => 'Tamanho da fonte do menu'],

            // ═══════════════════════════════════════════════════════════════
            // GRUPO 6 — SEÇÕES DA HOME
            // ═══════════════════════════════════════════════════════════════

            // Destaques
            ['key' => 'home.section.highlights.title',       'label' => 'Título',           'value' => 'Produtos em Destaque',                           'type' => 'text',   'options' => null],
            ['key' => 'home.section.highlights.subtitle',    'label' => 'Subtítulo',        'value' => 'Descubra nossa seleção especial de peças exclusivas', 'type' => 'text', 'options' => null],
            ['key' => 'home.section.highlights.title_color', 'label' => 'Cor do título',    'value' => '#1A1A1A',  'type' => 'color',  'options' => null],
            ['key' => 'home.section.highlights.subtitle_color', 'label' => 'Cor do subtítulo', 'value' => '#6B7280', 'type' => 'color', 'options' => null],
            ['key' => 'home.section.highlights.font_family', 'label' => 'Fonte',            'value' => 'Inter',    'type' => 'select', 'options' => self::FONTS],
            ['key' => 'home.section.highlights.title_size',  'label' => 'Tamanho do título', 'value' => '28px',    'type' => 'select', 'options' => self::FONT_SIZES],
            ['key' => 'home.section.highlights.bg_color',    'label' => 'Cor de fundo',     'value' => '#FFFFFF',  'type' => 'color',  'options' => null],

            // Todos os produtos
            ['key' => 'home.section.products.title',         'label' => 'Título',           'value' => 'Nossos Produtos',                                 'type' => 'text',   'options' => null],
            ['key' => 'home.section.products.subtitle',      'label' => 'Subtítulo',        'value' => 'Confira nossa coleção completa de peças exclusivas', 'type' => 'text', 'options' => null],
            ['key' => 'home.section.products.title_color',   'label' => 'Cor do título',    'value' => '#1A1A1A',  'type' => 'color',  'options' => null],
            ['key' => 'home.section.products.subtitle_color','label' => 'Cor do subtítulo', 'value' => '#6B7280',  'type' => 'color',  'options' => null],
            ['key' => 'home.section.products.font_family',   'label' => 'Fonte',            'value' => 'Inter',    'type' => 'select', 'options' => self::FONTS],
            ['key' => 'home.section.products.title_size',    'label' => 'Tamanho do título', 'value' => '28px',    'type' => 'select', 'options' => self::FONT_SIZES],
            ['key' => 'home.section.products.bg_color',      'label' => 'Cor de fundo',     'value' => '#FFFFFF',  'type' => 'color',  'options' => null],

            // Novidades
            ['key' => 'home.section.new.title',              'label' => 'Título',           'value' => 'Recém Chegados',                                   'type' => 'text',   'options' => null],
            ['key' => 'home.section.new.subtitle',           'label' => 'Subtítulo',        'value' => 'Seja a primeira a usar as últimas tendências da moda', 'type' => 'text', 'options' => null],
            ['key' => 'home.section.new.title_color',        'label' => 'Cor do título',    'value' => '#1A1A1A',  'type' => 'color',  'options' => null],
            ['key' => 'home.section.new.subtitle_color',     'label' => 'Cor do subtítulo', 'value' => '#6B7280',  'type' => 'color',  'options' => null],
            ['key' => 'home.section.new.font_family',        'label' => 'Fonte',            'value' => 'Inter',    'type' => 'select', 'options' => self::FONTS],
            ['key' => 'home.section.new.title_size',         'label' => 'Tamanho do título', 'value' => '28px',    'type' => 'select', 'options' => self::FONT_SIZES],
            ['key' => 'home.section.new.bg_color',           'label' => 'Cor de fundo',     'value' => '#FFFFFF',  'type' => 'color',  'options' => null],

            // Promoções
            ['key' => 'home.section.promotions.title',       'label' => 'Título',           'value' => 'Ofertas Imperdíveis',                              'type' => 'text',   'options' => null],
            ['key' => 'home.section.promotions.subtitle',    'label' => 'Subtítulo',        'value' => 'Aproveite nossos produtos com preços especiais',   'type' => 'text',   'options' => null],
            ['key' => 'home.section.promotions.title_color', 'label' => 'Cor do título',    'value' => '#1A1A1A',  'type' => 'color',  'options' => null],
            ['key' => 'home.section.promotions.subtitle_color','label' => 'Cor do subtítulo','value' => '#6B7280', 'type' => 'color',  'options' => null],
            ['key' => 'home.section.promotions.font_family', 'label' => 'Fonte',            'value' => 'Inter',    'type' => 'select', 'options' => self::FONTS],
            ['key' => 'home.section.promotions.title_size',  'label' => 'Tamanho do título', 'value' => '28px',    'type' => 'select', 'options' => self::FONT_SIZES],
            ['key' => 'home.section.promotions.bg_color',    'label' => 'Cor de fundo',     'value' => '#FFFFFF',  'type' => 'color',  'options' => null],

            // Nova Coleção
            ['key' => 'home.section.collection.title',       'label' => 'Título',           'value' => 'Coleção Exclusiva',                               'type' => 'text',   'options' => null],
            ['key' => 'home.section.collection.subtitle',    'label' => 'Subtítulo',        'value' => 'Peças únicas e especiais para você',              'type' => 'text',   'options' => null],
            ['key' => 'home.section.collection.title_color', 'label' => 'Cor do título',    'value' => '#1A1A1A',  'type' => 'color',  'options' => null],
            ['key' => 'home.section.collection.subtitle_color','label' => 'Cor do subtítulo','value' => '#6B7280', 'type' => 'color',  'options' => null],
            ['key' => 'home.section.collection.font_family', 'label' => 'Fonte',            'value' => 'Inter',    'type' => 'select', 'options' => self::FONTS],
            ['key' => 'home.section.collection.title_size',  'label' => 'Tamanho do título', 'value' => '28px',    'type' => 'select', 'options' => self::FONT_SIZES],
            ['key' => 'home.section.collection.bg_color',    'label' => 'Cor de fundo',     'value' => '#FFFFFF',  'type' => 'color',  'options' => null],

            // Kits
            ['key' => 'home.section.kits.title',             'label' => 'Título',           'value' => 'Kits em Destaque',                                'type' => 'text',   'options' => null],
            ['key' => 'home.section.kits.subtitle',          'label' => 'Subtítulo',        'value' => 'Combos exclusivos com preços especiais. Monte seu look completo!', 'type' => 'text', 'options' => null],
            ['key' => 'home.section.kits.title_color',       'label' => 'Cor do título',    'value' => '#1A1A1A',  'type' => 'color',  'options' => null],
            ['key' => 'home.section.kits.subtitle_color',    'label' => 'Cor do subtítulo', 'value' => '#6B7280',  'type' => 'color',  'options' => null],
            ['key' => 'home.section.kits.font_family',       'label' => 'Fonte',            'value' => 'Inter',    'type' => 'select', 'options' => self::FONTS],
            ['key' => 'home.section.kits.title_size',        'label' => 'Tamanho do título', 'value' => '28px',    'type' => 'select', 'options' => self::FONT_SIZES],
            ['key' => 'home.section.kits.bg_color',          'label' => 'Cor de fundo',     'value' => '#FFF9F0',  'type' => 'color',  'options' => null],

            // ═══════════════════════════════════════════════════════════════
            // GRUPO 7 — BENEFÍCIOS
            // ═══════════════════════════════════════════════════════════════
            ['key' => 'home.benefits.1.title',               'label' => 'Benefício 1 — Título',    'value' => 'Compra Segura',       'type' => 'text',  'options' => null],
            ['key' => 'home.benefits.1.subtitle',            'label' => 'Benefício 1 — Subtítulo', 'value' => 'SSL e criptografia',  'type' => 'text',  'options' => null],
            ['key' => 'home.benefits.1.icon_color',          'label' => 'Benefício 1 — Cor do ícone', 'value' => '#E91E8C',         'type' => 'color', 'options' => null],
            ['key' => 'home.benefits.2.title',               'label' => 'Benefício 2 — Título',    'value' => 'Parcelamento',        'type' => 'text',  'options' => null],
            ['key' => 'home.benefits.2.subtitle',            'label' => 'Benefício 2 — Subtítulo', 'value' => 'Em até 12x sem juros', 'type' => 'text', 'options' => null],
            ['key' => 'home.benefits.2.icon_color',          'label' => 'Benefício 2 — Cor do ícone', 'value' => '#E91E8C',         'type' => 'color', 'options' => null],

            // ═══════════════════════════════════════════════════════════════
            // GRUPO 8 — NEWSLETTER
            // ═══════════════════════════════════════════════════════════════
            ['key' => 'home.newsletter.title',               'label' => 'Título',           'value' => 'Fique por Dentro das Novidades',                  'type' => 'text',   'options' => null],
            ['key' => 'home.newsletter.subtitle',            'label' => 'Subtítulo',        'value' => 'Receba em primeira mão nossas promoções e lançamentos', 'type' => 'text', 'options' => null],
            ['key' => 'home.newsletter.button',              'label' => 'Texto do botão',   'value' => 'Inscrever',                                       'type' => 'text',   'options' => null],
            ['key' => 'home.newsletter.disclaimer',          'label' => 'Aviso',            'value' => 'Não enviamos spam. Você pode cancelar a qualquer momento.', 'type' => 'text', 'options' => null],
            ['key' => 'home.newsletter.bg_color_start',      'label' => 'Cor gradiente início', 'value' => '#E91E8C',                                     'type' => 'color',  'options' => null],
            ['key' => 'home.newsletter.bg_color_end',        'label' => 'Cor gradiente fim',    'value' => '#9C27B0',                                     'type' => 'color',  'options' => null],
            ['key' => 'home.newsletter.title_color',         'label' => 'Cor do título',    'value' => '#FFFFFF',  'type' => 'color',  'options' => null],
            ['key' => 'home.newsletter.button_bg_color',     'label' => 'Cor do botão',     'value' => '#FFFFFF',  'type' => 'color',  'options' => null],
            ['key' => 'home.newsletter.button_text_color',   'label' => 'Cor do texto do botão', 'value' => '#E91E8C', 'type' => 'color', 'options' => null],
            ['key' => 'home.newsletter.font_family',         'label' => 'Fonte',            'value' => 'Inter',    'type' => 'select', 'options' => self::FONTS],
        ];

        foreach ($settings as $setting) {
            Setting::firstOrCreate(
                ['key' => $setting['key']],
                array_merge($setting, [
                    'group'         => 'home',
                    'default_value' => $setting['value'],
                ])
            );
        }
    }
}
