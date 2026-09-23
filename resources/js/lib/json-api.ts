/**
 * JSON calls to admin endpoints that answer with data rather than a redirect.
 *
 * Inertia's `router` is for visits — it expects a page response and re-renders on one.
 * The console and the database list want a value back and the page left alone, so they
 * use `fetch` directly and this carries the two things that are easy to forget: the
 * session cookie, and the CSRF token Laravel requires on every non-GET.
 */

/**
 * Laravel sets XSRF-TOKEN as a readable (url-encoded) cookie for exactly this. The
 * `X-XSRF-TOKEN` header is the encrypted form, which `VerifyCsrfToken` decrypts itself —
 * unlike `X-CSRF-TOKEN`, which would need the raw session token rendered into the page.
 */
const xsrfToken = (): string => {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
};

export type JsonResult<T> = { ok: true; data: T } | { ok: false; error: string };

const parse = async <T>(response: Response, fallback: string): Promise<JsonResult<T>> => {
    let body: Record<string, unknown> | null = null;

    try {
        body = await response.json();
    } catch {
        // A 500 rendered as HTML, or an empty body — either way there is no message.
        return { ok: false, error: fallback };
    }

    if (!response.ok) {
        // Laravel answers a failed `validate()` with { message, errors }, and these
        // endpoints answer a handled failure with { error }.
        return { ok: false, error: String(body?.error ?? body?.message ?? fallback) };
    }

    return { ok: true, data: body as T };
};

export const getJson = async <T>(url: string, fallback: string): Promise<JsonResult<T>> => {
    try {
        const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });

        return parse<T>(response, fallback);
    } catch {
        return { ok: false, error: fallback };
    }
};

export const postJson = async <T>(url: string, body: Record<string, unknown>, fallback: string): Promise<JsonResult<T>> => {
    try {
        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': xsrfToken(),
            },
            body: JSON.stringify(body),
        });

        return parse<T>(response, fallback);
    } catch {
        return { ok: false, error: fallback };
    }
};

/**
 * POST and read a Server-Sent Events reply, calling `onEvent` per event.
 *
 * `EventSource` cannot do this — it is GET-only and carries no CSRF header —
 * so the stream is read off `fetch` by hand. Buffer until a blank line rather
 * than treating each read as an event: a chunk boundary lands mid-JSON often
 * enough that assuming otherwise drops events silently.
 *
 * Returns an error the same way `postJson` does, so a caller handles a dead
 * backend and a dead connection identically.
 */
export const streamJson = async (
    url: string,
    body: Record<string, unknown>,
    fallback: string,
    onEvent: (event: Record<string, unknown>) => void,
): Promise<JsonResult<null>> => {
    try {
        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'text/event-stream',
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': xsrfToken(),
            },
            body: JSON.stringify(body),
        });

        if (!response.ok || !response.body) {
            // Validation and throttling still answer normally, before the
            // stream opens, so their message is worth reading out.
            return parse<null>(response, fallback);
        }

        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';

        for (;;) {
            const { done, value } = await reader.read();

            if (done) {
                break;
            }

            buffer += decoder.decode(value, { stream: true });

            let split = buffer.indexOf('\n\n');

            while (split !== -1) {
                const frame = buffer.slice(0, split);
                buffer = buffer.slice(split + 2);
                split = buffer.indexOf('\n\n');

                const payload = frame
                    .split('\n')
                    .filter((line) => line.startsWith('data:'))
                    .map((line) => line.slice(5).trim())
                    .join('');

                if (payload !== '') {
                    try {
                        onEvent(JSON.parse(payload));
                    } catch {
                        // A truncated frame is not worth killing the stream for.
                    }
                }
            }
        }

        return { ok: true, data: null };
    } catch {
        return { ok: false, error: fallback };
    }
};
