import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';

type Registro = {
    id_productividad: number;
    nombre: string;
    ap: string;
    am: string;
    actividad: string;
    fecha_actividad: string;
    num_actividades: number;
    descripcion: string | null;
    estado: string;
    observaciones: string | null;
    revision: number;
    canEdit: boolean;
    canDelete: boolean;
    canReview: boolean;
    resultado: string;
    unidad_medida: string | null;
};
type Props = {
    registros: { data: Registro[]; prev_page_url: string | null; next_page_url: string | null; total: number };
    resumen: { nombre: string; carrera: string | null; estado: string; cantidad: number; unidad_medida: string | null }[];
    semana: string;
    finSemana: string;
    personal: string | number;
    participaciones: { id_personal: number; nombre: string; estado: string }[];
    canCreate: boolean;
    canCatalog: boolean;
};

function Activity({ item }: { item: Registro }) {
    const form = useForm({ estado: 'Validada', observaciones: item.observaciones ?? '', revision: item.revision });
    return (
        <article className="bg-card space-y-4 rounded-xl border p-5">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p className="text-muted-foreground text-sm">
                        {item.fecha_actividad} · {[item.nombre, item.ap, item.am].filter(Boolean).join(' ')}
                    </p>
                    <h2 className="mt-1 text-lg font-semibold">{item.actividad}</h2>
                </div>
                <span className="bg-muted rounded-full px-3 py-1 text-sm">{item.estado}</span>
            </div>
            <p>
                {item.resultado} · Cantidad registrada:{' '}
                <strong>
                    {item.num_actividades} {item.unidad_medida}
                </strong>
            </p>
            {item.descripcion && <p className="text-sm whitespace-pre-wrap">{item.descripcion}</p>}
            {item.observaciones && (
                <p className="bg-muted rounded-lg p-3 text-sm">
                    <strong>Observaciones: </strong>
                    {item.observaciones}
                </p>
            )}
            <div className="flex flex-wrap gap-3">
                {item.canEdit && (
                    <Button variant="outline" asChild>
                        <Link href={route('productividad.edit', item.id_productividad)}>Editar actividad</Link>
                    </Button>
                )}
                {item.canDelete && (
                    <Button
                        variant="outline"
                        disabled={form.processing}
                        onClick={() => {
                            if (window.confirm('¿Eliminar este registro de productividad?'))
                                form.delete(route('productividad.destroy', item.id_productividad), { preserveScroll: true });
                        }}
                    >
                        Eliminar
                    </Button>
                )}
            </div>
            <InputError message={form.errors.revision} />
            {item.canReview && (
                <details className="border-t pt-3">
                    <summary className="cursor-pointer font-medium">Revisar actividad</summary>
                    <form
                        className="mt-4 space-y-3"
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.post(route('productividad.review', item.id_productividad), { preserveScroll: true });
                        }}
                    >
                        <label className="block space-y-2">
                            <span>Resultado</span>
                            <select
                                className="bg-background block w-full rounded-md border p-2"
                                value={form.data.estado}
                                onChange={(e) => form.setData('estado', e.target.value)}
                            >
                                <option>Validada</option>
                                <option>Por corregir</option>
                            </select>
                        </label>
                        <label className="block space-y-2">
                            <span>Observaciones {form.data.estado === 'Por corregir' ? '(obligatorias)' : '(opcionales)'}</span>
                            <textarea
                                className="bg-background block min-h-24 w-full rounded-md border p-3"
                                maxLength={3000}
                                required={form.data.estado === 'Por corregir'}
                                value={form.data.observaciones}
                                onChange={(e) => form.setData('observaciones', e.target.value)}
                            />
                        </label>
                        <InputError message={form.errors.observaciones} />
                        <InputError message={form.errors.estado} />
                        <Button disabled={form.processing}>Guardar revisión</Button>
                        {form.recentlySuccessful && <p role="status">Revisión guardada.</p>}
                    </form>
                </details>
            )}
        </article>
    );
}

export default function Productividad({ registros, resumen, semana, finSemana, personal, participaciones, canCreate, canCatalog }: Props) {
    const filter = useForm({ semana, personal: String(personal) });
    return (
        <AppLayout breadcrumbs={[{ title: 'Productividad semanal', href: route('productividad.index') }]}>
            <Head title="Productividad semanal" />
            <div className="mx-auto w-full max-w-6xl space-y-6 p-5 md:p-8">
                <header className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">Productividad semanal</h1>
                        <p className="text-muted-foreground mt-2">Registra lo realizado cada día y consulta el resumen de lunes a domingo.</p>
                    </div>
                    <div className="flex flex-wrap gap-3">
                        {canCatalog && (
                            <Button asChild>
                                <Link href={route('productividad.programacion.index')}>Actividades y metas por promoción</Link>
                            </Button>
                        )}
                        {canCatalog && (
                            <Button variant="outline" asChild>
                                <Link href={route('catalogos.show', 'tipos_consultas')}>Configurar actividades</Link>
                            </Button>
                        )}
                        {canCreate && (
                            <Button asChild>
                                <Link href={route('productividad.create')}>Registrar actividad</Link>
                            </Button>
                        )}
                    </div>
                </header>
                <form
                    className="bg-card flex flex-wrap items-end gap-4 rounded-xl border p-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        filter.get(route('productividad.index'), { preserveState: false });
                    }}
                >
                    <label className="space-y-2">
                        <span className="block text-sm font-medium">Selecciona un día de la semana</span>
                        <Input type="date" required value={filter.data.semana} onChange={(e) => filter.setData('semana', e.target.value)} />
                    </label>
                    <label className="min-w-0 flex-1 space-y-2">
                        <span className="block text-sm font-medium">Participación</span>
                        <select
                            className="bg-background w-full rounded-md border p-2"
                            value={filter.data.personal}
                            onChange={(e) => filter.setData('personal', e.target.value)}
                        >
                            <option value="">Todas las disponibles</option>
                            {participaciones.map((p) => (
                                <option key={p.id_personal} value={p.id_personal}>
                                    {p.nombre} · #{p.id_personal} · {p.estado}
                                </option>
                            ))}
                        </select>
                    </label>
                    <Button disabled={filter.processing}>Consultar</Button>
                    {filter.data.personal && (
                        <Button variant="outline" asChild>
                            <Link href={route('productividad.report', filter.data.personal)}>Resumen del periodo y metas</Link>
                        </Button>
                    )}
                    <InputError message={filter.errors.semana} />
                </form>
                <section className="space-y-3" aria-label="Resumen semanal">
                    <h2 className="text-lg font-semibold">
                        Semana del {semana} al {finSemana}
                    </h2>
                    <p className="text-muted-foreground text-sm">
                        {registros.total} registros. Las cantidades se muestran por actividad y estado; no representan pacientes únicos.
                    </p>
                    {resumen.length > 0 && (
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            {resumen.map((r, index) => (
                                <div key={index} className="bg-muted/40 rounded-lg border p-4">
                                    <p className="font-medium">{r.nombre}</p>
                                    <p className="text-muted-foreground text-sm">{r.carrera ?? 'Actividad común'}</p>
                                    <p className="text-muted-foreground text-sm">{r.estado}</p>
                                    <p className="mt-2 text-2xl font-semibold">{r.cantidad}</p>
                                    {r.unidad_medida && <p className="text-muted-foreground text-sm">{r.unidad_medida}</p>}
                                </div>
                            ))}
                        </div>
                    )}
                </section>
                {!registros.data.length && (
                    <p role="status" className="rounded-xl border p-6">
                        No hay actividades registradas para esta semana y selección.{' '}
                        {canCreate
                            ? 'Utiliza «Registrar actividad» para comenzar.'
                            : 'Los registros aparecerán cuando el personal capture sus actividades.'}
                    </p>
                )}
                <div className="space-y-4">
                    {registros.data.map((item) => (
                        <Activity key={`${item.id_productividad}-${item.revision}`} item={item} />
                    ))}
                </div>
                <nav className="flex gap-4" aria-label="Paginación">
                    {registros.prev_page_url && <Link href={registros.prev_page_url}>Anterior</Link>}
                    {registros.next_page_url && <Link href={registros.next_page_url}>Siguiente</Link>}
                </nav>
            </div>
        </AppLayout>
    );
}
