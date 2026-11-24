import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';

type Registro = {
    id_productividad: number;
    id_personal: number;
    id_tipo_consulta: number;
    fecha_actividad: string;
    num_actividades: number;
    descripcion: string | null;
    revision: number;
    id_programacion: number | null;
    resultado: string;
};
type Props = {
    canSelectPersonal: boolean;
    registro: Registro | null;
    participaciones: {
        id_personal: number;
        nombre: string;
        fecha_ingreso: string | null;
        fecha_termino: string | null;
        carreras: number[];
        id_promocion: number | null;
    }[];
    programaciones: {
        id_programacion: number;
        id_personal: number;
        id_tipo_consulta: number;
        id_unidad: number;
        actividad: string;
        unidad_medida: string;
        fecha_inicio: string;
        fecha_fin: string;
        activa: boolean | number;
    }[];
    adscripciones: { id_personal: number; id_unidad: number; fecha_inicio: string; fecha_fin: string | null }[];
    actividades: { id_tipo_consulta: number; nombre: string; id_carrera: number | null }[];
    hoy: string;
};

export default function ProductividadForm({ registro, participaciones, actividades, programaciones, adscripciones, hoy, canSelectPersonal }: Props) {
    const form = useForm({
        id_personal: String(registro?.id_personal ?? (participaciones.length === 1 ? participaciones[0].id_personal : '')),
        id_tipo_consulta: String(registro?.id_tipo_consulta ?? ''),
        fecha_actividad: registro?.fecha_actividad ?? hoy,
        num_actividades: String(registro?.num_actividades ?? ''),
        descripcion: registro?.descripcion ?? '',
        revision: registro?.revision ?? 0,
        id_programacion: String(registro?.id_programacion ?? ''),
        resultado: registro?.resultado ?? 'Realizada',
    });
    const person = participaciones.find((p) => String(p.id_personal) === form.data.id_personal);
    const options = actividades.filter((a) => !a.id_carrera || person?.carreras.includes(a.id_carrera));
    const usesProgram = !!registro?.id_programacion || (!!person?.id_promocion && !(registro && !registro.id_programacion));
    const programs = programaciones.filter(
        (p) =>
            p.id_personal === person?.id_personal &&
            (p.activa || p.id_programacion === registro?.id_programacion) &&
            p.fecha_inicio <= form.data.fecha_actividad &&
            p.fecha_fin >= form.data.fecha_actividad &&
            adscripciones.some(
                (a) =>
                    a.id_personal === p.id_personal &&
                    a.id_unidad === p.id_unidad &&
                    a.fecha_inicio <= form.data.fecha_actividad &&
                    (!a.fecha_fin || a.fecha_fin >= form.data.fecha_actividad),
            ),
    );
    const title = registro ? 'Editar actividad' : 'Registrar actividad';
    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Productividad semanal', href: route('productividad.index') },
                { title, href: '#' },
            ]}
        >
            <Head title={title} />
            <div className="mx-auto w-full max-w-3xl space-y-6 p-5 md:p-8">
                <header>
                    <h1 className="text-2xl font-semibold">{title}</h1>
                    <p className="text-muted-foreground mt-2">
                        Captura el total de una actividad en una fecha. El sistema la agrupa automáticamente en su semana.
                    </p>
                </header>
                {!participaciones.length && (
                    <p role="status" className="rounded-xl border p-5">
                        No tienes una participación en formación disponible. Solicita al administrador revisar la vinculación de tu cuenta y tu
                        periodo.
                    </p>
                )}
                <form
                    className="bg-card space-y-5 rounded-xl border p-5"
                    onSubmit={(event) => {
                        event.preventDefault();
                        if (registro) form.put(route('productividad.update', registro.id_productividad));
                        else form.post(route('productividad.store'));
                    }}
                >
                    {canSelectPersonal ? (
                        <label className="block space-y-2">
                            <span>Participación</span>
                            <select
                                required
                                disabled={!!registro}
                                className="bg-background w-full rounded-md border p-2"
                                value={form.data.id_personal}
                                onChange={(e) =>
                                    form.setData({ ...form.data, id_personal: e.target.value, id_tipo_consulta: '', id_programacion: '' })
                                }
                            >
                                <option value="">Seleccionar</option>
                                {participaciones.map((p) => (
                                    <option key={p.id_personal} value={p.id_personal}>
                                        {p.nombre} · {p.fecha_ingreso} — {p.fecha_termino ?? 'Sin fecha de término'}
                                    </option>
                                ))}
                            </select>
                        </label>
                    ) : person ? (
                        <div className="space-y-1">
                            <p className="font-medium">{person.nombre}</p>
                            <p className="text-muted-foreground text-sm">
                                Periodo de servicio: {person.fecha_ingreso} — {person.fecha_termino ?? 'Sin fecha de término'}
                            </p>
                        </div>
                    ) : null}
                    <InputError message={form.errors.id_personal} />
                    {usesProgram ? (
                        <label className="block space-y-2">
                            <span>Actividad programada para tu promoción y fecha</span>
                            <select
                                required
                                disabled={!!registro}
                                className="bg-background w-full rounded-md border p-2"
                                value={form.data.id_programacion}
                                onChange={(e) => {
                                    const p = programs.find((item) => String(item.id_programacion) === e.target.value);
                                    form.setData({
                                        ...form.data,
                                        id_programacion: e.target.value,
                                        id_tipo_consulta: String(p?.id_tipo_consulta ?? ''),
                                    });
                                }}
                            >
                                <option value="">Seleccionar actividad</option>
                                {programs.map((p) => (
                                    <option key={p.id_programacion} value={p.id_programacion}>
                                        {p.actividad} · {p.unidad_medida}
                                    </option>
                                ))}
                            </select>
                            <InputError message={form.errors.id_programacion} />
                            {!programs.length && (
                                <p role="status" className="text-sm">
                                    No hay programación habilitada para esta fecha y adscripción. Solicita a Enseñanza revisar las actividades de tu
                                    promoción.
                                </p>
                            )}
                        </label>
                    ) : (
                        <label className="block space-y-2">
                            <span>Actividad correspondiente a la carrera</span>
                            <select
                                required
                                disabled={!person}
                                className="bg-background w-full rounded-md border p-2"
                                value={form.data.id_tipo_consulta}
                                onChange={(e) => form.setData('id_tipo_consulta', e.target.value)}
                            >
                                <option value="">Seleccionar actividad</option>
                                {options.map((a) => (
                                    <option key={a.id_tipo_consulta} value={a.id_tipo_consulta}>
                                        {a.nombre}
                                        {!a.id_carrera ? ' · Común' : ''}
                                    </option>
                                ))}
                            </select>
                        </label>
                    )}
                    <InputError message={form.errors.id_tipo_consulta} />
                    {!usesProgram && person && !options.length && (
                        <p role="status" className="text-sm">
                            No hay actividades configuradas para esta carrera. Solicita al administrador agregarlas en Catálogos → Actividades de
                            productividad.
                        </p>
                    )}
                    {!usesProgram && (
                        <p className="text-muted-foreground text-sm">
                            Captura sin programación de promoción. Estos registros se conservan separados de las metas por periodo.
                        </p>
                    )}
                    <label className="block space-y-2">
                        <span>Resultado de la actividad</span>
                        <select
                            className="bg-background w-full rounded-md border p-2"
                            value={form.data.resultado}
                            onChange={(e) =>
                                form.setData({ ...form.data, resultado: e.target.value, num_actividades: e.target.value === 'Realizada' ? '' : '0' })
                            }
                        >
                            <option>Realizada</option>
                            <option>Sin actividad</option>
                            <option>No aplica</option>
                        </select>
                        <InputError message={form.errors.resultado} />
                    </label>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <label className="space-y-2">
                            <span>Fecha de la actividad</span>
                            <Input
                                required
                                type="date"
                                min={person?.fecha_ingreso ?? undefined}
                                max={person?.fecha_termino && person.fecha_termino < hoy ? person.fecha_termino : hoy}
                                value={form.data.fecha_actividad}
                                onChange={(e) => form.setData('fecha_actividad', e.target.value)}
                            />
                        </label>
                        <label className="space-y-2">
                            <span>Cantidad realizada</span>
                            <Input
                                required
                                type="number"
                                min={form.data.resultado === 'Realizada' ? 1 : 0}
                                readOnly={form.data.resultado !== 'Realizada'}
                                max={100000}
                                step={1}
                                value={form.data.num_actividades}
                                onChange={(e) => form.setData('num_actividades', e.target.value)}
                            />
                        </label>
                    </div>
                    <InputError message={form.errors.fecha_actividad} />
                    <InputError message={form.errors.num_actividades} />
                    <label className="block space-y-2">
                        <span>{form.data.resultado === 'Realizada' ? 'Descripción (opcional)' : 'Motivo (obligatorio)'}</span>
                        <textarea
                            className="bg-background min-h-28 w-full rounded-md border p-3"
                            maxLength={3000}
                            required={form.data.resultado !== 'Realizada'}
                            value={form.data.descripcion}
                            onChange={(e) => form.setData('descripcion', e.target.value)}
                        />
                    </label>
                    <p className="text-muted-foreground text-sm">Describe la actividad de forma general, sin nombres ni datos de pacientes.</p>
                    <InputError message={form.errors.descripcion} />
                    <InputError message={form.errors.revision} />
                    {registro && (
                        <p className="text-muted-foreground text-sm">Al guardar cambios, la actividad quedará nuevamente pendiente de revisión.</p>
                    )}
                    <div className="flex flex-wrap gap-3">
                        <Button disabled={form.processing || !person || (usesProgram ? !programs.length : !options.length)}>Guardar actividad</Button>
                        <Button variant="outline" asChild>
                            <Link href={route('productividad.index')}>Cancelar</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
