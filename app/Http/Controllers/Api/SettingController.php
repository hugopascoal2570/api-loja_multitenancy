<?php

namespace App\Http\Controllers\Api;

use App\DTO\Settings\CreateSettingDTO;
use App\DTO\Settings\UpdateSettingDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Settings\StoreSettingRequest;
use App\Repositories\SettingRepository;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class SettingController extends Controller
{
    public function __construct(private SettingRepository $repository) {}

    public function index()
    {
        return response()->json($this->repository->all());
    }

    public function store(StoreSettingRequest $request)
    {
        $data = $request->safe()->except(['value']);
        $file = $request->file('value');
    
        $data['value'] = ($request->input('type') === 'image' && $file)
            ? null
            : $request->input('value');
    
        $dto = new CreateSettingDTO(...$data);
    
        $setting = $this->repository->createWithOptionalFile($dto, $file);
    
        return response()->json($setting, Response::HTTP_CREATED);
    }
    

    public function show(string $key)
    {
        $setting = $this->repository->findByKey($key);

        if (! $setting) {
            return response()->json(['message' => 'Setting not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json($setting);
    }

    public function update(StoreSettingRequest $request, string $key)
    {
        $data = $request->safe()->except(['value', 'key']);
        $file = $request->file('value');
    
        $data['value'] = ($request->input('type') === 'image' && $file)
            ? null
            : $request->input('value');
    
        $dto = new UpdateSettingDTO(...[$key, ...$data]);
    
        try {
            $setting = $this->repository->updateWithOptionalFile($dto, $file);
            return response()->json($setting);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }    

    public function destroy(string $key)
    {
        $deleted = $this->repository->deleteByKey($key);

        if (! $deleted) {
            return response()->json(['message' => 'Setting not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json([], Response::HTTP_NO_CONTENT);
    }

    // Páginas válidas para tema
    private const THEME_PAGES = ['home', 'products', 'product_detail', 'checkout', 'orders', 'account'];

    /**
     * Retorna as configurações de tema de uma página com metadados para o frontend renderizar os inputs.
     * GET /api/theme/{page}
     */
    public function themePage(string $page)
    {
        if (! in_array($page, self::THEME_PAGES)) {
            return response()->json(['message' => 'Página inválida.'], 404);
        }

        $settings = \App\Models\Setting::where('group', $page)->get();

        $result = [];
        foreach ($settings as $setting) {
            $subKey  = preg_replace('/^' . preg_quote($page . '.', '/') . '/', '', $setting->key);
            $parts   = explode('.', $subKey);
            $section = array_shift($parts);
            $field   = implode('.', $parts);

            $meta = [
                'value'       => $setting->value,
                'type'        => $setting->type,
                'label'       => $setting->label,
                'description' => $setting->description,
                'options'     => $setting->options,
            ];

            if ($field === '') {
                $result[$section] = $meta;
            } else {
                $result[$section][$field] = $meta;
            }
        }

        return response()->json($result);
    }

    /**
     * Reseta as configurações de tema de uma página para os valores default.
     * POST /api/theme/{page}/reset  — admin
     */
    public function resetThemePage(string $page)
    {
        if (! in_array($page, self::THEME_PAGES)) {
            return response()->json(['message' => 'Página inválida.'], 404);
        }

        $count = \App\Models\Setting::where('group', $page)
            ->whereNotNull('default_value')
            ->update(['value' => \Illuminate\Support\Facades\DB::raw('default_value')]);

        return response()->json([
            'message' => "{$count} configuração(ões) restaurada(s) para o padrão.",
            'page'    => $page,
        ]);
    }

    /**
     * Atualiza os valores DEFAULT de uma página (exclusivo super admin).
     * PUT /api/theme/{page}/defaults  — superadmin
     */
    public function updateThemeDefaults(Request $request, string $page)
    {
        if (! in_array($page, self::THEME_PAGES)) {
            return response()->json(['message' => 'Página inválida.'], 404);
        }

        $updated  = [];
        $notFound = [];

        foreach ($request->all() as $subKey => $value) {
            $fullKey = $page . '.' . $subKey;
            $setting = \App\Models\Setting::where('key', $fullKey)->first();

            if ($setting) {
                $setting->update(['default_value' => $value]);
                $updated[] = $subKey;
            } else {
                $notFound[] = $subKey;
            }
        }

        return response()->json([
            'message'   => count($updated) . ' valor(es) padrão atualizado(s).',
            'page'      => $page,
            'updated'   => $updated,
            'not_found' => $notFound,
        ]);
    }

    /**
     * Atualiza configurações de tema de uma página em lote (rota administrativa).
     * PUT /api/theme/{page}
     *
     * Body: objeto plano com sub-keys a atualizar, ex:
     * {
     *   "hero.title": "Nova coleção!",
     *   "hero.button_text": "Ver agora",
     *   "footer.phone": "(81) 99999-9999"
     * }
     */
    public function updateThemePage(Request $request, string $page)
    {
        if (! in_array($page, self::THEME_PAGES)) {
            return response()->json(['message' => 'Página inválida.'], 404);
        }

        $updated = [];
        $notFound = [];

        foreach ($request->all() as $subKey => $value) {
            $fullKey = $page . '.' . $subKey;
            $setting = \App\Models\Setting::where('key', $fullKey)->first();

            if ($setting) {
                $setting->update(['value' => $value]);
                $updated[] = $subKey;
            } else {
                $notFound[] = $subKey;
            }
        }

        return response()->json([
            'message'   => count($updated) . ' configuração(ões) atualizada(s).',
            'page'      => $page,
            'updated'   => $updated,
            'not_found' => $notFound,
        ]);
    }
}
