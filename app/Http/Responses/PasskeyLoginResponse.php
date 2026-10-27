<?php

namespace App\Http\Responses;

use App\Actions\Sharing\CompletePendingSharedClaim;
use App\Actions\Sharing\StorePendingSharedClaim;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse as PasskeyLoginResponseContract;

class PasskeyLoginResponse implements PasskeyLoginResponseContract
{
    public function __construct(
        private readonly CompletePendingSharedClaim $completePendingSharedClaim,
        private readonly StorePendingSharedClaim $storePendingSharedClaim,
    ) {}

    /**
     * Answer a passkey login with the URL the browser continues on (F-01).
     *
     * Mirrors the password login: a claim started on a shared list before
     * the login is completed and takes priority over the dashboard. Invalid
     * claim fields are dropped, as the password login rejects them.
     */
    public function toResponse($request): JsonResponse
    {
        $claim = Validator::make($request->all(), [
            'pending_claim_roster_token' => ['nullable', 'string'],
            'pending_claim_item_id' => ['nullable', 'integer'],
            'pending_claim_quantity' => ['nullable', 'numeric', 'min:0.01'],
        ]);

        if ($claim->passes()) {
            $this->storePendingSharedClaim->handle(
                $request,
                $request->string('pending_claim_roster_token')->value() ?: null,
                $request->integer('pending_claim_item_id') ?: null,
                $request->input('pending_claim_quantity') !== null ? (float) $request->input('pending_claim_quantity') : null,
            );
        }

        $redirect = $this->completePendingSharedClaim->handle($request, $request->user())
            ?? redirect()->route('dashboard');

        return new JsonResponse(['redirect' => $redirect->getTargetUrl()]);
    }
}
