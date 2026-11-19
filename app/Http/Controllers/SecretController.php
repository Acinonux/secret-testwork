<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSecretRequest;
use App\Models\Secret;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SecretController extends Controller
{
    public function store(StoreSecretRequest $request): JsonResponse
    {
        $payload = $request->decodedPayload();

        $secret = Secret::create([
            'payload' => $payload,
            'size' => strlen($payload),
            'expires_at' => now()->addSeconds((int) $request->integer('ttl')),
        ]);

        return response()->json([
            'id' => $secret->id,
            'expires_at' => $secret->expires_at->toIso8601String(),
        ], 201);
    }

    /**
     * Отдаёт зашифрованный блоб и удаляет его. Блокировка строки гарантирует,
     * что при параллельных запросах данные получит только один из них.
     */
    public function reveal(string $secret): JsonResponse
    {
        $payload = DB::transaction(function () use ($secret) {
            $model = Secret::active()->whereKey($secret)->lockForUpdate()->first();
            if (! $model) {
                return null;
            }
            $model->delete();

            return $model->payload;
        });

        abort_if($payload === null, 404);

        return response()->json(['payload' => base64_encode($payload)]);
    }
}
