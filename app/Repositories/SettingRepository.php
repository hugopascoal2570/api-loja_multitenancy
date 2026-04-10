<?php

namespace App\Repositories;

use App\DTO\Settings\CreateSettingDTO;
use App\DTO\Settings\UpdateSettingDTO;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class SettingRepository
{
    public function __construct(protected Setting $model) {}

    public function all(): Collection
    {
        return $this->model->all();
    }

    public function findByKey(string $key): ?Setting
    {
        return $this->model->where('key', $key)->first();
    }

    public function createWithOptionalFile(CreateSettingDTO $dto, ?UploadedFile $file = null): Setting
    {
        if ($dto->type === 'image' && $file) {
            $filename = uniqid($dto->key . '_') . '.' . $file->extension();
            $path = $file->storeAs('settings', $filename, 'public');
            $dto->value = Storage::url($path);
        }

        return $this->model->create((array) $dto);
    }

    public function updateWithOptionalFile(UpdateSettingDTO $dto, ?UploadedFile $file = null): Setting
    {
        $setting = $this->findByKey($dto->key);

        if (! $setting) {
            throw new \Exception("Setting not found");
        }

        if ($dto->type === 'image' && $file) {
            $filename = uniqid($dto->key . '_') . '.' . $file->extension();
            $path = $file->storeAs('settings', $filename, 'public');
            $dto->value = Storage::url($path);
        }

        $setting->update((array) $dto);

        return $setting;
    }

    public function deleteByKey(string $key): bool
    {
        $setting = $this->findByKey($key);

        if (! $setting) {
            return false;
        }

        if ($setting->type === 'image' && $setting->value) {
            $relative = ltrim(parse_url($setting->value, PHP_URL_PATH), '/storage/');
            Storage::disk('public')->delete($relative);
        }

        return $setting->delete();
    }
}
