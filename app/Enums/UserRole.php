<?php

namespace App\Enums;

/**
 * Editors write and publish posts and look after tags and media. Admins can
 * also change templates, links and settings, and manage users.
 */
enum UserRole: string
{
    case Admin = 'admin';
    case Editor = 'editor';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Editor => 'Editor',
        };
    }
}
