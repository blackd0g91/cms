/**
 * Small JSON helper for the few control panel requests that happen outside
 * of Inertia visits (previews, the image picker).
 */

export class HttpError extends Error {
    constructor(
        message: string,
        public readonly status: number,
    ) {
        super(message);
    }
}

const xsrfToken = () =>
    decodeURIComponent(
        document.cookie
            .split('; ')
            .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
            ?.slice('XSRF-TOKEN='.length) ?? '',
    );

export async function requestJson<T>(
    url: string,
    options: { method?: string; body?: FormData | object } = {},
): Promise<T> {
    const isForm = options.body instanceof FormData;

    const response = await fetch(url, {
        method: options.method ?? 'GET',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
            ...(options.body && !isForm
                ? { 'Content-Type': 'application/json' }
                : {}),
        },
        body: isForm
            ? (options.body as FormData)
            : options.body
              ? JSON.stringify(options.body)
              : undefined,
    });

    const json = (await response.json().catch(() => ({}))) as {
        message?: string;
    };

    if (!response.ok) {
        throw new HttpError(
            json.message ?? `Request failed (${response.status})`,
            response.status,
        );
    }

    return json as T;
}
