import { requestJson } from '@/lib/http';
import { store } from '@/routes/cp/media';
import type { Media } from '@/types';

/**
 * The image types the media library takes (see MediaController::RULES).
 */
export const IMAGE_TYPES = [
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp',
    'image/avif',
    'image/svg+xml',
];

/**
 * Upload an image to the media library. Throws with the server's message
 * when it is refused (too large, not an image).
 */
export async function uploadImage(file: File): Promise<Media> {
    const body = new FormData();
    body.append('file', file);

    return (
        await requestJson<{ media: Media }>(store().url, {
            method: 'POST',
            body,
        })
    ).media;
}
