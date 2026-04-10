<?php

namespace App\Services;

use App\Models\ProductVariant;
use Picqer\Barcode\BarcodeGeneratorSVG;
use Picqer\Barcode\BarcodeGeneratorPNG;

class BarcodeService
{
    // Prefixo GS1 Brasil (789)
    private const GS1_PREFIX = '789';

    /**
     * Gera um EAN-13 único que não existe na tabela product_variants.
     */
    public function generateUniqueBarcode(): string
    {
        do {
            $barcode = $this->generateEan13();
        } while (ProductVariant::where('barcode', $barcode)->exists());

        return $barcode;
    }

    /**
     * Gera um EAN-13 aleatório com prefixo GS1 Brasil (789).
     * Formato: 789 + 9 dígitos aleatórios + 1 dígito verificador = 13 dígitos
     */
    public function generateEan13(): string
    {
        $digits = self::GS1_PREFIX . str_pad(random_int(0, 999999999), 9, '0', STR_PAD_LEFT);
        return $digits . $this->ean13CheckDigit($digits);
    }

    /**
     * Calcula o dígito verificador EAN-13.
     */
    public function ean13CheckDigit(string $digits12): string
    {
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int) $digits12[$i] * ($i % 2 === 0 ? 1 : 3);
        }
        return (string) ((10 - ($sum % 10)) % 10);
    }

    /**
     * Retorna o SVG do código de barras.
     */
    public function toSvg(string $barcode): string
    {
        $generator = new BarcodeGeneratorSVG();
        return $generator->getBarcode($barcode, BarcodeGeneratorSVG::TYPE_EAN_13);
    }

    /**
     * Retorna o PNG do código de barras em base64.
     */
    public function toPngBase64(string $barcode): string
    {
        $generator = new BarcodeGeneratorPNG();
        $png = $generator->getBarcode($barcode, BarcodeGeneratorPNG::TYPE_EAN_13);
        return base64_encode($png);
    }
}
