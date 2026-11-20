export function formatBytes(bytes: number): string {
    if (bytes < 1024) return `${bytes} Б`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} КБ`;
    return `${(bytes / 1024 / 1024).toFixed(1)} МБ`;
}

const TTL_LABELS: Record<number, string> = {
    300: '5 минут',
    3600: '1 час',
    86400: '1 день',
    604800: '7 дней',
};

export function ttlLabel(seconds: number): string {
    return TTL_LABELS[seconds] ?? `${Math.round(seconds / 60)} мин`;
}
