<?php

declare(strict_types=1);

namespace App\Audit;

use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Contracts\Resolver;

class ImpersonatorResolver implements Resolver
{
    /**
     * Resolve the public id of the master impersonating the current session, if any.
     *
     * The value is set on the request by RestrictImpersonatedSession, so writes
     * performed under an impersonation session are attributed to the real actor.
     */
    public static function resolve(Auditable $auditable): ?string
    {
        $impersonatorId = request()->attributes->get('impersonator_id');

        return is_string($impersonatorId) ? $impersonatorId : null;
    }
}
