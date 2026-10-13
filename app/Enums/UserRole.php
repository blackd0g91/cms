<?php

namespace App\Enums;

/**
 * Editors write and publish posts and look after tags and media. Admins can
 * also change templates, links and settings, and manage users. Together they
 * are the staff, who use the control panel. Members sign up on the site and
 * never reach the control panel.
 */
enum UserRole: string
{
    case Admin = 'admin';
    case Editor = 'editor';
    case Member = 'member';

    /**
     * The roles that use the control panel.
     *
     * @return list<self>
     */
    public static function staff(): array
    {
        return [self::Admin, self::Editor];
    }

    public function isStaff(): bool
    {
        return in_array($this, self::staff(), true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Editor => 'Editor',
            self::Member => 'Member',
        };
    }
}
