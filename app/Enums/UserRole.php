<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case User = 'user';
    case Admin = 'admin';
    case Master = 'master';

    /**
     * Whether the role may read other users' data (listing, viewing, audit trail).
     */
    public function canViewOthers(): bool
    {
        return $this === self::Admin || $this === self::Master;
    }

    /**
     * Whether the role may mutate other users (update, delete, restore, assign roles).
     */
    public function canManageOthers(): bool
    {
        return $this === self::Master;
    }
}
