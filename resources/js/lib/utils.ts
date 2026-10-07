import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function slugify(value: string, separator = '-') {
    return value
        .normalize('NFKD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, separator)
        .replace(new RegExp(`^\\${separator}+|\\${separator}+$`, 'g'), '');
}

/**
 * Up to two capital letters for a name, for a small badge: "Ana Souza" → "AS".
 */
export function initials(name: string) {
    return (
        name
            .split(/\s+/)
            .filter(Boolean)
            .slice(0, 2)
            .map((word) => word[0]?.toUpperCase())
            .join('') || '?'
    );
}

/**
 * How long ago a moment was, briefly: "just now", "5 min ago", "3 h ago",
 * then the date.
 */
export function timeAgo(iso: string) {
    const minutes = Math.round((Date.now() - new Date(iso).getTime()) / 60000);

    if (minutes < 1) {
        return 'just now';
    }

    if (minutes < 60) {
        return `${minutes} min ago`;
    }

    const hours = Math.round(minutes / 60);

    return hours < 48
        ? `${hours} h ago`
        : new Date(iso).toLocaleDateString(undefined, { dateStyle: 'medium' });
}

/**
 * How many calendar days ago a moment was: "today", "yesterday", "12 days ago".
 */
export function daysAgo(iso: string) {
    const startOfDay = (date: Date) =>
        new Date(date.getFullYear(), date.getMonth(), date.getDate()).getTime();
    const days = Math.round(
        (startOfDay(new Date()) - startOfDay(new Date(iso))) / 86400000,
    );

    if (days <= 0) {
        return 'today';
    }

    return days === 1 ? 'yesterday' : `${days.toLocaleString()} days ago`;
}
