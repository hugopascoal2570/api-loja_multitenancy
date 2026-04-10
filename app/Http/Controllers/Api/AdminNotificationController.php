<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminNotificationController extends Controller
{
    /**
     * Lista notificacoes nao lidas (para polling do front-end).
     * GET /api/notifications/unread
     *
     * O front-end faz polling a cada 30s nesta rota.
     * Se retornar notificacoes, toca o som e exibe popup.
     */
    public function unread(): JsonResponse
    {
        $notifications = AdminNotification::unread()
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        return response()->json([
            'count' => $notifications->count(),
            'notifications' => $notifications,
        ]);
    }

    /**
     * Lista todas as notificacoes (paginado).
     * GET /api/notifications
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min($request->integer('per_page', 20), 100);

        $notifications = AdminNotification::orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json($notifications);
    }

    /**
     * Marca uma notificacao como lida.
     * PUT /api/notifications/{id}/read
     */
    public function markAsRead(AdminNotification $notification): JsonResponse
    {
        $notification->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        return response()->json(['message' => 'Notificacao marcada como lida.']);
    }

    /**
     * Marca todas as notificacoes como lidas.
     * PUT /api/notifications/read-all
     */
    public function markAllAsRead(): JsonResponse
    {
        AdminNotification::unread()->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        return response()->json(['message' => 'Todas as notificacoes marcadas como lidas.']);
    }
}
