<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use LogsActivity;

    /**
     * Protected roles that cannot be deleted.
     */
    public static array $protectedRoles = ['super_admin', 'fallback', 'user'];

    /**
     * Check if this role is protected.
     */
    public function isProtected(): bool
    {
        return in_array($this->name, self::$protectedRoles);
    }
}
