<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use HasFactory, LogsActivity;

    /**
     * Protected roles that cannot be deleted.
     */
    public static array $protectedRoles = ['super_admin', 'fallback', 'user'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return array_merge(parent::casts(), ['is_active' => 'boolean']);
    }

    /**
     * Check if this role is protected.
     */
    public function isProtected(): bool
    {
        return in_array($this->name, self::$protectedRoles);
    }
}
