import { FolderOpen, Settings2, ShieldCheck, Users, type LucideIcon } from 'lucide-react';

export interface AdminModule {
    id: string;
    title: string;
    description: string;
    icon: LucideIcon;
    group: 'Gestión y seguimiento' | 'Administración';
    screens: string[];
}

export const adminModules: AdminModule[] = [
    {
        id: 'promociones',
        title: 'Promociones',
        description: 'Organiza generaciones, carreras y periodos; conserva el historial de cada pasante.',
        icon: Users,
        group: 'Gestión y seguimiento',
        screens: ['Calendario', 'Carreras participantes', 'Historial y periodos individuales'],
    },
    {
        id: 'consulta',
        title: 'Portal de consulta',
        description: 'Publica guías, formatos y materiales de apoyo para el personal en formación.',
        icon: FolderOpen,
        group: 'Gestión y seguimiento',
        screens: ['Materiales de apoyo', 'Publicación por destinatarios'],
    },
    {
        id: 'incidencias',
        title: 'Incidencias y oficios',
        description: 'Registra incidencias, da seguimiento y envía oficios a los pasantes.',
        icon: FolderOpen,
        group: 'Gestión y seguimiento',
        screens: ['Registro', 'Seguimiento', 'Oficios para pasantes'],
    },
    {
        id: 'expedientes',
        title: 'Expedientes digitales',
        description: 'Consulta los documentos entregados por cada pasante y registra observaciones.',
        icon: FolderOpen,
        group: 'Gestión y seguimiento',
        screens: ['Control de entrega', 'Descarga de documentos', 'Observaciones'],
    },
    {
        id: 'personas',
        title: 'Personas',
        description: 'Registra y actualiza los datos personales de asesores y personal en formación.',
        icon: Users,
        group: 'Administración',
        screens: ['Listado y búsqueda', 'Registro y edición de datos personales', 'Vínculo con cuentas de acceso'],
    },
    {
        id: 'personal',
        title: 'Personal en formación',
        description: 'Consulta las participaciones registradas del personal en formación.',
        icon: Users,
        group: 'Gestión y seguimiento',
        screens: ['Listado de participaciones', 'Consulta de datos generales'],
    },
    {
        id: 'acceso',
        title: 'Usuarios y permisos',
        description: 'Organiza las cuentas y sus responsabilidades dentro del sistema.',
        icon: ShieldCheck,
        group: 'Administración',
        screens: ['Cuentas de acceso', 'Asignación de roles', 'Módulos por rol'],
    },
    {
        id: 'catalogos',
        title: 'Catálogos',
        description: 'Administra los datos geográficos, académicos y de apoyo al seguimiento.',
        icon: Settings2,
        group: 'Administración',
        screens: ['Catálogos geográficos y académicos', 'Unidades médicas'],
    },
];
