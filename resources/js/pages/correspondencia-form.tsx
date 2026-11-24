import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';

type Documento = {
    id_correspondencia: number;
    id_tipo_correspondencia: number;
    direccion: string;
    numero_oficio: string | null;
    asunto: string;
    remitente: string;
    destinatario_externo: string | null;
    descripcion: string | null;
    fecha_documento: string;
    fecha_movimiento: string;
    requiere_respuesta: boolean;
    fecha_limite: string | null;
    responsable_id: number;
    revision: number;
};
type Persona = { id_personal: number; nombre: string; ap: string; am: string; promocion: string | null; carrera: string | null };
type Props = {
    documento: Documento | null;
    seleccionados: number[];
    principal: { nombre_archivo: string; id_adjunto_incidencia: number | null } | null;
    tipos: { id_tipo_correspondencia: number; nombre: string }[];
    responsables: { id: number; name: string }[];
    responsableDefault: number;
    personas: Persona[];
    adscripciones: { id_personal: number; id_unidad: number; nom_unidad: string }[];
    oficios: { id_adjunto: number; id_personal: number; numero_oficio: string; asunto: string; fecha_oficio: string }[];
    hoy: string;
};
export default function CorrespondenciaForm({
    documento: d,
    seleccionados,
    principal,
    tipos,
    responsables,
    responsableDefault,
    personas,
    adscripciones,
    oficios,
    hoy,
}: Props) {
    const form = useForm({
        id_tipo_correspondencia: String(d?.id_tipo_correspondencia ?? tipos[0]?.id_tipo_correspondencia ?? ''),
        direccion: d?.direccion ?? 'Emitida',
        numero_oficio: d?.numero_oficio ?? '',
        asunto: d?.asunto ?? '',
        remitente: d?.remitente ?? '',
        destinatario_externo: d?.destinatario_externo ?? '',
        descripcion: d?.descripcion ?? '',
        fecha_documento: d?.fecha_documento ?? hoy,
        fecha_movimiento: d?.fecha_movimiento ?? hoy,
        requiere_respuesta: !!d?.requiere_respuesta,
        fecha_limite: d?.fecha_limite ?? '',
        responsable_id: String(d?.responsable_id ?? responsableDefault),
        destinatarios: seleccionados.map(Number),
        archivo: null as File | null,
        anexos: [] as File[],
        id_adjunto_incidencia: String(principal?.id_adjunto_incidencia ?? ''),
        revision: d?.revision ?? 0,
    });
    const [buscar, setBuscar] = useState('');
    const [promocion, setPromocion] = useState('');
    const [carrera, setCarrera] = useState('');
    const [unidad, setUnidad] = useState('');
    const visibles = personas.filter(
        (p) =>
            `${p.nombre} ${p.ap} ${p.am}`.toLowerCase().includes(buscar.toLowerCase()) &&
            (!promocion || p.promocion === promocion) &&
            (!carrera || p.carrera === carrera) &&
            (!unidad || adscripciones.some((a) => a.id_personal === p.id_personal && String(a.id_unidad) === unidad)),
    );
    const selectClass = 'bg-background w-full rounded-md border p-2';
    return (
        <AppLayout breadcrumbs={[{ title: 'Correspondencia', href: route('correspondencia.index') }]}>
            <Head title="Registrar correspondencia" />
            <form
                className="mx-auto w-full max-w-5xl space-y-6 p-6"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.transform((data) => ({ ...data, ...(d ? { _method: 'put' } : {}) }));
                    form.post(d ? route('correspondencia.update', d.id_correspondencia) : route('correspondencia.store'), { forceFormData: true });
                }}
            >
                <h1 className="text-2xl font-semibold">{d ? 'Editar borrador' : 'Registrar correspondencia'}</h1>
                <p className="text-muted-foreground">
                    Primero guarda el borrador. Al publicarlo, sus destinatarios y documento quedarán fijos. Los registros externos no envían correo
                    electrónico.
                </p>
                {Object.keys(form.errors).length > 0 && (
                    <div role="alert" className="border-destructive rounded-lg border p-4">
                        {Object.entries(form.errors).map(([k, v]) => (
                            <p key={k}>{v}</p>
                        ))}
                    </div>
                )}
                <fieldset className="grid gap-4 rounded-xl border p-5 sm:grid-cols-2">
                    <legend className="px-2 font-semibold">Datos del documento</legend>
                    <label>
                        Tipo
                        <select
                            className={selectClass}
                            value={form.data.id_tipo_correspondencia}
                            onChange={(e) => form.setData('id_tipo_correspondencia', e.target.value)}
                        >
                            {tipos.map((t) => (
                                <option key={t.id_tipo_correspondencia} value={t.id_tipo_correspondencia}>
                                    {t.nombre}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label>
                        Dirección
                        <select className={selectClass} value={form.data.direccion} onChange={(e) => form.setData('direccion', e.target.value)}>
                            <option>Emitida</option>
                            <option>Recibida</option>
                        </select>
                    </label>
                    {(['numero_oficio', 'asunto', 'remitente', 'destinatario_externo'] as const).map((key, i) => (
                        <label key={key}>
                            {['Número oficial (opcional)', 'Asunto', 'Remitente', 'Contraparte externa (si corresponde)'][i]}
                            <Input
                                value={form.data[key]}
                                required={key === 'asunto' || key === 'remitente'}
                                onChange={(e) => form.setData(key, e.target.value)}
                            />
                        </label>
                    ))}
                    <label>
                        Fecha del documento
                        <Input
                            type="date"
                            required
                            max={hoy}
                            value={form.data.fecha_documento}
                            onChange={(e) => form.setData('fecha_documento', e.target.value)}
                        />
                    </label>
                    <label>
                        Fecha de recepción o emisión
                        <Input
                            type="date"
                            required
                            max={hoy}
                            min={form.data.fecha_documento}
                            value={form.data.fecha_movimiento}
                            onChange={(e) => form.setData('fecha_movimiento', e.target.value)}
                        />
                    </label>
                    <label>
                        Responsable
                        <select
                            className={selectClass}
                            value={form.data.responsable_id}
                            onChange={(e) => form.setData('responsable_id', e.target.value)}
                        >
                            {responsables.map((r) => (
                                <option key={r.id} value={r.id}>
                                    {r.name}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label className="flex items-center gap-2">
                        <input
                            type="checkbox"
                            checked={form.data.requiere_respuesta}
                            onChange={(e) => form.setData('requiere_respuesta', e.target.checked)}
                        />
                        Requiere respuesta
                    </label>
                    {form.data.requiere_respuesta && (
                        <label>
                            Fecha límite (opcional)
                            <Input
                                type="date"
                                min={form.data.fecha_movimiento}
                                value={form.data.fecha_limite}
                                onChange={(e) => form.setData('fecha_limite', e.target.value)}
                            />
                        </label>
                    )}
                    <label className="sm:col-span-2">
                        Descripción
                        <textarea
                            className={selectClass}
                            rows={3}
                            value={form.data.descripcion}
                            onChange={(e) => form.setData('descripcion', e.target.value)}
                        />
                    </label>
                </fieldset>
                <fieldset className="space-y-4 rounded-xl border p-5">
                    <legend className="px-2 font-semibold">Documento y anexos</legend>
                    {principal && <p>Actual: {principal.nombre_archivo}. Se conserva si no eliges otro.</p>}
                    <label className="block">
                        Vincular oficio de incidencias
                        <select
                            className={selectClass}
                            value={form.data.id_adjunto_incidencia}
                            onChange={(e) => {
                                const o = oficios.find((o) => String(o.id_adjunto) === e.target.value);
                                form.setData((data) => ({
                                    ...data,
                                    id_adjunto_incidencia: e.target.value,
                                    ...(o
                                        ? {
                                              archivo: null,
                                              destinatarios: [o.id_personal],
                                              destinatario_externo: '',
                                              direccion: 'Emitida',
                                              numero_oficio: o.numero_oficio ?? '',
                                              asunto: o.asunto,
                                              fecha_documento: o.fecha_oficio,
                                          }
                                        : {}),
                                }));
                            }}
                        >
                            <option value="">Usar PDF propio</option>
                            {oficios.map((o) => (
                                <option key={o.id_adjunto} value={o.id_adjunto}>
                                    #{o.id_adjunto} · {o.numero_oficio} · {o.asunto}
                                </option>
                            ))}
                        </select>
                    </label>
                    {!form.data.id_adjunto_incidencia && (
                        <label className="block">
                            Documento principal PDF
                            <Input type="file" accept="application/pdf,.pdf" onChange={(e) => form.setData('archivo', e.target.files?.[0] ?? null)} />
                        </label>
                    )}
                    <label className="block">
                        Anexos PDF (hasta 5 por envío)
                        <Input
                            type="file"
                            multiple
                            accept="application/pdf,.pdf"
                            onChange={(e) => form.setData('anexos', Array.from(e.target.files ?? []))}
                        />
                    </label>
                    <p className="text-muted-foreground text-sm">
                        Máximo 10 MB por archivo. Un oficio vinculado solo se dirige al pasante de su incidencia.
                    </p>
                </fieldset>
                <fieldset className="space-y-4 rounded-xl border p-5">
                    <legend className="px-2 font-semibold">Destinatarios · {form.data.destinatarios.length} seleccionados</legend>
                    <div className="grid gap-3 sm:grid-cols-2">
                        <Input aria-label="Buscar pasante" placeholder="Buscar pasante" value={buscar} onChange={(e) => setBuscar(e.target.value)} />
                        <select aria-label="Promoción" className={selectClass} value={promocion} onChange={(e) => setPromocion(e.target.value)}>
                            <option value="">Todas las promociones</option>
                            {[...new Set(personas.map((p) => p.promocion).filter(Boolean))].map((p) => (
                                <option key={p} value={p!}>
                                    {p}
                                </option>
                            ))}
                        </select>
                        <select aria-label="Carrera" className={selectClass} value={carrera} onChange={(e) => setCarrera(e.target.value)}>
                            <option value="">Todas las carreras</option>
                            {[...new Set(personas.map((p) => p.carrera).filter(Boolean))].map((c) => (
                                <option key={c} value={c!}>
                                    {c}
                                </option>
                            ))}
                        </select>
                        <select aria-label="Unidad médica" className={selectClass} value={unidad} onChange={(e) => setUnidad(e.target.value)}>
                            <option value="">Todas las unidades actuales</option>
                            {Array.from(new Map(adscripciones.map((a) => [a.id_unidad, a])).values()).map((a) => (
                                <option key={a.id_unidad} value={a.id_unidad}>
                                    {a.nom_unidad}
                                </option>
                            ))}
                        </select>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        disabled={!!form.data.id_adjunto_incidencia}
                        onClick={() =>
                            form.setData('destinatarios', [...new Set([...form.data.destinatarios, ...visibles.map((p) => p.id_personal)])])
                        }
                    >
                        Seleccionar resultados
                    </Button>
                    <div className="max-h-72 space-y-2 overflow-y-auto">
                        {visibles.map((p) => (
                            <label key={p.id_personal} className="flex items-center gap-3 rounded-md border p-3">
                                <input
                                    type="checkbox"
                                    disabled={!!form.data.id_adjunto_incidencia}
                                    checked={form.data.destinatarios.includes(p.id_personal)}
                                    onChange={(e) =>
                                        form.setData(
                                            'destinatarios',
                                            e.target.checked
                                                ? [...form.data.destinatarios, p.id_personal]
                                                : form.data.destinatarios.filter((id) => id !== p.id_personal),
                                        )
                                    }
                                />
                                <span>
                                    {p.nombre} {p.ap} {p.am}
                                    <small className="text-muted-foreground block">
                                        Participación #{p.id_personal} · {p.promocion ?? 'Sin promoción'} · {p.carrera ?? 'Sin carrera'}
                                    </small>
                                </span>
                            </label>
                        ))}
                        {!visibles.length && <p>No hay coincidencias.</p>}
                    </div>
                </fieldset>
                <div className="flex gap-3">
                    <Button disabled={form.processing}>Guardar borrador</Button>
                    <Button asChild variant="outline">
                        <Link href={route('correspondencia.index')}>Cancelar</Link>
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}
