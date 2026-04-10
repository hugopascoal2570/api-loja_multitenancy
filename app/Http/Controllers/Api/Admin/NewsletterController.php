<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Newsletter\StoreNewsletterRequest;
use App\Http\Requests\Api\Newsletter\UpdateNewsletterRequest;
use App\Jobs\SendNewsletterJob;
use App\Models\Newsletter;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class NewsletterController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $newsletters = Newsletter::with('createdBy:id,name')
            ->orderByDesc('created_at')
            ->paginate($request->get('per_page', 15));

        return response()->json($newsletters);
    }

    public function store(StoreNewsletterRequest $request): JsonResponse
    {
        $data = [
            'title' => $request->title,
            'content' => $request->content,
            'status' => 'draft',
            'created_by' => Auth::id(),
        ];

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('newsletters', 'public');
        }

        $newsletter = Newsletter::create($data);

        return response()->json([
            'message' => 'Newsletter criada com sucesso.',
            'data' => $newsletter,
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $newsletter = Newsletter::with('createdBy:id,name')->findOrFail($id);

        return response()->json([
            'data' => $newsletter,
            'image_url' => $newsletter->image_path
                ? Storage::disk('public')->url($newsletter->image_path)
                : null,
        ]);
    }

    public function update(UpdateNewsletterRequest $request, string $id): JsonResponse
    {
        $newsletter = Newsletter::findOrFail($id);

        if ($newsletter->status !== 'draft') {
            return response()->json([
                'message' => 'Apenas newsletters em rascunho podem ser editadas.',
            ], 422);
        }

        $data = array_filter($request->only(['title', 'content']), fn ($v) => $v !== null);

        if ($request->hasFile('image')) {
            // Remove imagem anterior
            if ($newsletter->image_path) {
                Storage::disk('public')->delete($newsletter->image_path);
            }
            $data['image_path'] = $request->file('image')->store('newsletters', 'public');
        }

        $newsletter->update($data);

        return response()->json([
            'message' => 'Newsletter atualizada com sucesso.',
            'data' => $newsletter->fresh(),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $newsletter = Newsletter::findOrFail($id);

        if ($newsletter->status !== 'draft') {
            return response()->json([
                'message' => 'Apenas newsletters em rascunho podem ser removidas.',
            ], 422);
        }

        if ($newsletter->image_path) {
            Storage::disk('public')->delete($newsletter->image_path);
        }

        $newsletter->delete();

        return response()->json([
            'message' => 'Newsletter removida com sucesso.',
        ]);
    }

    public function send(string $id): JsonResponse
    {
        $newsletter = Newsletter::findOrFail($id);

        if (!in_array($newsletter->status, ['draft', 'scheduled'])) {
            return response()->json([
                'message' => 'Esta newsletter já foi enviada ou está em envio.',
            ], 422);
        }

        $activeSubscribers = NewsletterSubscriber::active()->count();

        if ($activeSubscribers === 0) {
            return response()->json([
                'message' => 'Não há inscritos ativos para enviar a newsletter.',
            ], 422);
        }

        $newsletter->update(['status' => 'sending']);

        SendNewsletterJob::dispatchSync($newsletter->id);

        return response()->json([
            'message' => "Newsletter em envio para {$activeSubscribers} inscritos.",
            'total_recipients' => $activeSubscribers,
        ]);
    }

    public function schedule(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'scheduled_at' => 'required|date|after:now',
        ], [
            'scheduled_at.required' => 'A data de agendamento é obrigatória.',
            'scheduled_at.date' => 'Informe uma data válida.',
            'scheduled_at.after' => 'A data de agendamento deve ser no futuro.',
        ]);

        $newsletter = Newsletter::findOrFail($id);

        if (!in_array($newsletter->status, ['draft', 'scheduled'])) {
            return response()->json([
                'message' => 'Esta newsletter já foi enviada ou está em envio.',
            ], 422);
        }

        $newsletter->update([
            'status' => 'scheduled',
            'scheduled_at' => $request->scheduled_at,
        ]);

        return response()->json([
            'message' => 'Newsletter agendada com sucesso para ' . $newsletter->scheduled_at->format('d/m/Y H:i'),
            'data' => $newsletter->fresh(),
        ]);
    }

    public function subscribers(Request $request): JsonResponse
    {
        $query = NewsletterSubscriber::orderByDesc('created_at');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('search')) {
            $query->where('email', 'like', '%' . $request->search . '%');
        }

        $subscribers = $query->paginate($request->get('per_page', 15));

        return response()->json($subscribers);
    }

    public function removeSubscriber(string $id): JsonResponse
    {
        $subscriber = NewsletterSubscriber::findOrFail($id);
        $subscriber->delete();

        return response()->json([
            'message' => 'Inscrito removido com sucesso.',
        ]);
    }

    /**
     * Upload de imagem do editor (React Quill).
     * Retorna a URL pública da imagem para inserir no HTML.
     */
    public function uploadImage(Request $request): JsonResponse
    {
        $request->validate([
            'image' => 'required|image|mimes:jpg,jpeg,png,webp,gif|max:2048',
        ], [
            'image.required' => 'Nenhuma imagem enviada.',
            'image.image' => 'O arquivo deve ser uma imagem.',
            'image.mimes' => 'A imagem deve ser JPG, JPEG, PNG, WebP ou GIF.',
            'image.max' => 'A imagem não pode ter mais de 2MB.',
        ]);

        $path = $request->file('image')->store('newsletter-images', 'public');
        $url = Storage::disk('public')->url($path);

        return response()->json([
            'url' => $url,
        ]);
    }
}
