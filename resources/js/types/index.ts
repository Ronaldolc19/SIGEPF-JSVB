import { LucideIcon } from 'lucide-react';

export interface Auth {
    user: User;
    roles: string[];
    canViewPersonal: boolean;
    modulesByRole: Record<string, string[]>;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    url: string;
    icon?: LucideIcon | null;
    isActive?: boolean;
}

export interface SharedData {
    promotionCalendar: {
        selected: string;
        label: string;
        isAdmin: boolean;
        options: { id_promocion: number; nombre: string; fecha_inicio: string; fecha_fin: string; estado: string }[];
        participaciones: {
            id_personal: number;
            id_promocion: number | null;
            fecha_ingreso: string | null;
            fecha_termino: string | null;
            estado: string;
        }[];
    } | null;
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    [key: string]: unknown;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown; // This allows for additional properties...
}
