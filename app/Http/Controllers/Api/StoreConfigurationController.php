<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreConfiguration\UpdateStoreConfigurationRequest;
use App\Http\Resources\StoreConfigurationResource;
use App\Models\StoreConfiguration;

class StoreConfigurationController extends Controller
{
    public function show(): StoreConfigurationResource
    {
        return new StoreConfigurationResource(StoreConfiguration::current());
    }

    public function update(UpdateStoreConfigurationRequest $request): StoreConfigurationResource
    {
        $config = StoreConfiguration::current();
        $config->update($request->validated());

        return new StoreConfigurationResource($config->fresh());
    }
}
