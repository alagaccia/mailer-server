export type User = {
    id: number;
    name: string;
    email: string;
    is_admin: boolean;
    [key: string]: unknown;
};

export type Auth = {
    user: User | null;
};
