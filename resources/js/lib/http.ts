/**
 * Plain fetch helpers for the few endpoints that aren't Inertia visits (the AI features, which
 * return JSON or a stream and shouldn't navigate the page).
 */

/** Laravel sets the XSRF-TOKEN cookie on every response; echoing it back passes CSRF checks. */
export function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
}

export class HttpError extends Error {
    constructor(
        message: string,
        public status: number,
    ) {
        super(message);
    }
}

/** A message that's safe to show, for the status codes these endpoints actually return. */
export async function errorMessage(response: Response): Promise<string> {
    if (response.status === 429) {
        return 'That was a lot of requests in a row. Give it a minute and try again.';
    }

    if (response.status === 419) {
        return 'Your session expired. Refresh the page and try again.';
    }

    if (response.status === 403) {
        return "You don't have access to do that on this lead.";
    }

    try {
        const json = await response.json();

        return json.message ?? 'Something went wrong.';
    } catch {
        return 'Something went wrong.';
    }
}

export async function postJson<T>(url: string, body: unknown = {}): Promise<T> {
    const response = await fetch(url, {
        method: 'POST',
        body: JSON.stringify(body),
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
        },
    });

    if (!response.ok) {
        throw new HttpError(await errorMessage(response), response.status);
    }

    return response.json() as Promise<T>;
}
