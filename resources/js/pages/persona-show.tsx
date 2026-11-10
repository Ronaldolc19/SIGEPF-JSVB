import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';
import { personaFields, type Persona } from './persona-form';

export default function PersonaShow({
    persona,
    labels,
    usuario,
    status,
    perfiles = [],
    participaciones = [],
    edad,
    residencia,
}: {
    persona: Persona;
    labels: Record<string, { label: string; context?: string } | null>;
    usuario: { id: number; email: string } | null;
    status?: string;
    perfiles?: string[];
    participaciones?: { id_personal: number; estado: string }[];
    edad?: number | null;
    residencia?: { municipio: string | null; entidad: string | null } | null;
}) {
    const name = [persona.nombre, persona.ap, persona.am].filter(Boolean).join(' ');
    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Personas', href: route('personas.index') },
                { title: 'Detalle', href: route('personas.show', persona.id_persona) },
            ]}
        >
            <Head title={name} />
            <div className="mx-auto w-full max-w-4xl space-y-6 p-5 md:p-8">
                {status && (
                    <p role="status" className="rounded-lg bg-emerald-50 p-4 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-100">
                        {status}
                    </p>
                )}
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <h1 className="text-2xl font-semibold break-words">{name}</h1>
                    <Button asChild>
                        <Link href={route('personas.edit', persona.id_persona)}>Editar datos</Link>
                    </Button>
                </div>
                <dl className="grid gap-6 rounded-xl border p-5 sm:grid-cols-2">
                    <div>
                        <dt className="text-muted-foreground text-sm">Entidad del prestador (constancia)</dt>
                        <dd>{labels.id_entidad_federativa?.label || 'Sin registrar'}</dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground text-sm">Edad actual</dt>
                        <dd>{edad ?? 'Sin fecha de nacimiento'}</dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground text-sm">Municipio y entidad de residencia</dt>
                        <dd>{[residencia?.municipio, residencia?.entidad].filter(Boolean).join(' · ') || 'Sin registrar'}</dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground text-sm">Perfil registrado</dt>
                        <dd className="mt-1">{perfiles.length ? perfiles.join(' / ') : 'Sin asignar'}</dd>
                    </div>
                    {personaFields.map(([field, label]) => (
                        <div key={field}>
                            <dt className="text-muted-foreground text-sm">{label}</dt>
                            <dd className="mt-1 break-words">{persona[field] || 'Sin registrar'}</dd>
                        </div>
                    ))}
                    {Object.entries({ id_genero: 'Género', id_nacionalidad: 'Nacionalidad', id_localidad: 'Localidad de residencia' }).map(
                        ([field, label]) => (
                            <div key={field}>
                                <dt className="text-muted-foreground text-sm">{label}</dt>
                                <dd className="mt-1">
                                    {labels[field] ? [labels[field]?.label, labels[field]?.context].filter(Boolean).join(' · ') : 'Sin registrar'}
                                </dd>
                            </div>
                        ),
                    )}
                </dl>
                {!!participaciones.length && (
                    <section className="space-y-3 rounded-xl border p-5">
                        <h2 className="font-semibold">Participaciones de personal en formación</h2>
                        <p className="text-muted-foreground text-sm">Completa aquí los datos académicos y la adscripción de cada participación.</p>
                        <div className="flex flex-wrap gap-3">
                            {participaciones.map((item) => (
                                <Button key={item.id_personal} variant="outline" asChild>
                                    <Link href={route('personal.show', item.id_personal)}>
                                        Participación #{item.id_personal} · {item.estado}
                                    </Link>
                                </Button>
                            ))}
                        </div>
                    </section>
                )}
                {!participaciones.length && !perfiles.includes('Asesor') && (
                    <Button asChild>
                        <Link href={route('personal.create', { persona: persona.id_persona })}>Registrar como personal en formación</Link>
                    </Button>
                )}
                <section className="space-y-3 rounded-xl border p-5">
                    <h2 className="font-semibold">Cuenta de acceso</h2>
                    <p className="text-muted-foreground text-sm">
                        {usuario
                            ? `Cuenta vinculada: ${usuario.email}`
                            : 'Esta persona aún no tiene una cuenta vinculada. Selecciona la cuenta correspondiente en Usuarios y permisos para asociarla.'}
                    </p>
                    <Button variant="outline" asChild>
                        <Link href={usuario ? route('usuarios.persona.edit', usuario.id) : route('usuarios.index')}>
                            {usuario ? 'Administrar vínculo' : 'Ir a usuarios y permisos'}
                        </Link>
                    </Button>
                </section>
            </div>
        </AppLayout>
    );
}
