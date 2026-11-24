import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';

type Programacion = {
    id_programacion: number;
    actividad: string;
    categoria: string;
    unidad_medida: string;
    promocion: string;
    carrera: string;
    unidad: string;
    fecha_inicio: string;
    fecha_fin: string;
    meta_unidad: number | null;
    activa: boolean | number;
    revision: number;
    capturas: number;
};
function Activity({ item }: { item: Programacion }) {
    const form = useForm({ activa: !item.activa, revision: item.revision });
    return (
        <article className="bg-card space-y-3 rounded-xl border p-5">
            <p className="text-muted-foreground text-sm">
                {item.promocion} · {item.carrera}
            </p>
            <h2 className="text-lg font-semibold">{item.actividad}</h2>
            <p>{item.unidad}</p>
            <p className="text-sm">
                {item.categoria} · Se contabilizan: {item.unidad_medida}
            </p>
            <p className="text-sm">
                Periodo de la meta: {item.fecha_inicio} — {item.fecha_fin}
            </p>
            <p>
                Meta de la unidad: <strong>{item.meta_unidad ?? 'Sin definir'}</strong>
            </p>
            <p className="text-muted-foreground text-sm">
                {item.activa ? 'Habilitada para captura' : 'Desactivada'} · {item.capturas} capturas históricas
            </p>
            <div className="flex flex-wrap gap-3">
                <Button variant="outline" asChild>
                    <Link href={route('productividad.programacion.edit', item.id_programacion)}>Ver programación y metas individuales</Link>
                </Button>
                <Button
                    variant="outline"
                    disabled={form.processing}
                    onClick={() => form.post(route('productividad.programacion.toggle', item.id_programacion), { preserveScroll: true })}
                >
                    {item.activa ? 'Desactivar captura' : 'Habilitar captura'}
                </Button>
            </div>
            <InputError message={form.errors.revision} />
        </article>
    );
}
export default function Programacion({
    programaciones,
}: {
    programaciones: { data: Programacion[]; prev_page_url: string | null; next_page_url: string | null };
}) {
    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Productividad semanal', href: route('productividad.index') },
                { title: 'Programación por promoción', href: route('productividad.programacion.index') },
            ]}
        >
            <Head title="Programación de productividad" />
            <div className="mx-auto w-full max-w-6xl space-y-6 p-5 md:p-8">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">Actividades y metas por promoción</h1>
                        <p className="text-muted-foreground mt-2">
                            Define qué se captura en cada carrera y unidad. Usa el calendario del encabezado para cambiar de promoción.
                        </p>
                    </div>
                    <Button asChild>
                        <Link href={route('productividad.programacion.create')}>Programar actividad</Link>
                    </Button>
                </header>
                <div className="flex flex-wrap gap-4 text-sm">
                    <Link href={route('catalogos.show', 'tipos_consultas')}>Catálogo de actividades</Link>
                    <Link href={route('catalogos.show', 'categorias_productividad')}>Categorías</Link>
                    <Link href={route('catalogos.show', 'unidades_medida_productividad')}>Unidades de medida</Link>
                </div>
                {!programaciones.data.length && (
                    <p role="status" className="rounded-xl border p-6">
                        No hay actividades programadas para esta selección. Puedes crear una programación para la promoción correspondiente.
                    </p>
                )}
                <div className="grid gap-4 lg:grid-cols-2">
                    {programaciones.data.map((item) => (
                        <Activity key={`${item.id_programacion}-${item.revision}`} item={item} />
                    ))}
                </div>
                <nav className="flex gap-4" aria-label="Paginación">
                    {programaciones.prev_page_url && <Link href={programaciones.prev_page_url}>Anterior</Link>}
                    {programaciones.next_page_url && <Link href={programaciones.next_page_url}>Siguiente</Link>}
                </nav>
            </div>
        </AppLayout>
    );
}
