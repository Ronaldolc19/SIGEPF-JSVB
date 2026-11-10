import { EmptyState, PageContainer, PageHeader } from '@/components/page-frame';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { adminModules } from '@/lib/admin-navigation';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight, ClipboardList, UserPlus, Users } from 'lucide-react';
import { useState } from 'react';

const moduleRoutes: Record<string, string> = {
    promociones: 'promociones.index',
    consulta: 'consulta.index',
    incidencias: 'incidencias.index',
    expedientes: 'expedientes.index',
    personas: 'personas.index',
    personal: 'personal.index',
    acceso: 'usuarios.index',
    catalogos: 'catalogos.index',
};
const normalize = (value: string) =>
    value
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim();

export default function Dashboard() {
    const { auth } = usePage<SharedData>().props;
    const [query, setQuery] = useState('');
    const modules = adminModules.filter((module) => normalize(`${module.title} ${module.description}`).includes(normalize(query)));
    const actions = [
        { title: 'Crear cuenta', routeName: 'usuarios.create', icon: UserPlus },
        { title: 'Registrar persona', routeName: 'personas.create', icon: Users },
        { title: 'Consultar personal', routeName: 'personal.index', icon: ClipboardList },
    ];
    return (
        <AppLayout breadcrumbs={[{ title: 'Inicio', href: route('administrador.dashboard') }]}>
            <Head title="Inicio · Enseñanza" />
            <PageContainer>
                <PageHeader
                    institutional
                    title="Panel de Enseñanza"
                    eyebrow="Jurisdicción Sanitaria 09 · Valle de Bravo"
                    description={`Hola, ${auth.user.name}. Administra las cuentas, los datos personales y los catálogos del sistema.`}
                />
                <section className="space-y-4" aria-labelledby="quick-actions-title">
                    <h2 id="quick-actions-title" className="text-lg font-semibold">
                        Accesos frecuentes
                    </h2>
                    <div className="grid gap-3 md:grid-cols-3">
                        {actions.map((action) => (
                            <Button key={action.routeName} variant="outline" className="h-auto justify-start gap-3 p-5" asChild>
                                <Link href={route(action.routeName)}>
                                    <action.icon className="size-5" />
                                    {action.title}
                                </Link>
                            </Button>
                        ))}
                    </div>
                </section>
                <section className="space-y-4" aria-labelledby="modules-title">
                    <h2 id="modules-title" className="text-lg font-semibold">
                        Módulos de trabajo
                    </h2>
                    <div className="space-y-2">
                        <label htmlFor="module-search" className="text-sm font-medium">
                            Buscar módulo
                        </label>
                        <Input
                            id="module-search"
                            type="search"
                            maxLength={100}
                            placeholder="Buscar personas, usuarios o catálogos"
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                        />
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        {modules.map((module) => (
                            <Link
                                key={module.id}
                                href={route(moduleRoutes[module.id])}
                                className="bg-card hover:border-primary focus-visible:ring-ring rounded-xl border p-5 transition focus-visible:ring-2 focus-visible:outline-none"
                            >
                                <module.icon className="text-primary size-6" />
                                <h3 className="mt-3 font-semibold">{module.title}</h3>
                                <p className="text-muted-foreground mt-2 text-sm">{module.description}</p>
                                <span className="text-primary mt-4 flex items-center gap-2 text-sm">
                                    Abrir módulo <ArrowRight className="size-4" />
                                </span>
                            </Link>
                        ))}
                    </div>
                    {!modules.length && (
                        <EmptyState
                            title="No encontramos ese módulo"
                            description="Prueba otro nombre."
                            action={
                                <Button variant="outline" onClick={() => setQuery('')}>
                                    Ver todos los módulos
                                </Button>
                            }
                        />
                    )}
                </section>
                <section className="bg-muted/30 rounded-xl border p-5">
                    <h2 className="font-semibold">Incorporar una persona</h2>
                    <ol className="mt-3 list-decimal space-y-2 pl-5 text-sm">
                        <li>Crea la cuenta y asigna su rol.</li>
                        <li>Registra a la persona como asesor o personal en formación.</li>
                        <li>Vincula la cuenta con su persona y habilita los módulos del rol.</li>
                    </ol>
                </section>
            </PageContainer>
        </AppLayout>
    );
}
