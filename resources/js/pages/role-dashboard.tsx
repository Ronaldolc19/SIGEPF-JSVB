import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { Users } from 'lucide-react';

const roleLabels = { asesor: 'Asesor', pasante: 'Personal en formación' };

export default function RoleDashboard({ role }: { role: 'asesor' | 'pasante' | null }) {
    const { auth } = usePage<SharedData>().props;
    if (role) {
        const modules = ['personal', 'expedientes', 'incidencias', 'consulta'].filter((module) => auth.modulesByRole[role]?.includes(module));
        return (
            <AppLayout breadcrumbs={[{ title: 'Inicio', href: route(`${role}.dashboard`) }]}>
                <Head title={`Inicio · ${roleLabels[role]}`} />
                <div className="mx-auto w-full max-w-6xl space-y-6 p-6">
                    <div>
                        <h1 className="text-2xl font-semibold">{roleLabels[role]}</h1>
                        <p className="text-muted-foreground mt-2">Bienvenido, {auth.user.name}. Estos son los módulos habilitados para tu rol.</p>
                    </div>
                    {modules.length === 0 && (
                        <div role="status" className="rounded-xl border p-5">
                            <h2 className="font-semibold">Sin módulos operativos habilitados</h2>
                            <p className="text-muted-foreground mt-2 text-sm">
                                Tu rol está asignado. Por ahora no tienes acceso a funciones operativas. Consulta al administrador.
                            </p>
                        </div>
                    )}
                    <div className="grid gap-4 md:grid-cols-2">
                        {modules.map((module) => {
                            const Icon = Users;
                            return (
                                <Link
                                    key={module}
                                    href={route(
                                        module === 'consulta'
                                            ? 'consulta.index'
                                            : module === 'incidencias'
                                              ? 'incidencias.index'
                                              : module === 'expedientes'
                                                ? 'expedientes.index'
                                                : 'personal.index',
                                    )}
                                    className="bg-card hover:border-primary rounded-xl border p-5 transition focus-visible:outline-2 focus-visible:outline-offset-2"
                                >
                                    <Icon className="text-primary size-6" />
                                    <h2 className="mt-3 font-semibold">
                                        {module === 'consulta'
                                            ? 'Portal de consulta'
                                            : module === 'incidencias'
                                              ? 'Incidencias y oficios'
                                              : module === 'expedientes'
                                                ? 'Mi expediente digital'
                                                : role === 'asesor'
                                                  ? 'Personal asignado'
                                                  : 'Mi participación'}
                                    </h2>
                                    <p className="text-muted-foreground mt-2 text-sm">
                                        {module === 'consulta'
                                            ? 'Consulta y descarga guías, formatos y materiales de Enseñanza.'
                                            : module === 'incidencias'
                                              ? 'Consulta el seguimiento de incidencias y los oficios disponibles.'
                                              : module === 'expedientes'
                                                ? 'Sube tus documentos y consulta el estado de tus entregas.'
                                                : 'Consulta las participaciones registradas a las que tienes acceso.'}
                                    </p>
                                    <p className="mt-3 text-xs font-medium">Abrir módulo</p>
                                </Link>
                            );
                        })}
                    </div>
                    <p className="text-muted-foreground text-sm">
                        {role === 'asesor'
                            ? 'La consulta de personal se limita a tus asignaciones vigentes.'
                            : 'La consulta de personal se limita a tus propias participaciones.'}
                    </p>
                </div>
            </AppLayout>
        );
    }
    return (
        <AppLayout breadcrumbs={[{ title: 'Inicio', href: route('dashboard') }]}>
            <Head title="Acceso pendiente" />
            <div className="mx-auto w-full max-w-3xl space-y-5 p-6">
                <h1 className="text-2xl font-semibold">Acceso pendiente</h1>
                <p>Tu cuenta está registrada. Un administrador debe asignarte un rol para habilitar tu inicio.</p>
                <p className="text-muted-foreground text-sm">Comunica al administrador el correo de tu cuenta: {auth.user.email}.</p>
                <Button variant="outline" asChild>
                    <Link href={route('profile.edit')}>Mi cuenta</Link>
                </Button>
            </div>
        </AppLayout>
    );
}
