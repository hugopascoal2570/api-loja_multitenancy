<?php

namespace App\Repositories;

use App\DTO\Banners\CreateBannerDTO;
use App\DTO\Banners\EditBannerDTO;
use App\Models\Banner;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

class BannerRepository
{
    public function __construct(protected Banner $banner) {}

    public function getPaginate(
        int $perPage = 15,
        int $page = 1,
        string $filter = '',
        ?bool $isActive = null,
        ?string $deviceType = null
    ): LengthAwarePaginator {
        $query = $this->banner
            ->withoutTrashed()
            ->when($filter !== '', fn($q) => $q->where('name', 'like', "%{$filter}%"))
            ->when(!is_null($isActive), fn($q) => $q->where('active', $isActive))
            ->when($deviceType !== null, fn($q) => $q->whereIn('device_type', ['all', $deviceType]));

        \Log::info('BannerRepository@getPaginate - SQL:', [
            'sql' => $query->toSql(),
            'bindings' => $query->getBindings(),
            'count' => $query->count(),
        ]);

        return $query->paginate($perPage, ['*'], 'page', $page);
    }
    
    public function createWithImage(CreateBannerDTO $dto, ?UploadedFile $file = null): Banner
    {
        if ($file) {
            $path = $this->storeBannerImage($file);
            $dto->image_url = $path;
            \Log::info('Banner - Imagem salva:', ['path' => $path]);
        }

        $data = [
            'name'        => $dto->name,
            'description' => $dto->description,
            'link'        => $dto->link,
            'is_featured' => $dto->is_featured,
            'position'    => $dto->position,
            'image_url'   => $dto->image_url,
            'start_date'  => $dto->start_date,
            'end_date'    => $dto->end_date,
            'device_type' => $dto->device_type,
        ];

        \Log::info('Banner - Dados para criar:', $data);

        $banner = Banner::create($data);

        \Log::info('Banner - Criado com sucesso:', ['id' => $banner->id, 'name' => $banner->name]);

        return $banner;
    }
 
    public function updateWithImage(string $id, CreateBannerDTO $dto, ?UploadedFile $file = null): Banner
    {
        $banner = Banner::findOrFail($id);

        if ($file) {
            $path = $file->store('banners', 'public');
            $dto->image_url = $path;
        }

        $banner->update([
            'name'        => $dto->name,
            'description' => $dto->description,
            'link'        => $dto->link,
            'is_featured' => $dto->is_featured,
            'position'    => $dto->position,
            'image_url'   => $dto->image_url ?: $banner->image_url,
            'start_date'  => $dto->start_date,
            'end_date'    => $dto->end_date,
            'device_type' => $dto->device_type,
        ]);

        return $banner;
    }

    public function updateBanner(string $id, array $data, ?UploadedFile $file = null): Banner
    {
        $banner = Banner::findOrFail($id);

        if ($file) {
            // Remove imagem antiga se existir
            if ($banner->image_url) {
                $this->deleteImage($banner->image_url);
            }
            $data['image_url'] = $this->storeBannerImage($file);
        }

        // Converte datas se necessário
        if (isset($data['start_date']) && is_string($data['start_date'])) {
            $data['start_date'] = new \DateTime($data['start_date']);
        }
        if (isset($data['end_date']) && is_string($data['end_date'])) {
            $data['end_date'] = new \DateTime($data['end_date']);
        }

        \Log::info('BannerRepository@updateBanner - dados para atualizar:', $data);

        $banner->update($data);

        return $banner->fresh();
    }

    public function delete(string $id): bool
    {
        $banner = $this->findById($id);
        if (! $banner) {
            return false;
        }

        $this->deleteImage($banner->image_url);
        return (bool) $banner->delete();
    }

    public function findById(string $id): ?Banner
    {
        return $this->banner->find($id);
    }

    protected function storeBannerImage(UploadedFile $file): string
    {
        return $file->store('banners', 'public');
    }

    protected function storeImage(UploadedFile $file): string
    {
        $name = uniqid('banner_') .'.'. $file->getClientOriginalExtension();
        $path = $file->storeAs('banners', $name, 'public');
        return Storage::url($path);
    }

    protected function deleteImage(string $url): void
    {
        $relative = ltrim(parse_url($url, PHP_URL_PATH), '/storage/');
        Storage::disk('public')->delete($relative);
    }

    public function getActiveFor(string $device = 'all')
{
    return $this->banner
        ->where('active', true)
        ->where(function ($q) use ($device) {
            $q->where('device_type', 'all')->orWhere('device_type', $device);
        })
        ->get();
}
}