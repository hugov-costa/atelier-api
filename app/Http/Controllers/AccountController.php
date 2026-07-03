<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Account\DeleteAccountRequest;
use App\Http\Resources\AuditResource;
use App\Http\Resources\ImpersonationResource;
use App\Http\Resources\UserResource;
use App\Models\Impersonation;
use App\Models\User;
use App\Services\AccountService;
use App\Services\AuthService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use OwenIt\Auditing\Models\Audit;
use Symfony\Component\HttpFoundation\StreamedJsonResponse;

#[Group('Account', weight: 2)]
class AccountController extends Controller
{
    public function __construct(
        private AuthService $auth,
        private AccountService $account,
    ) {}

    /**
     * Export personal data
     *
     * Returns the personal data held about the authenticated account (profile, audit trail
     * and any administrative access to the account) as a downloadable JSON document,
     * supporting LGPD/GDPR data-portability and transparency requests.
     */
    public function export(Request $request): StreamedJsonResponse
    {
        $user = $this->currentUser($request);

        return response()->streamJson([
            'data' => [
                'user'   => (new UserResource($user))->resolve($request),
                'audits' => $user->audits()->latest()->lazy()->map(
                    fn (Audit $audit): array => (new AuditResource($audit))->resolve($request)
                ),
                'access_log' => Impersonation::query()
                    ->with('impersonator')
                    ->where('impersonated_id', $user->getKey())
                    ->latest()
                    ->lazy()
                    ->map(fn (Impersonation $record): array => (new ImpersonationResource($record))->resolve($request)),
                'exported_at' => now()->toIso8601String(),
            ],
            'message' => null,
        ], 200, ['Content-Disposition' => 'attachment; filename="account-data.json"']);
    }

    /**
     * Delete the account
     *
     * Irreversibly erases the authenticated account after confirming the current `password`:
     * personal data is anonymised, the avatar is removed from storage, every token is revoked
     * and the auth cookies are cleared. Fulfils the LGPD/GDPR right to erasure.
     */
    public function destroy(DeleteAccountRequest $request): Response
    {
        $user = $this->currentUser($request);

        $this->account->erase($user);

        return response()->noContent()
            ->withCookie($this->auth->forgetTokenCookie())
            ->withCookie($this->auth->forgetCsrfCookie());
    }

    private function currentUser(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(Response::HTTP_FORBIDDEN);
        }

        return $user;
    }
}
