import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';
import type { Promotion } from './promociones';

export default function PromocionDetail({
    promocion: p,
    carreras,
    participaciones,
    status,
}: {
    promocion: Promotion;
    carreras: { id_carrera: number; nombre: string }[];
    status?: string;
    participaciones: {
        data: {
            id_personal: number;
            nombre: string;
            ap: string;
            am: string | null;
            fecha_ingreso: string;
            fecha_termino: string | null;
            estado: string;
            motivo_periodo: string | null;
        }[];
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
}) {
    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Promociones', href: route('promociones.index') },
                { title: p.nombre, href: route('promociones.show', p.id_promocion) },
            ]}
        >
            <Head title={p.nombre} />
            <div className="mx-auto w-full max-w-6xl space-y-6 p-5 md:p-8">
                {status && (
                    <p role="status" className="rounded-xl border border-emerald-500/30 p-4">
                        {status}
                    </p>
                )}
                <header className="flex flex-wrap justify-between gap-4">
                    <div>
                        <p className="text-primary text-sm">{p.estado}</p>
                        <h1 className="text-2xl font-semibold">{p.nombre}</h1>
                        <p className="text-muted-foreground mt-2">
                            Periodo programado: {p.fecha_inicio} — {p.fecha_fin}
                        </p>
                    </div>
                    <Button variant="outline" asChild>
                        <Link href={route('promociones.edit', p.id_promocion)}>Editar promoción</Link>
                    </Button>
                </header>
                <section className="bg-card space-y-3 rounded-xl border p-5">
                    <h2 className="font-semibold">Carreras participantes</h2>
                    <div className="flex flex-wrap gap-2">
                        {carreras.map((c) => (
                            <span key={c.id_carrera} className="bg-muted rounded-full px-3 py-1 text-sm">
                                {c.nombre}
                            </span>
                        ))}
                    </div>
                    <p className="text-muted-foreground text-sm">
                        {p.permite_inscripciones
                            ? 'Se permiten nuevas incorporaciones.'
                            : 'No se permiten nuevas incorporaciones. Los registros existentes pueden seguir atendiéndose.'}
                    </p>
                </section>
                <nav aria-label="Información de la promoción" className="flex flex-wrap gap-3">
                    {[
                        ['personal.index', 'Personal'],
                        ['expedientes.index', 'Expedientes'],
                        ['incidencias.index', 'Incidencias y oficios'],
                    ].map(([name, title]) => (
                        <Button key={name} variant="outline" asChild>
                            <Link href={route(name, { promocion: p.id_promocion })}>{title}</Link>
                        </Button>
                    ))}
                    {p.permite_inscripciones && p.estado !== 'Cerrada' && (
                        <Button asChild>
                            <Link href={route('personal.create', { promocion: p.id_promocion })}>Registrar pasante</Link>
                        </Button>
                    )}
                </nav>
                <section className="space-y-4">
                    <h2 className="text-lg font-semibold">Participantes · {participaciones.total}</h2>
                    <p className="text-muted-foreground text-sm">
                        Una fecha individual posterior al término programado se muestra como periodo extendido. No cambia la promoción ni acredita una
                        liberación.
                    </p>
                    <div className="grid gap-4 md:grid-cols-2">
                        {participaciones.data.map((person) => (
                            <article key={person.id_personal} className="bg-card space-y-3 rounded-xl border p-5">
                                <h3 className="font-semibold">{[person.nombre, person.ap, person.am].filter(Boolean).join(' ')}</h3>
                                <p className="text-sm">
                                    {person.fecha_ingreso} — {person.fecha_termino ?? 'Sin término'} · {person.estado}
                                </p>
                                {person.fecha_termino && person.fecha_termino > p.fecha_fin && (
                                    <span className="inline-block rounded-full bg-amber-500/15 px-3 py-1 text-xs">Periodo extendido</span>
                                )}
                                {person.motivo_periodo && <p className="text-muted-foreground text-sm">{person.motivo_periodo}</p>}
                                <div className="flex flex-wrap gap-2">
                                    <Button variant="outline" asChild>
                                        <Link href={route('personal.show', person.id_personal)}>Ver participación</Link>
                                    </Button>
                                    <Button variant="outline" asChild>
                                        <Link href={route('personal.registro.edit', person.id_personal)}>Ajustar periodo</Link>
                                    </Button>
                                </div>
                            </article>
                        ))}
                    </div>
                    {!participaciones.data.length && (
                        <p className="rounded-xl border border-dashed p-6">
                            Aún no hay pasantes vinculados. Puedes registrar uno nuevo o asignar esta promoción al editar una participación existente
                            sin promoción.
                        </p>
                    )}
                    <div className="flex gap-3">
                        {participaciones.prev_page_url && (
                            <Link className="underline" href={participaciones.prev_page_url}>
                                Anterior
                            </Link>
                        )}
                        {participaciones.next_page_url && (
                            <Link className="underline" href={participaciones.next_page_url}>
                                Siguiente
                            </Link>
                        )}
                    </div>
                </section>
            </div>
        </AppLayout>
    );
}
