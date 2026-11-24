import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';

type Programacion = {
    id_programacion: number;
    id_promocion: number;
    id_carrera: number;
    id_unidad: number;
    id_tipo_consulta: number;
    id_categoria_productividad: number;
    id_unidad_medida: number;
    actividad: string;
    categoria: string;
    unidad_medida: string;
    fecha_inicio: string;
    fecha_fin: string;
    meta_unidad: number | null;
    revision: number;
};
type Props = {
    programacion: Programacion | null;
    locked: boolean;
    promociones: { id_promocion: number; nombre: string; fecha_inicio: string; fecha_fin: string }[];
    carreras: { id_promocion: number; id_carrera: number; nombre: string }[];
    unidades: { id_unidad: number; nom_unidad: string }[];
    actividades: { id_tipo_consulta: number; nombre: string; id_carrera: number | null }[];
    categorias: { id_categoria_productividad: number; nombre: string }[];
    medidas: { id_unidad_medida: number; nombre: string }[];
    personas: { id_personal: number; nombre: string; ap: string; am: string }[];
    metas: { id_personal: number; cantidad: number }[];
};
export default function ProgramacionForm({
    programacion: p,
    locked,
    promociones,
    carreras,
    unidades,
    actividades,
    categorias,
    medidas,
    personas,
    metas,
}: Props) {
    const form = useForm({
        id_promocion: String(p?.id_promocion ?? ''),
        id_carrera: String(p?.id_carrera ?? ''),
        id_unidad: String(p?.id_unidad ?? ''),
        id_tipo_consulta: String(p?.id_tipo_consulta ?? ''),
        id_categoria_productividad: String(p?.id_categoria_productividad ?? ''),
        id_unidad_medida: String(p?.id_unidad_medida ?? ''),
        fecha_inicio: p?.fecha_inicio ?? '',
        fecha_fin: p?.fecha_fin ?? '',
        meta_unidad: String(p?.meta_unidad ?? ''),
        revision: p?.revision ?? 0,
    });
    const goal = useForm({ id_personal: '', cantidad: '' });
    const fields = [
        {
            key: 'id_carrera' as const,
            label: 'Carrera',
            options: carreras.filter((c) => String(c.id_promocion) === form.data.id_promocion).map((c) => ({ id: c.id_carrera, nombre: c.nombre })),
        },
        { key: 'id_unidad' as const, label: 'Unidad médica', options: unidades.map((u) => ({ id: u.id_unidad, nombre: u.nom_unidad })) },
        {
            key: 'id_tipo_consulta' as const,
            label: 'Actividad',
            options: actividades
                .filter((a) => !a.id_carrera || String(a.id_carrera) === form.data.id_carrera)
                .map((a) => ({ id: a.id_tipo_consulta, nombre: a.nombre })),
        },
        {
            key: 'id_categoria_productividad' as const,
            label: 'Categoría',
            options: categorias.map((c) => ({ id: c.id_categoria_productividad, nombre: c.nombre })),
        },
        {
            key: 'id_unidad_medida' as const,
            label: 'Qué se contabiliza',
            options: medidas.map((m) => ({ id: m.id_unidad_medida, nombre: m.nombre })),
        },
    ];
    return (
        <AppLayout breadcrumbs={[{ title: 'Programación de productividad', href: route('productividad.programacion.index') }]}>
            <Head title={p ? 'Actividad programada' : 'Programar actividad'} />
            <div className="mx-auto w-full max-w-4xl space-y-6 p-5 md:p-8">
                <header>
                    <h1 className="text-2xl font-semibold">{p ? p.actividad : 'Programar actividad'}</h1>
                    <p className="text-muted-foreground mt-2">
                        La meta corresponde al periodo completo indicado. No se convierte automáticamente en una obligación semanal o individual.
                    </p>
                </header>
                {locked && (
                    <p role="status" className="bg-muted rounded-xl p-4">
                        Esta programación tiene capturas o metas individuales. Conservamos sus datos originales: {p?.categoria} · {p?.unidad_medida}.
                        Para otra promoción, registra una nueva programación.
                    </p>
                )}
                <form
                    className="bg-card space-y-5 rounded-xl border p-5"
                    onSubmit={(e) => {
                        e.preventDefault();
                        if (p) form.put(route('productividad.programacion.update', p.id_programacion));
                        else form.post(route('productividad.programacion.store'));
                    }}
                >
                    <fieldset disabled={locked || form.processing} className="grid gap-5 md:grid-cols-2">
                        <label className="space-y-2">
                            <span>Promoción</span>
                            <select
                                required
                                className="bg-background w-full rounded-md border p-2"
                                value={form.data.id_promocion}
                                onChange={(e) => {
                                    const pr = promociones.find((r) => String(r.id_promocion) === e.target.value);
                                    form.setData({
                                        ...form.data,
                                        id_promocion: e.target.value,
                                        id_carrera: '',
                                        id_tipo_consulta: '',
                                        fecha_inicio: pr?.fecha_inicio ?? '',
                                        fecha_fin: pr?.fecha_fin ?? '',
                                    });
                                }}
                            >
                                <option value="">Seleccionar</option>
                                {promociones.map((pr) => (
                                    <option key={pr.id_promocion} value={pr.id_promocion}>
                                        {pr.nombre}
                                    </option>
                                ))}
                            </select>
                            <InputError message={form.errors.id_promocion} />
                        </label>
                        {fields.map((f) => (
                            <label key={f.key} className="space-y-2">
                                <span>{f.label}</span>
                                <select
                                    required
                                    className="bg-background w-full rounded-md border p-2"
                                    value={form.data[f.key]}
                                    onChange={(e) => {
                                        if (f.key === 'id_carrera') form.setData({ ...form.data, id_carrera: e.target.value, id_tipo_consulta: '' });
                                        else form.setData(f.key, e.target.value);
                                    }}
                                >
                                    <option value="">Seleccionar</option>
                                    {f.options.map((o) => (
                                        <option key={o.id} value={o.id}>
                                            {o.nombre}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={form.errors[f.key]} />
                            </label>
                        ))}
                        <label className="space-y-2">
                            <span>Inicio del periodo</span>
                            <Input
                                required
                                type="date"
                                value={form.data.fecha_inicio}
                                onChange={(e) => form.setData('fecha_inicio', e.target.value)}
                            />
                            <InputError message={form.errors.fecha_inicio} />
                        </label>
                        <label className="space-y-2">
                            <span>Término del periodo</span>
                            <Input
                                required
                                type="date"
                                min={form.data.fecha_inicio}
                                value={form.data.fecha_fin}
                                onChange={(e) => form.setData('fecha_fin', e.target.value)}
                            />
                            <InputError message={form.errors.fecha_fin} />
                        </label>
                        <label className="space-y-2">
                            <span>Meta de la unidad (opcional)</span>
                            <Input
                                type="number"
                                min={0}
                                max={100000000}
                                step={1}
                                value={form.data.meta_unidad}
                                onChange={(e) => form.setData('meta_unidad', e.target.value)}
                            />
                            <InputError message={form.errors.meta_unidad} />
                        </label>
                    </fieldset>
                    <p className="text-muted-foreground text-sm">
                        Vacío significa «sin definir». Cero es una meta explícita y no genera porcentaje. Puedes ampliar el periodo para contemplar
                        prórrogas antes de comenzar a capturar, o programar un periodo adicional sin superposición.
                    </p>
                    <InputError message={form.errors.revision} />
                    <div className="flex flex-wrap gap-3">
                        {!locked && <Button disabled={form.processing}>Guardar programación</Button>}
                        <Button variant="outline" asChild>
                            <Link href={route('productividad.programacion.index')}>Volver</Link>
                        </Button>
                    </div>
                </form>
                <p className="text-muted-foreground text-sm">
                    Si falta una opción, regístrala en los catálogos de actividades, categorías o unidades de medida antes de completar esta
                    programación.
                </p>
                {p && (
                    <section className="bg-card space-y-4 rounded-xl border p-5">
                        <h2 className="text-lg font-semibold">Metas individuales del periodo</h2>
                        <p className="text-muted-foreground text-sm">
                            Registra una meta únicamente cuando Enseñanza la haya definido para ese pasante. La meta de la unidad se conserva por
                            separado. Una meta individual guardada se conserva como historial.
                        </p>
                        <ul className="space-y-2">
                            {metas.map((m) => {
                                const person = personas.find((r) => r.id_personal === m.id_personal);
                                return (
                                    <li key={m.id_personal}>
                                        {person ? `${person.nombre} ${person.ap} ${person.am}` : `Participación #${m.id_personal}`}:{' '}
                                        <strong>
                                            {m.cantidad} {p.unidad_medida}
                                        </strong>
                                    </li>
                                );
                            })}
                        </ul>
                        <form
                            className="space-y-3"
                            onSubmit={(e) => {
                                e.preventDefault();
                                goal.post(route('productividad.programacion.goal', p.id_programacion), {
                                    preserveScroll: true,
                                    onSuccess: () => goal.reset(),
                                });
                            }}
                        >
                            <label className="block space-y-2">
                                <span>Pasante de esta promoción, carrera y unidad</span>
                                <select
                                    required
                                    className="bg-background w-full rounded-md border p-2"
                                    value={goal.data.id_personal}
                                    onChange={(e) => goal.setData('id_personal', e.target.value)}
                                >
                                    <option value="">Seleccionar</option>
                                    {personas
                                        .filter((person) => !metas.some((m) => m.id_personal === person.id_personal))
                                        .map((person) => (
                                            <option key={person.id_personal} value={person.id_personal}>
                                                {person.nombre} {person.ap} {person.am} · #{person.id_personal}
                                            </option>
                                        ))}
                                </select>
                            </label>
                            <label className="block space-y-2">
                                <span>Cantidad objetivo individual</span>
                                <Input
                                    required
                                    type="number"
                                    min={0}
                                    max={100000000}
                                    step={1}
                                    value={goal.data.cantidad}
                                    onChange={(e) => goal.setData('cantidad', e.target.value)}
                                />
                            </label>
                            <InputError message={goal.errors.id_personal} />
                            <InputError message={goal.errors.cantidad} />
                            <Button disabled={goal.processing || !goal.data.id_personal}>Asignar meta individual</Button>
                            {goal.recentlySuccessful && <p role="status">Meta individual registrada.</p>}
                        </form>
                    </section>
                )}
            </div>
        </AppLayout>
    );
}
