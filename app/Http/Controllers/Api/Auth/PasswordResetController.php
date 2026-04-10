<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetMail;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class PasswordResetController extends Controller
{
    /**
     * Envia email com link de redefinição de senha
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Erro de validação',
                'errors' => $validator->errors(),
            ], 422);
        }

        $email = $request->email;

        // Resposta genérica independentemente de o email existir (anti-enumeração)
        $user = User::where('email', $email)->first();

        if ($user) {
            $token = bin2hex(random_bytes(32));

            DB::table('password_reset_tokens')
                ->where('email', $email)
                ->delete();

            DB::table('password_reset_tokens')->insert([
                'email'      => $email,
                'token'      => hash('sha256', $token),
                'created_at' => Carbon::now(),
            ]);

            $resetUrl = rtrim(config('app.frontend_url', config('app.url')), '/')
                . '/reset-password?token=' . $token
                . '&email=' . urlencode($email);

            try {
                Mail::to($email)->send(new PasswordResetMail($resetUrl, $email, $user->name));
            } catch (\Throwable $e) {
                Log::error('Erro ao enviar email de recuperação', [
                    'error' => $e->getMessage(),
                ]);

                return response()->json([
                    'message' => 'Erro ao enviar email. Por favor, tente novamente mais tarde.',
                ], 500);
            }
        }

        return response()->json([
            'message' => 'Se este email estiver cadastrado, você receberá as instruções em breve.',
        ]);
    }

    /**
     * Redefine a senha usando o token
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|email',
            'token'    => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ], [
            'password.min' => 'A senha deve ter no mínimo 8 caracteres.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Erro de validação',
                'errors' => $validator->errors(),
            ], 422);
        }

        $resetRecord = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (!$resetRecord) {
            return response()->json([
                'message' => 'Token inválido ou expirado.',
            ], 400);
        }

        if (Carbon::parse($resetRecord->created_at)->addMinutes(60)->isPast()) {
            DB::table('password_reset_tokens')
                ->where('email', $request->email)
                ->delete();

            return response()->json([
                'message' => 'Token expirado. Por favor, solicite um novo link de recuperação.',
            ], 400);
        }

        if (!hash_equals($resetRecord->token, hash('sha256', $request->token))) {
            return response()->json([
                'message' => 'Token inválido ou expirado.',
            ], 400);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'message' => 'Token inválido ou expirado.',
            ], 400);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        // Revoga todos os tokens Sanctum do usuário
        $user->tokens()->delete();

        DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->delete();

        return response()->json([
            'message' => 'Senha redefinida com sucesso! Você já pode fazer login com sua nova senha.',
        ]);
    }
}
