<?php

namespace App\DTO\Product;

class ProductDTO
{
    public function __construct(
        public ?string $name,
        public ?string $reference,
        public ?string $description,
        public ?float $retail_price,
        public ?float $wholesale_price,
        public ?int $wholesale_min_qty,
        public ?string $category_id,
        public ?bool $is_highlighted,
        public ?bool $is_promotion,
        public ?float $promotion_price,
        public ?float $ml_price,
        public ?bool $is_new,
        public ?bool $is_new_collection,
        public ?array $variants,
        public ?array $images,
        public ?bool $active,
        public ?float $weight = null,
        public ?float $width = null,
        public ?float $height = null,
        public ?float $length = null,
    ) {}

    public static function fromRequest($request): self
    {
        return new self(
            name: $request->name ?? null,
            reference: $request->reference ?? null,
            description: $request->description ?? null,
            retail_price: $request->filled('retail_price') ? (float) $request->retail_price : null,
            wholesale_price: $request->filled('wholesale_price') ? (float) $request->wholesale_price : null,
            wholesale_min_qty: $request->wholesale_min_qty ?? null,
            category_id: $request->category_id ?? null,
            is_highlighted: $request->has('is_highlighted') ? filter_var($request->is_highlighted, FILTER_VALIDATE_BOOLEAN) : null,
            is_promotion: $request->has('is_promotion') ? filter_var($request->is_promotion, FILTER_VALIDATE_BOOLEAN) : null,
            promotion_price: $request->filled('promotion_price') ? (float) $request->promotion_price : null,
            ml_price: $request->filled('ml_price') ? (float) $request->ml_price : null,
            is_new: $request->has('is_new') ? filter_var($request->is_new, FILTER_VALIDATE_BOOLEAN) : null,
            is_new_collection: $request->has('is_new_collection') ? filter_var($request->is_new_collection, FILTER_VALIDATE_BOOLEAN) : null,
            active: $request->has('active') ? filter_var($request->active, FILTER_VALIDATE_BOOLEAN) : null,
            variants: $request->has('variants') ? collect($request->input('variants', []))->map(function ($variant) {
                return [
                    'sku' => $variant['sku'] ?? null,
                    'size' => $variant['size'] ?? null,
                    'color' => $variant['color'] ?? null,
                    'stock' => $variant['stock'] ?? 0,
                ];
            })->toArray() : null,
            weight: $request->filled('weight') ? (float) $request->weight : null,
            width: $request->filled('width') ? (float) $request->width : null,
            height: $request->filled('height') ? (float) $request->height : null,
            length: $request->filled('length') ? (float) $request->length : null,
            images: $request->has('images') ? collect($request->input("images", []))->map(function ($image, $index) use ($request) {
                $file = $request->file("images.{$index}.image") ?? null;

                return [
                    "id" => $image["id"] ?? null,
                    "url" => $image["url"] ?? null,
                    "image" => $file,
                    "is_main" => filter_var($image["is_main"] ?? false, FILTER_VALIDATE_BOOLEAN),
                    "variant_sku" => $image["variant_sku"] ?? null,
                ];
            })->toArray() : null,
        );
    }


}