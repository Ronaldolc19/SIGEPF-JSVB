import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';

type Documento = {
    id_documento: number;
    titulo: string | null;
    nombre_archivo: string;
    version: number;
    estado: string;
    created_at: string;
    nombre: string;
    ap: string;
    am: string | null;
    tipo: string;
};
export default function Academico({
    documentos,
    filters,
    canUpload,
    isAdmin,
    status,
}: {
    documentos: { data: Documento[]; total: number; prev_page_url: string | null; next_page_url: string | null };
    filters: { buscar?: string; estado?: string };
    canUpload: boolean;
    isAdmin: boolean;
    status?: string;
}) {
    const form = useForm({ buscar: filters.buscar ?? '', estado: filters.estado ?? '' });
    return (
        <AppLayout breadcrumbs={[{ title: 'Repositorio académico', href: route('academico.index') }]}>
            <Head title="Repositorio académico" />
            <div className="mx-auto w-full max-w-6xl space-y-6 p-6">
                <header className="flex flex-wrap justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">Repositorio académico</h1>
                        <p className="text-muted-foreground mt-2">Entrega de trabajos, observaciones e historial de versiones.</p>
                    </div>
                    <div className="flex flex-wrap gap-3">
                        {isAdmin && (
                            <Button variant="outline" asChild>
                                <Link href={route('catalogos.show', 'tipos_documentos')}>Tipos de documentos</Link>
                            </Button>
                        )}
                        {canUpload && (
                            <Button asChild>
                                <Link href={route('academico.create')}>Entregar documento</Link>
                            </Button>
                        )}
                    </div>
                </header>
                {status && (
                    <p role="status" className="bg-muted rounded-lg p-4">
                        {status}
                    </p>
                )}
                <form
                    className="flex flex-wrap items-end gap-3 rounded-xl border p-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.get(route('academico.index'));
                    }}
                >
                    <label className="min-w-56 flex-1">
                        Buscar
                        <Input
                            maxLength={100}
                            placeholder="Apellidos, nombre, título o tipo"
                            value={form.data.buscar}
                            onChange={(e) => form.setData('buscar', e.target.value)}
                        />
                    </label>
                    <label>
                        Estado
                        <select
                            className="bg-background block rounded-md border p-2"
                            value={form.data.estado}
                            onChange={(e) => form.setData('estado', e.target.value)}
                        >
                            <option value="">Todos</option>
                            {['Pendiente de revisión', 'Por corregir', 'Aprobado'].map((s) => (
                                <option key={s}>{s}</option>
                            ))}
                        </select>
                    </label>
                    <Button disabled={form.processing}>Buscar</Button>
                </form>
                <p className="text-muted-foreground text-sm">
                    {documentos.total} trabajos. Se muestra la última versión de cada entrega en el periodo seleccionado.
                </p>
                {!documentos.data.length && (
                    <div className="rounded-xl border p-8">
                        <h2 className="font-semibold">No hay documentos para esta consulta</h2>
                        <p className="text-muted-foreground mt-2">
                            {canUpload
                                ? 'Entrega tu primer documento o revisa el periodo seleccionado en el calendario.'
                                : 'Las entregas aparecerán aquí cuando los pasantes suban sus documentos. También puedes revisar la búsqueda y el calendario.'}
                        </p>
                    </div>
                )}
                <div className="grid gap-4 md:grid-cols-2">
                    {documentos.data.map((d) => (
                        <article className="bg-card space-y-3 rounded-xl border p-5" key={d.id_documento}>
                            <p className="text-muted-foreground text-sm">{[d.ap, d.am, d.nombre].filter(Boolean).join(' ')}</p>
                            <h2 className="font-semibold">{d.titulo ?? d.nombre_archivo}</h2>
                            <p className="text-sm">
                                {d.tipo} · Versión {d.version}
                            </p>
                            <span className="bg-muted inline-block rounded-full px-3 py-1 text-sm">{d.estado}</span>
                            <div>
                                <Button asChild variant="outline">
                                    <Link href={route('academico.show', d.id_documento)}>Ver documento y revisión</Link>
                                </Button>
                            </div>
                        </article>
                    ))}
                </div>
                <nav aria-label="Paginación" className="flex gap-4">
                    {documentos.prev_page_url && <Link href={documentos.prev_page_url}>Anterior</Link>}
                    {documentos.next_page_url && <Link href={documentos.next_page_url}>Siguiente</Link>}
                </nav>
            </div>
        </AppLayout>
    );
}
