export class ApiError extends Error {
    constructor(
        public status: number,
        message: string,
    ) {
        super(message);
        this.name = 'ApiError';
    }
}

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

/** POST JSON с CSRF-токеном Laravel; бросает ApiError при неуспешном ответе. */
export async function postJson<T>(url: string, body: unknown = {}): Promise<T> {
    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
        },
        body: JSON.stringify(body),
    });

    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
        throw new ApiError(response.status, data?.message ?? response.statusText);
    }
    return data as T;
}

export function createSecret(payload: string, ttl: number) {
    return postJson<{ id: string; expires_at: string }>('/api/secrets', { payload, ttl });
}

export function revealSecret(id: string) {
    return postJson<{ payload: string }>(`/api/secrets/${id}/reveal`);
}
