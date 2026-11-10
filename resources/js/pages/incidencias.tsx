import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';

type Item = {
    id_incidencia: number;
    nombre: string;
    ap: string;
    am: string | null;
    tipo: string;
    fecha_hecho: string;
    fecha_fin: string | null;
    estado: string;
    oficios: number;
};
export default function Incidencias({
    incidencias,
    filters,
    estados,
    canCreate,
}: {
    incidencias: { data: Item[]; prev_page_url: string | null; next_page_url: string | null };
    filters: { buscar?: string; estado?: string };
    estados: string[];
    canCreate: boolean;
}) {
    const form = useForm({ buscar: filters.buscar ?? '', estado: filters.estado ?? '' });
    return (
        <AppLayout breadcrumbs={[{ title: 'Incidencias y oficios', href: route('incidencias.index') }]}>
            <Head title="Incidencias y oficios" />
            <div className="mx-auto w-full max-w-6xl space-y-6 p-5 md:p-8">
                <header className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">Incidencias y oficios</h1>
                        <p className="text-muted-foreground mt-2">Consulta el seguimiento y descarga los oficios disponibles en cada incidencia.</p>
                    </div>
                    {canCreate && (
                        <Button asChild>
                            <Link href={route('incidencias.create')}>Registrar incidencia</Link>
                        </Button>
                    )}
                </header>
                <form
                    className="flex flex-wrap gap-3"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.get(route('incidencias.index'), { preserveState: true, replace: true });
                    }}
                >
                    <Input
                        aria-label="Buscar por pasante o tipo"
                        placeholder="Buscar por pasante o tipo"
                        className="max-w-md"
                        value={form.data.buscar}
                        maxLength={100}
                        onChange={(event) => form.setData('buscar', event.target.value)}
                    />
                    <select
                        aria-label="Filtrar por estado"
                        className="bg-background rounded-md border p-2"
                        value={form.data.estado}
                        onChange={(event) => form.setData('estado', event.target.value)}
                    >
                        <option value="">Todos los estados</option>
                        {estados.map((state) => (
                            <option key={state}>{state}</option>
                        ))}
                    </select>
                    <Button disabled={form.processing}>Buscar</Button>
                </form>
                {!incidencias.data.length && (
                    <p role="status" className="rounded-xl border p-6">
                        No hay incidencias disponibles con estos filtros. Si eres pasante, solo aparecen las de tus participaciones vinculadas.
                    </p>
                )}
                <div className="grid gap-4 md:grid-cols-2">
                    {incidencias.data.map((item) => (
                        <article key={item.id_incidencia} className="bg-card space-y-3 rounded-xl border p-5">
                            <p className="text-muted-foreground text-xs">
                                Incidencia #{item.id_incidencia} · {item.estado}
                            </p>
                            <h2 className="font-semibold">{item.tipo}</h2>
                            <p>{[item.nombre, item.ap, item.am].filter(Boolean).join(' ')}</p>
                            <p className="text-muted-foreground text-sm">
                                {item.fecha_hecho} — {item.fecha_fin ?? item.fecha_hecho}
                            </p>
                            <p className="text-sm">{item.oficios} oficio(s) disponible(s)</p>
                            <Button variant="outline" asChild>
                                <Link href={route('incidencias.show', item.id_incidencia)}>Ver seguimiento y oficios</Link>
                            </Button>
                        </article>
                    ))}
                </div>
                <nav className="flex gap-4" aria-label="Paginación">
                    {incidencias.prev_page_url && <Link href={incidencias.prev_page_url}>Anterior</Link>}
                    {incidencias.next_page_url && <Link href={incidencias.next_page_url}>Siguiente</Link>}
                </nav>
            </div>
        </AppLayout>
    );
}
