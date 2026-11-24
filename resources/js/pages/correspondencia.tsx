import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';

type Item = {
    id_correspondencia: number;
    asunto: string;
    numero_oficio: string | null;
    remitente: string;
    direccion: string;
    tipo: string;
    estado: string;
    fecha_documento: string;
    fecha_limite: string | null;
};
export default function Correspondencia({
    documentos,
    filters,
    isAdmin,
    hoy,
}: {
    documentos: { data: Item[]; total: number; prev_page_url: string | null; next_page_url: string | null };
    filters: { buscar?: string; estado?: string; vencidas?: boolean };
    isAdmin: boolean;
    hoy: string;
}) {
    const form = useForm({ buscar: filters.buscar ?? '', estado: filters.estado ?? '', vencidas: filters.vencidas ? '1' : '' });
    return (
        <AppLayout breadcrumbs={[{ title: 'Correspondencia', href: route('correspondencia.index') }]}>
            <Head title="Correspondencia institucional" />
            <div className="mx-auto w-full max-w-6xl space-y-6 p-6">
                <header className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">Correspondencia institucional</h1>
                        <p className="text-muted-foreground">
                            {isAdmin ? 'Entrada, salida y seguimiento de documentos.' : 'Documentos dirigidos a tus participaciones.'}
                        </p>
                    </div>
                    {isAdmin && (
                        <Button asChild>
                            <Link href={route('correspondencia.create')}>Registrar documento</Link>
                        </Button>
                    )}
                </header>
                <form
                    className="flex flex-wrap items-end gap-3 rounded-xl border p-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.get(route('correspondencia.index'));
                    }}
                >
                    <label className="space-y-2">
                        Buscar
                        <Input
                            value={form.data.buscar}
                            onChange={(e) => form.setData('buscar', e.target.value)}
                            placeholder="Asunto, oficio o remitente"
                        />
                    </label>
                    <label className="space-y-2">
                        Estado
                        <select
                            className="bg-background block rounded-md border p-2"
                            value={form.data.estado}
                            onChange={(e) => form.setData('estado', e.target.value)}
                        >
                            <option value="">Todos</option>
                            {['Borrador', 'En seguimiento', 'Atendida', 'Cancelada']
                                .filter((s) => isAdmin || s !== 'Borrador')
                                .map((s) => (
                                    <option key={s}>{s}</option>
                                ))}
                        </select>
                    </label>
                    <label className="flex items-center gap-2 py-2">
                        <input
                            type="checkbox"
                            checked={!!form.data.vencidas}
                            onChange={(e) => form.setData('vencidas', e.target.checked ? '1' : '')}
                        />
                        Plazo vencido
                    </label>
                    <Button disabled={form.processing}>Buscar</Button>
                </form>
                <p className="text-muted-foreground text-sm">{documentos.total} registros en el periodo seleccionado en el calendario.</p>
                {!documentos.data.length && <p className="rounded-xl border p-8">No hay documentos para esta selección.</p>}
                <div className="space-y-3">
                    {documentos.data.map((d) => (
                        <Link
                            className="bg-card hover:border-primary block space-y-2 rounded-xl border p-5"
                            key={d.id_correspondencia}
                            href={route('correspondencia.show', d.id_correspondencia)}
                        >
                            <div className="flex flex-wrap justify-between gap-2">
                                <span className="text-muted-foreground text-sm">
                                    Registro interno COR-{d.id_correspondencia} · {d.tipo} · {d.direccion}
                                </span>
                                <span className="bg-muted rounded-full px-3 py-1 text-sm">{d.estado}</span>
                            </div>
                            <h2 className="font-semibold">{d.asunto}</h2>
                            <p className="text-sm">
                                Oficio: {d.numero_oficio || 'Sin número'} · {d.remitente} · {d.fecha_documento}
                            </p>
                            {d.fecha_limite && (
                                <p className={d.estado === 'En seguimiento' && d.fecha_limite < hoy ? 'text-destructive text-sm' : 'text-sm'}>
                                    Fecha límite: {d.fecha_limite}
                                </p>
                            )}
                        </Link>
                    ))}
                </div>
                <nav className="flex gap-4" aria-label="Paginación">
                    {documentos.prev_page_url && <Link href={documentos.prev_page_url}>Anterior</Link>}
                    {documentos.next_page_url && <Link href={documentos.next_page_url}>Siguiente</Link>}
                </nav>
            </div>
        </AppLayout>
    );
}
