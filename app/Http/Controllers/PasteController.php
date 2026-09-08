<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePasteRequest;
use App\Models\Paste;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PasteController extends Controller
{
    /**
     * Show the form for writing a new paste.
     */
    public function create(): Response
    {
        return Inertia::render('paste/Create', [
            'expiryOptions' => array_keys(config('paste.expiry_options')),
            'defaultExpiry' => config('paste.default_expiry'),
            'maxPayloadBytes' => config('paste.max_payload_bytes'),
        ]);
    }

    /**
     * Store an already encrypted paste.
     *
     * The request only ever carries ciphertext. The key that decrypts it stays
     * in the browser and is placed in the fragment of the share URL.
     */
    public function store(StorePasteRequest $request): JsonResponse
    {
        $retention = $request->retentionSeconds();
        $deleteToken = Paste::generateDeleteToken();

        $paste = new Paste($request->safe()->only([
            'payload', 'format', 'has_password', 'burn_after_reading',
        ]));

        $paste->id = Paste::generateId();
        $paste->delete_token_hash = Paste::hashDeleteToken($deleteToken);
        $paste->expires_at = $retention === null ? null : now()->addSeconds($retention);
        $paste->save();

        return response()->json([
            'id' => $paste->id,
            'url' => route('paste.show', $paste),
            'deleteUrl' => route('paste.delete', ['paste' => $paste->id, 'token' => $deleteToken]),
            'expiresAt' => $paste->expires_at?->toIso8601String(),
        ], 201);
    }

    /**
     * Show the reader for a paste.
     *
     * A paste that burns on reading is not handed over here; the reader has to
     * confirm first, so that a link preview cannot destroy it.
     */
    public function show(string $paste): Response
    {
        $model = $this->findAvailable($paste);

        return Inertia::render('paste/Show', [
            'paste' => [
                'id' => $model->id,
                'format' => $model->format,
                'hasPassword' => $model->has_password,
                'burnAfterReading' => $model->burn_after_reading,
                'expiresAt' => $model->expires_at?->toIso8601String(),
                'payload' => $model->burn_after_reading ? null : $model->payload,
            ],
        ]);
    }

    /**
     * Hand over the ciphertext of a burn-after-reading paste and destroy it.
     *
     * The row is locked and deleted inside the transaction, so that two readers
     * racing for the same paste cannot both walk away with the ciphertext.
     */
    public function reveal(string $paste): JsonResponse
    {
        $payload = DB::transaction(function () use ($paste): ?string {
            $model = Paste::query()->available()->lockForUpdate()->find($paste);

            if ($model === null || ! $model->burn_after_reading) {
                return null;
            }

            $payload = $model->payload;
            $model->delete();

            return $payload;
        });

        if ($payload === null) {
            throw new NotFoundHttpException;
        }

        return response()->json(['payload' => $payload]);
    }

    /**
     * Show the confirmation screen behind the creator's delete link.
     */
    public function confirmDelete(string $paste, string $token): Response
    {
        $model = $this->findAvailable($paste);

        if (! $model->deleteTokenMatches($token)) {
            throw new NotFoundHttpException;
        }

        return Inertia::render('paste/Delete', [
            'pasteId' => $model->id,
            'token' => $token,
        ]);
    }

    /**
     * Delete a paste on behalf of its creator.
     */
    public function destroy(Request $request, string $paste): RedirectResponse
    {
        $model = $this->findAvailable($paste);
        $token = $request->string('token')->toString();

        if (! $model->deleteTokenMatches($token)) {
            throw new NotFoundHttpException;
        }

        $model->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Paste deleted.')]);

        return to_route('home');
    }

    /**
     * Find a paste that exists and has not expired yet.
     */
    protected function findAvailable(string $id): Paste
    {
        $paste = Paste::query()->available()->find($id);

        if ($paste === null) {
            throw new NotFoundHttpException;
        }

        return $paste;
    }
}
