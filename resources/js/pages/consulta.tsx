import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { BookOpen, Download, FileText, Plus } from 'lucide-react';

export type CatalogOption = { id: number; nombre: string };
type Material = {
    id_material: number;
    titulo: string;
    descripcion: string | null;
    nombre_archivo: string;
    estado: string;
    fecha_publicacion: string | null;
    tipo: string | null;
    carrera: string | null;
    tipo_personal: string | null;
};
type Props = {
    materiales: { data: Material[]; total: number; prev_page_url: string | null; next_page_url: string | null };
    tipos: CatalogOption[];
    carreras: CatalogOption[];
    personalTipos: CatalogOption[];
    filters: Record<string, string | number | null>;
    isAdmin: boolean;
    linked: boolean;
    estados: string[];
    status?: string;
};
export default function Consulta({ materiales, tipos, carreras, personalTipos, filters, isAdmin, linked, estados, status }: Props) {
    const form = useForm({
        buscar: String(filters.buscar ?? ''),
        id_tipo_documento: String(filters.id_tipo_documento ?? ''),
        id_carrera: String(filters.id_carrera ?? ''),
        id_tipo_personal: String(filters.id_tipo_personal ?? ''),
        estado: String(filters.estado ?? ''),
    });
    return (
        <AppLayout breadcrumbs={[{ title: 'Portal de consulta', href: route('consulta.index') }]}>
            <Head title="Portal de consulta" />
            <div className="mx-auto w-full max-w-6xl space-y-6 p-5 md:p-8">
                <header className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <span className="text-primary mb-2 inline-flex items-center gap-2 text-sm">
                            <BookOpen className="size-4" />
                            Enseñanza, Investigación y Calidad
                        </span>
                        <h1 className="text-2xl font-semibold">Portal de consulta</h1>
                        <p className="text-muted-foreground mt-2">
                            {isAdmin
                                ? 'Organiza y publica materiales de apoyo para el personal en formación.'
                                : 'Guías, formatos y documentos disponibles para tus participaciones registradas.'}
                        </p>
                    </div>
                    {isAdmin && (
                        <Button asChild>
                            <Link href={route('consulta.create')}>
                                <Plus className="size-4" />
                                Agregar material
                            </Link>
                        </Button>
                    )}
                </header>
                {status && (
                    <p role="status" className="rounded-lg border border-emerald-500/30 bg-emerald-500/10 p-4">
                        {status}
                    </p>
                )}
                {!linked && (
                    <p role="status" className="rounded-xl border p-5">
                        Solicita a Enseñanza vincular tu cuenta con tu persona y participación para consultar los materiales. Los documentos
                        específicos de una carrera requieren datos académicos registrados.
                    </p>
                )}
                <form
                    className="bg-card space-y-4 rounded-xl border p-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.get(route('consulta.index'), { preserveState: true, replace: true });
                    }}
                >
                    <div>
                        <label htmlFor="buscar-material" className="text-sm font-medium">
                            Buscar materiales
                        </label>
                        <Input
                            id="buscar-material"
                            className="mt-2"
                            placeholder="Título o descripción"
                            maxLength={100}
                            value={form.data.buscar}
                            onChange={(event) => form.setData('buscar', event.target.value)}
                        />
                    </div>
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        {(
                            [
                                ['id_tipo_documento', 'Tipo de documento', tipos],
                                ['id_carrera', 'Carrera', carreras],
                                ['id_tipo_personal', 'Tipo de personal', personalTipos],
                            ] as const
                        ).map(([key, label, options]) => (
                            <div key={key}>
                                <label htmlFor={'filtro-' + key} className="text-sm">
                                    {label}
                                </label>
                                <select
                                    id={'filtro-' + key}
                                    className="bg-background mt-2 w-full rounded-md border p-2"
                                    value={form.data[key]}
                                    onChange={(event) => form.setData(key, event.target.value)}
                                >
                                    <option value="">Todos</option>
                                    {options.map((option) => (
                                        <option key={option.id} value={option.id}>
                                            {option.nombre}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        ))}
                        {isAdmin && (
                            <div>
                                <label htmlFor="filtro-estado" className="text-sm">
                                    Estado
                                </label>
                                <select
                                    id="filtro-estado"
                                    className="bg-background mt-2 w-full rounded-md border p-2"
                                    value={form.data.estado}
                                    onChange={(event) => form.setData('estado', event.target.value)}
                                >
                                    <option value="">Todos</option>
                                    {estados.map((state) => (
                                        <option key={state}>{state}</option>
                                    ))}
                                </select>
                            </div>
                        )}
                    </div>
                    <div className="flex items-center gap-4">
                        <Button disabled={form.processing}>Buscar</Button>
                        <Link href={route('consulta.index')} className="text-muted-foreground text-sm underline">
                            Limpiar filtros
                        </Link>
                        <span className="text-muted-foreground text-sm">{materiales.total} material(es)</span>
                    </div>
                </form>
                {!materiales.data.length && linked && (
                    <div className="rounded-xl border border-dashed p-8 text-center">
                        <BookOpen className="text-muted-foreground mx-auto mb-3 size-8" />
                        <h2 className="font-semibold">No hay materiales disponibles</h2>
                        <p className="text-muted-foreground mt-2 text-sm">
                            {isAdmin
                                ? 'Agrega el primer material o cambia los filtros de búsqueda.'
                                : 'Prueba otros filtros. Los materiales aparecerán cuando Enseñanza publique contenido para tu perfil.'}
                        </p>
                    </div>
                )}
                <div className="grid gap-4 md:grid-cols-2">
                    {materiales.data.map((material) => (
                        <article key={material.id_material} className="bg-card flex flex-col gap-4 rounded-xl border p-5">
                            <div className="flex items-start justify-between gap-3">
                                <span className="bg-muted text-muted-foreground inline-flex items-center gap-2 rounded-lg px-3 py-2 text-xs">
                                    <FileText className="size-4" />
                                    {material.tipo ?? 'Documento'}
                                </span>
                                {isAdmin && <span className="rounded-full border px-3 py-1 text-xs">{material.estado}</span>}
                            </div>
                            <h2 className="text-lg font-semibold">{material.titulo}</h2>
                            <p className="text-muted-foreground line-clamp-4 text-sm whitespace-pre-wrap">
                                {material.descripcion || 'Sin descripción adicional.'}
                            </p>
                            <dl className="text-muted-foreground space-y-1 text-xs">
                                <div>
                                    <dt className="inline font-medium">Carrera: </dt>
                                    <dd className="inline">{material.carrera ?? 'Todas las carreras'}</dd>
                                </div>
                                <div>
                                    <dt className="inline font-medium">Personal: </dt>
                                    <dd className="inline">{material.tipo_personal ?? 'Todos los tipos'}</dd>
                                </div>
                                <div>
                                    <dt className="inline font-medium">Publicación: </dt>
                                    <dd className="inline">{material.fecha_publicacion?.slice(0, 10) ?? 'Sin publicar'}</dd>
                                </div>
                            </dl>
                            <details className="text-sm">
                                <summary className="cursor-pointer">Ver descripción completa y archivo</summary>
                                <p className="mt-2 whitespace-pre-wrap">{material.descripcion || 'Sin descripción adicional.'}</p>
                                <p className="text-muted-foreground mt-2 break-all">{material.nombre_archivo}</p>
                            </details>
                            <div className="mt-auto flex flex-wrap items-center gap-3 pt-2">
                                <Button asChild>
                                    <a href={route('consulta.download', material.id_material)}>
                                        <Download className="size-4" />
                                        Descargar
                                    </a>
                                </Button>
                                {isAdmin && (
                                    <>
                                        <Button asChild variant="outline">
                                            <Link href={route('consulta.edit', material.id_material)}>Editar / publicar</Link>
                                        </Button>
                                        <DeleteMaterial material={material} />
                                    </>
                                )}
                            </div>
                        </article>
                    ))}
                </div>
                <nav aria-label="Paginación" className="flex gap-4">
                    {materiales.prev_page_url && <Link href={materiales.prev_page_url}>Anterior</Link>}
                    {materiales.next_page_url && <Link href={materiales.next_page_url}>Siguiente</Link>}
                </nav>
            </div>
        </AppLayout>
    );
}
function DeleteMaterial({ material }: { material: Material }) {
    const form = useForm({});
    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                if (
                    window.confirm(
                        `¿Eliminar «${material.titulo}» del portal? Para ocultarlo temporalmente puedes editarlo y marcarlo como Retirado.`,
                    )
                )
                    form.delete(route('consulta.destroy', material.id_material), { preserveScroll: true });
            }}
        >
            <Button variant="ghost" disabled={form.processing}>
                Eliminar
            </Button>
        </form>
    );
}
