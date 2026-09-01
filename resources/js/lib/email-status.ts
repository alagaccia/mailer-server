import type { EmailStatus } from '@/types/bridge';

/**
 * Etichette italiane degli stati email: unica fonte per la tabella, il badge
 * e la tendina dei filtri, così le due viste non possono divergere.
 */
export const EMAIL_STATUS_LABELS: Record<EmailStatus, string> = {
    pending: 'In coda',
    sending: 'In invio',
    sent: 'Inviata',
    failed: 'Fallita',
};

export function emailStatusLabel(status: string): string {
    return EMAIL_STATUS_LABELS[status as EmailStatus] ?? status;
}
