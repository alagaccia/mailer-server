export type JsonResult<T> = {
    status: number;
    ok: boolean;
    data: T & {
        message?: string;
        error?: string;
        errors?: Record<string, string[]>;
    };
};

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

/**
 * Piccolo client fetch JSON con gestione CSRF per gli endpoint non-Inertia
 * (installer, azioni della dashboard, test SMTP).
 */
export async function requestJson<T = Record<string, unknown>>(
    url: string,
    options: { method?: string; body?: unknown } = {},
): Promise<JsonResult<T>> {
    const response = await fetch(url, {
        method: options.method ?? 'GET',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
        },
        credentials: 'same-origin',
        body:
            options.body === undefined
                ? undefined
                : JSON.stringify(options.body),
    });

    let data = {} as JsonResult<T>['data'];

    try {
        data = (await response.json()) as JsonResult<T>['data'];
    } catch {
        // risposta senza corpo JSON
    }

    return { status: response.status, ok: response.ok, data };
}

export function getJson<T = Record<string, unknown>>(
    url: string,
): Promise<JsonResult<T>> {
    return requestJson<T>(url);
}

export function postJson<T = Record<string, unknown>>(
    url: string,
    body?: unknown,
): Promise<JsonResult<T>> {
    return requestJson<T>(url, { method: 'POST', body });
}

/**
 * Primo messaggio d'errore da una risposta di validazione 422.
 */
export function firstValidationError(data: {
    message?: string;
    errors?: Record<string, string[]>;
}): string {
    if (data.errors) {
        const first = Object.values(data.errors)[0];

        if (first && first.length > 0) {
            return first[0];
        }
    }

    return data.message ?? 'Errore di validazione.';
}
