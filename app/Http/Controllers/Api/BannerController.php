<?php

namespace App\Http\Controllers\Api;

use App\DTO\Banners\CreateBannerDTO;
use App\DTO\Banners\EditBannerDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Banners\StoreBannerRequest;
use App\Http\Requests\Api\Banners\UpdateBannerRequest;
use App\Repositories\BannerRepository;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Resources\BannerResource;
use Illuminate\Support\Facades\Storage;
use DateTime;

class BannerController extends Controller
{
    public function __construct(private BannerRepository $bannerRepository) {}

    public function index(Request $request)
    {
        \Log::info('BannerController@index - params:', $request->all());

        // Verificar se is_active foi enviado
        $isActive = null;
        if ($request->has('is_active')) {
            $isActive = $request->boolean('is_active');
        }

        $banners = $this->bannerRepository->getPaginate(
            perPage:     $request->integer('per_page', 15),
            page:        $request->integer('page', 1),
            filter:      $request->get('filter', ''),
            isActive:    $isActive,
            deviceType:  $request->get('device_type', null),
        );

        \Log::info('BannerController@index - total encontrado:', ['total' => $banners->total()]);

        return BannerResource::collection($banners);
    }

    /**
     * Lista banners ativos (rota pública)
     * GET /api/banners/active?device=mobile
     */
    public function active(Request $request)
    {
        $device = $request->get('device', 'all'); // all, desktop, mobile
        $banners = $this->bannerRepository->getActiveFor($device);

        return BannerResource::collection($banners);
    }

    public function store(StoreBannerRequest $request)
    {
        $payload = $request->validated();

        // Log para debug
        \Log::info('Banner store - payload recebido:', $payload);
        \Log::info('Banner store - arquivo:', ['image' => $request->hasFile('image')]);

        // Converter is_featured para boolean
        $isFeatured = false;
        if (isset($payload['is_featured'])) {
            $isFeatured = filter_var($payload['is_featured'], FILTER_VALIDATE_BOOLEAN);
        }

        $dto = new CreateBannerDTO(
            name:        $payload['name'],
            description: $payload['description'] ?? null,
            link:        $payload['link'] ?? null,
            is_featured: $isFeatured,
            position:    (int) ($payload['position'] ?? 1),
            start_date:  isset($payload['start_date']) ? new DateTime($payload['start_date']) : null,
            end_date:    isset($payload['end_date']) ? new DateTime($payload['end_date']) : null,
            image_url:   $payload['image_url'] ?? '',
            device_type: $payload['device_type'] ?? 'all'
        );

        $banner = $this->bannerRepository->createWithImage(
            $dto,
            $request->file('image')
        );

        \Log::info('Banner criado:', ['id' => $banner->id]);

        return new BannerResource($banner);
    }


    public function show(string $id)
    {
        if (! $banner = $this->bannerRepository->findById($id)) {
            return response()->json(['message' => 'banner not found'], Response::HTTP_NOT_FOUND);
        }

        return new BannerResource($banner);
    }

    public function update(UpdateBannerRequest $request, $id)
    {
        $payload = $request->validated();

        \Log::info('BannerController@update - payload:', $payload);

        $banner = $this->bannerRepository->updateBanner(
            $id,
            $payload,
            $request->file('image')
        );

        return new BannerResource($banner);
    }

    public function destroy(string $id)
    {
        if (! $this->bannerRepository->delete($id)) {
            return response()->json(['message' => 'banner not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json([], Response::HTTP_NO_CONTENT);
    }
}