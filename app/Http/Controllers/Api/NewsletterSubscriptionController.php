<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Newsletter\SubscribeRequest;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class NewsletterSubscriptionController extends Controller
{
    public function subscribe(SubscribeRequest $request): JsonResponse
    {
        $existing = NewsletterSubscriber::where('email', $request->email)->first();

        if ($existing) {
            if ($existing->status === 'active') {
                return response()->json([
                    'message' => 'Este email já está inscrito na nossa newsletter.',
                ], 409);
            }

            // Reativar inscrito que tinha se descadastrado
            $existing->update([
                'status' => 'active',
                'unsubscribed_at' => null,
                'subscribed_at' => now(),
                'unsubscribe_token' => Str::uuid()->toString(),
            ]);

            return response()->json([
                'message' => 'Inscrição reativada com sucesso! Você voltará a receber nossas novidades.',
            ], 200);
        }

        NewsletterSubscriber::create([
            'email' => $request->email,
            'unsubscribe_token' => Str::uuid()->toString(),
            'subscribed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Inscrição realizada com sucesso! Você receberá nossas novidades por email.',
        ], 201);
    }

    public function unsubscribe(string $token): JsonResponse
    {
        $subscriber = NewsletterSubscriber::where('unsubscribe_token', $token)->first();

        if (!$subscriber) {
            return response()->json([
                'message' => 'Link de descadastramento inválido.',
            ], 404);
        }

        if ($subscriber->status === 'unsubscribed') {
            return response()->json([
                'message' => 'Você já foi descadastrado anteriormente.',
            ]);
        }

        $subscriber->update([
            'status' => 'unsubscribed',
            'unsubscribed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Você foi descadastrado com sucesso. Não receberá mais emails da nossa newsletter.',
        ]);
    }

    public function status(string $email): JsonResponse
    {
        $subscriber = NewsletterSubscriber::where('email', $email)->first();

        if (!$subscriber) {
            return response()->json([
                'subscribed' => false,
            ]);
        }

        return response()->json([
            'subscribed' => $subscriber->status === 'active',
            'status' => $subscriber->status,
        ]);
    }
}
