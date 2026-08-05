export type AttachmentMeta = {
    count: number;
    size: string;
} | null;

export type EmailRow = {
    id: number;
    recipient: string;
    subject: string;
    status: string;
    attachments_meta: AttachmentMeta;
    created_at: string | null;
};

export type EmailAttachment = {
    filename?: string;
    content?: string;
    mime?: string;
};

export type EmailDetail = {
    id: number;
    uuid: string | null;
    recipient: string;
    subject: string;
    body: string;
    status: string;
    attachments: EmailAttachment[];
    webhook: string | null;
    last_error: string | null;
    created_at: string | null;
};

export type Paginator<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
    from: number | null;
    to: number | null;
};

export type EmailStats = {
    pending: number;
    sent: number;
    failed: number;
    total: number;
};
