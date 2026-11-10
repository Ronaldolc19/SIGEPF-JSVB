import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import AppLayout from '@/layouts/app-layout';
import { type SharedData } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

interface Role {
    id: number;
    codigo: string;
    nombre: string;
    permisos: { codigo: string; nombre: string }[];
}
interface Account {
    id: number;
    name: string;
    email: string;
    roles: Role[];
    id_persona: number | null;
    persona: { nombre: string; ap: string; am: string | null } | null;
}
interface Props {
    users: { data: Account[]; prev_page_url: string | null; next_page_url: string | null; current_page: number; last_page: number };
    roles: Role[];
    modules: Record<string, { nombre: string; acciones: string[] }>;
    roleModules: Record<string, Record<string, string[]>>;
    status: string | null;
}

export default function UserRoles({ users, roles, modules, roleModules, status }: Props) {
    const { auth } = usePage<SharedData>().props;
    const [selected, setSelected] = useState<Account | null>(null);
    const [editingRole, setEditingRole] = useState<Role | null>(null);
    const form = useForm<{ roles: string[] }>({ roles: [] });
    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Inicio', href: route('dashboard') },
                { title: 'Usuarios y permisos', href: route('usuarios.index') },
            ]}
        >
            <Head title="Usuarios y permisos" />
            <div className="mx-auto w-full max-w-6xl space-y-6 p-5 md:p-8">
                <div>
                    <h1 className="text-2xl font-semibold">Usuarios y permisos</h1>
                    <Button asChild className="mt-4">
                        <Link href={route('usuarios.create')}>Crear cuenta de usuario</Link>
                    </Button>
                    <p className="text-muted-foreground mt-2 text-sm">Asigna los roles que determinan el inicio y el acceso de cada cuenta.</p>
                </div>
                {status && (
                    <p role="status" className="rounded-lg border border-emerald-500/30 bg-emerald-500/10 p-4 text-sm">
                        {status}
                    </p>
                )}
                <div className="bg-card overflow-x-auto rounded-xl border">
                    <table className="w-full min-w-[600px] text-left text-sm">
                        <caption className="sr-only">Cuentas de usuario y roles asignados</caption>
                        <thead className="bg-muted/50">
                            <tr>
                                {['Cuenta', 'Roles', 'Acciones'].map((title) => (
                                    <th key={title} className="p-4" scope="col">
                                        {title}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {users.data.map((user) => (
                                <tr className="border-t" key={user.id}>
                                    <td className="p-4">
                                        <p className="font-medium">
                                            {user.name}
                                            {auth.user.id === user.id ? ' (tú)' : ''}
                                        </p>
                                        <p className="text-muted-foreground mt-1">{user.email}</p>
                                        <p className="text-muted-foreground mt-1 text-xs">
                                            {user.persona
                                                ? `Persona: ${[user.persona.nombre, user.persona.ap, user.persona.am].filter(Boolean).join(' ')}`
                                                : user.id_persona
                                                  ? 'Persona vinculada eliminada'
                                                  : 'Sin persona vinculada'}
                                        </p>
                                    </td>
                                    <td className="p-4">
                                        <div className="flex flex-wrap gap-2">
                                            {user.roles.length ? (
                                                user.roles.map((role) => (
                                                    <Badge variant="secondary" key={role.id}>
                                                        {role.nombre}
                                                    </Badge>
                                                ))
                                            ) : (
                                                <Badge variant="outline">Acceso pendiente</Badge>
                                            )}
                                        </div>
                                    </td>
                                    <td className="p-4">
                                        <Button variant="ghost" asChild className="mr-2">
                                            <Link href={route('usuarios.persona.edit', user.id)}>Vincular persona</Link>
                                        </Button>
                                        <Button
                                            variant="outline"
                                            onClick={() => {
                                                form.clearErrors();
                                                form.setData(
                                                    'roles',
                                                    user.roles.map((role) => role.codigo),
                                                );
                                                setSelected(user);
                                            }}
                                        >
                                            Asignar roles
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <div className="flex items-center justify-between gap-3">
                    <span className="text-muted-foreground text-sm">
                        Página {users.current_page} de {users.last_page}
                    </span>
                    <div className="flex gap-2">
                        {users.prev_page_url && (
                            <Button variant="outline" asChild>
                                <Link href={users.prev_page_url}>Anterior</Link>
                            </Button>
                        )}
                        {users.next_page_url && (
                            <Button variant="outline" asChild>
                                <Link href={users.next_page_url}>Siguiente</Link>
                            </Button>
                        )}
                    </div>
                </div>
                <section className="bg-card rounded-xl border p-5">
                    <h2 className="text-lg font-semibold">Módulos por rol</h2>
                    <div className="mt-4 flex flex-wrap gap-2">
                        {roles
                            .filter((role) => ['asesor', 'pasante'].includes(role.codigo))
                            .map((role) => (
                                <Button key={role.id} variant="outline" onClick={() => setEditingRole(role)}>
                                    Configurar módulos · {role.nombre}
                                </Button>
                            ))}
                    </div>
                    <p className="text-muted-foreground mt-2 text-sm">
                        El administrador tiene acceso completo. Habilita módulos para cada rol; las acciones se asignan automáticamente. El asesor
                        consulta personal asignado y el pasante su propia participación. Los módulos pendientes de implementación no aparecen en el
                        menú.
                    </p>
                    <div className="mt-5 overflow-x-auto">
                        <table className="w-full min-w-[640px] text-left text-sm">
                            <thead>
                                <tr>
                                    <th className="p-3" scope="col">
                                        Módulo
                                    </th>
                                    {roles.map((role) => (
                                        <th scope="col" className="p-3" key={role.id}>
                                            {role.nombre}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {Object.entries(modules).map(([code, module]) => (
                                    <tr className="border-t" key={code}>
                                        <th scope="row" className="p-3 font-medium">
                                            {module.nombre}
                                            <span className="text-muted-foreground mt-1 block text-xs font-normal">
                                                {['personal', 'acceso', 'catalogos'].includes(code) ? 'Disponible' : 'Pendiente de implementación'}
                                            </span>
                                        </th>
                                        {roles.map((role) => (
                                            <td key={role.id} className="text-muted-foreground p-3">
                                                {role.codigo === 'administrador'
                                                    ? 'Acceso completo'
                                                    : !roleModules[role.codigo]?.[code]
                                                      ? 'No disponible para este rol'
                                                      : role.permisos.some((permission) => permission.codigo === `${code}.ver`)
                                                        ? 'Habilitado'
                                                        : 'Sin acceso'}
                                            </td>
                                        ))}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
                <p className="text-muted-foreground text-sm">
                    Si una cuenta tiene varios roles, el inicio prioritario es administrador, asesor y después pasante. Puede acceder a sus otros
                    inicios desde el menú. Asignar un rol no crea una persona ni una adscripción.
                </p>
            </div>
            {editingRole && (
                <PermissionEditor
                    key={editingRole.id}
                    role={editingRole}
                    modules={modules}
                    roleModules={roleModules}
                    onClose={() => setEditingRole(null)}
                />
            )}
            <Dialog
                open={selected !== null}
                onOpenChange={(open) => {
                    if (!open && !form.processing) setSelected(null);
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Asignar roles</DialogTitle>
                        <DialogDescription>
                            {selected?.name} · {selected?.email}
                        </DialogDescription>
                    </DialogHeader>
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            if (selected) form.put(route('usuarios.roles.update', selected.id), { onSuccess: () => setSelected(null) });
                        }}
                        className="space-y-5"
                    >
                        <fieldset disabled={form.processing} className="space-y-3">
                            <legend className="mb-3 text-sm">Selecciona los roles de esta cuenta</legend>
                            {roles.map((role) => (
                                <label key={role.id} className="flex items-center gap-3 rounded-lg border p-3 text-sm">
                                    <input
                                        type="checkbox"
                                        checked={form.data.roles.includes(role.codigo)}
                                        disabled={selected?.id === auth.user.id && role.codigo === 'administrador'}
                                        onChange={(event) =>
                                            form.setData(
                                                'roles',
                                                event.target.checked
                                                    ? [...form.data.roles, role.codigo]
                                                    : form.data.roles.filter((code) => code !== role.codigo),
                                            )
                                        }
                                        className="size-4 accent-[#722f42]"
                                    />
                                    {role.nombre}
                                </label>
                            ))}
                        </fieldset>
                        <p className="text-muted-foreground text-xs">
                            Sin roles, la cuenta quedará con acceso pendiente. No puedes retirar tu propio rol de administrador.
                        </p>
                        {Object.values(form.errors).map((error) => (
                            <p key={error} role="alert" className="text-sm text-red-600">
                                {error}
                            </p>
                        ))}
                        <Button type="submit" disabled={form.processing || roles.length === 0}>
                            {form.processing ? 'Guardando…' : 'Guardar roles'}
                        </Button>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}

function PermissionEditor({
    role,
    modules,
    roleModules,
    onClose,
}: {
    role: Role;
    modules: Props['modules'];
    roleModules: Props['roleModules'];
    onClose: () => void;
}) {
    const available = roleModules[role.codigo] ?? {};
    const form = useForm<{ modulos: string[] }>({
        modulos: Object.keys(available).filter((code) => role.permisos.some((permission) => permission.codigo === `${code}.ver`)),
    });
    return (
        <Dialog
            open
            onOpenChange={(open) => {
                if (!open && !form.processing) onClose();
            }}
        >
            <DialogContent className="max-h-[85dvh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Módulos · {role.nombre}</DialogTitle>
                    <DialogDescription>
                        Los cambios se aplican a todas las cuentas con este rol. Las acciones permitidas se asignan al guardar.
                    </DialogDescription>
                </DialogHeader>
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.put(route('roles.permisos.update', role.id), { onSuccess: onClose });
                    }}
                    className="space-y-4"
                >
                    <p className="rounded-lg border p-3 text-sm">
                        Alcance: {role.codigo === 'asesor' ? 'personal asignado al asesor' : 'participación propia y materiales publicados'}. Guardar
                        sustituye las asignaciones anteriores por las acciones de los módulos seleccionados.
                    </p>
                    <fieldset disabled={form.processing} className="space-y-3">
                        <legend className="sr-only">Módulos disponibles</legend>
                        {Object.entries(available).map(([code, actions]) => (
                            <label key={code} className="flex items-start gap-3 rounded-lg border p-4">
                                <input
                                    type="checkbox"
                                    className="mt-1 size-4 accent-[#722f42]"
                                    checked={form.data.modulos.includes(code)}
                                    onChange={(event) =>
                                        form.setData(
                                            'modulos',
                                            event.target.checked ? [...form.data.modulos, code] : form.data.modulos.filter((value) => value !== code),
                                        )
                                    }
                                />
                                <span>
                                    <span className="block text-sm font-medium">{modules[code].nombre}</span>
                                    <span className="text-muted-foreground mt-1 block text-xs">Acciones del rol: {actions.join(', ')}.</span>
                                    <span className="text-muted-foreground mt-1 block text-xs">
                                        {code === 'personal'
                                            ? 'Consulta funcional con acceso restringido a sus registros.'
                                            : 'Pendiente de implementación: esta asignación se reserva para cuando el módulo esté disponible.'}
                                    </span>
                                </span>
                            </label>
                        ))}
                    </fieldset>
                    <p className="text-muted-foreground text-xs">
                        Sin módulos, el usuario conserva su inicio y su cuenta, pero no puede abrir los módulos de este rol.
                    </p>
                    {Object.values(form.errors).map((error) => (
                        <p role="alert" key={error} className="text-sm text-red-600">
                            {error}
                        </p>
                    ))}
                    <Button type="submit" disabled={form.processing}>
                        {form.processing ? 'Guardando…' : 'Guardar módulos del rol'}
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}
