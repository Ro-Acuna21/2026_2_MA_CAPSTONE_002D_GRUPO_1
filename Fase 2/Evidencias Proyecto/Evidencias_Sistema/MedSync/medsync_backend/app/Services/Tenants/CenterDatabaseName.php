<?php

namespace App\Services\Tenants;

class CenterDatabaseName
{
    public static function fromSlug(string $slug): string
    {
        $readable = 'medsync_'.str_replace('-', '_', $slug);

        if (strlen($readable) <= 63) {
            return $readable;
        }

        // PostgreSQL limits identifiers to 63 bytes. Preserve a readable
        // prefix and a stable suffix so long slugs cannot collide by truncation.
        return substr($readable, 0, 54).'_'.substr(hash('sha256', $slug), 0, 8);
    }
}
