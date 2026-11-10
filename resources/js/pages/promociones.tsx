import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { CalendarDays, Plus, Users } from 'lucide-react';

export type Promotion = {
    id_promocion: number;
    nombre: string;
    fecha_inicio: string;
    fecha_fin: string;
    estado: string;
    permite_inscripciones: boolean;
    participantes?: number;
    revision: number;
};
const months = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
export default function Promociones({
    promociones,
    calendario,
    filters,
    sinPromocion,
    periodosExtendidos,
    status,
}: {
    promociones: { data: Promotion[]; total: number; prev_page_url: string | null; next_page_url: string | null };
    calendario: Promotion[];
    filters: { estado: string; anio: number };
    sinPromocion: number;
    periodosExtendidos: number;
    status?: string;
}) {
    const form = useForm(filters);
    return (
        <AppLayout breadcrumbs={[{ title: 'Promociones', href: route('promociones.index') }]}>
            <Head title="Promociones de servicio" />
            <div className="mx-auto w-full max-w-6xl space-y-6 p-5 md:p-8">
                <header className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <p className="text-primary text-sm font-medium">Organización del servicio</p>
                        <h1 className="mt-1 text-2xl font-semibold">Promociones</h1>
                        <p className="text-muted-foreground mt-2">Administra los periodos de ingreso y consulta el historial de cada generación.</p>
                    </div>
                    <Button asChild>
                        <Link href={route('promociones.create')}>
                            <Plus className="size-4" />
                            Nueva promoción
                        </Link>
                    </Button>
                </header>
                {status && (
                    <p role="status" className="rounded-xl border border-emerald-500/30 p-4">
                        {status}
                    </p>
                )}
                {sinPromocion > 0 && (
                    <aside className="rounded-xl border border-amber-500/40 bg-amber-500/5 p-4 text-sm">
                        <strong>{sinPromocion} participaciones sin promoción.</strong> Revisa sus constancias y vincúlalas al editar el registro
                        completo.{' '}
                        <Link className="underline" href={route('personal.index', { promocion: 'sin' })}>
                            Revisar pendientes
                        </Link>
                    </aside>
                )}
                {periodosExtendidos > 0 && (
                    <aside className="border-primary/30 rounded-xl border p-4 text-sm">
                        <strong>{periodosExtendidos} periodos extendidos en formación.</strong> Se mantienen en seguimiento aunque su promoción esté
                        cerrada.{' '}
                        <Link className="underline" href={route('personal.index', { promocion: 'prorrogas' })}>
                            Consultar
                        </Link>
                    </aside>
                )}
                <form
                    className="flex flex-wrap items-end gap-3"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.get(route('promociones.index'), { replace: true });
                    }}
                >
                    <label className="space-y-2 text-sm">
                        Mostrar promociones
                        <select
                            className="bg-background block rounded-md border p-2"
                            value={form.data.estado}
                            onChange={(e) => form.setData('estado', e.target.value)}
                        >
                            {['En curso', 'Programada', 'Cerrada', 'Todas'].map((s) => (
                                <option key={s}>{s}</option>
                            ))}
                        </select>
                    </label>
                    <label className="space-y-2 text-sm">
                        Año del calendario
                        <Input
                            className="w-32"
                            type="number"
                            min={2000}
                            max={2100}
                            value={form.data.anio}
                            onChange={(e) => form.setData('anio', Number(e.target.value))}
                        />
                    </label>
                    <Button variant="outline" disabled={form.processing}>
                        Aplicar filtros
                    </Button>
                    {Object.values(form.errors).map((error) => (
                        <p key={error} role="alert" className="text-destructive text-sm">
                            {error}
                        </p>
                    ))}
                </form>
                <section className="bg-card space-y-4 rounded-xl border p-5">
                    <h2 className="flex items-center gap-2 font-semibold">
                        <CalendarDays className="size-5" />
                        Calendario anual · {filters.anio}
                    </h2>
                    <p className="text-muted-foreground text-sm">
                        Periodos programados de todas las promociones que coinciden con este año. Las prórrogas individuales se consultan en cada
                        participación.
                    </p>
                    <div className="overflow-x-auto">
                        <div className="min-w-[720px] space-y-3">
                            <div className="text-muted-foreground grid grid-cols-12 gap-1 text-center text-xs">
                                {months.map((m) => (
                                    <span key={m}>{m}</span>
                                ))}
                            </div>
                            {calendario.map((p) => {
                                const start = p.fecha_inicio < `${filters.anio}-01-01` ? 1 : Number(p.fecha_inicio.slice(5, 7));
                                const end = p.fecha_fin > `${filters.anio}-12-31` ? 12 : Number(p.fecha_fin.slice(5, 7));
                                return (
                                    <div key={p.id_promocion} className="space-y-1">
                                        <Link
                                            className="text-sm font-medium underline-offset-4 hover:underline"
                                            href={route('promociones.show', p.id_promocion)}
                                        >
                                            {p.nombre} · {p.fecha_inicio} — {p.fecha_fin}
                                        </Link>
                                        <div className="bg-muted grid grid-cols-12 rounded-md">
                                            <Link
                                                aria-label={`Abrir ${p.nombre}, ${p.estado}`}
                                                href={route('promociones.show', p.id_promocion)}
                                                style={{ gridColumn: `${start} / ${end + 1}` }}
                                                className={
                                                    'truncate rounded-md px-3 py-2 text-xs font-medium ' +
                                                    (p.estado === 'Cerrada'
                                                        ? 'bg-muted-foreground/20'
                                                        : p.estado === 'En curso'
                                                          ? 'bg-primary text-primary-foreground'
                                                          : 'bg-amber-500/20')
                                                }
                                            >
                                                {p.estado}
                                            </Link>
                                        </div>
                                    </div>
                                );
                            })}
                            {!calendario.length && <p className="text-muted-foreground p-5 text-sm">No hay promociones registradas para este año.</p>}
                        </div>
                    </div>
                </section>
                <section className="space-y-4">
                    <h2 className="text-lg font-semibold">
                        {filters.estado} · {promociones.total} promociones
                    </h2>
                    <div className="grid gap-4 md:grid-cols-2">
                        {promociones.data.map((p) => (
                            <article key={p.id_promocion} className="bg-card space-y-4 rounded-xl border p-5">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <h3 className="font-semibold">{p.nombre}</h3>
                                    <span className="bg-muted rounded-full px-3 py-1 text-xs">{p.estado}</span>
                                </div>
                                <p className="text-muted-foreground text-sm">
                                    {p.fecha_inicio} — {p.fecha_fin}
                                </p>
                                <p className="flex items-center gap-2 text-sm">
                                    <Users className="size-4" />
                                    {p.participantes} participantes · {p.permite_inscripciones ? 'Admite incorporaciones' : 'Inscripciones cerradas'}
                                </p>
                                <div className="flex gap-2">
                                    <Button asChild>
                                        <Link href={route('promociones.show', p.id_promocion)}>Ver promoción</Link>
                                    </Button>
                                    <Button variant="outline" asChild>
                                        <Link href={route('promociones.edit', p.id_promocion)}>Editar</Link>
                                    </Button>
                                </div>
                            </article>
                        ))}
                    </div>
                    {!promociones.data.length && (
                        <p className="text-muted-foreground rounded-xl border border-dashed p-8 text-center">
                            No hay promociones con este estado. Puedes crear una o consultar el historial.
                        </p>
                    )}
                    <div className="flex gap-3">
                        {promociones.prev_page_url && (
                            <Link className="underline" href={promociones.prev_page_url}>
                                Anterior
                            </Link>
                        )}
                        {promociones.next_page_url && (
                            <Link className="underline" href={promociones.next_page_url}>
                                Siguiente
                            </Link>
                        )}
                    </div>
                </section>
            </div>
        </AppLayout>
    );
}
