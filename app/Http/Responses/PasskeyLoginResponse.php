<?php

namespace App\Http\Responses;

use App\Actions\Sharing\CompletePendingSharedClaim;
use App\Actions\Sharing\StorePendingSharedClaim;
use Illuminate\Http\JsonResponse;
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
     * the login is completed and takes priority over the dashboard.
     */
    public function toResponse($request): JsonResponse
    {
        $this->storePendingSharedClaim->handle(
            $request,
            $request->string('pending_claim_roster_token')->value() ?: null,
            $request->integer('pending_claim_item_id') ?: null,
            $request->input('pending_claim_quantity') !== null ? (float) $request->input('pending_claim_quantity') : null,
        );

        $redirect = $this->completePendingSharedClaim->handle($request, $request->user())
            ?? redirect()->route('dashboard');

        return new JsonResponse(['redirect' => $redirect->getTargetUrl()]);
    }
}
