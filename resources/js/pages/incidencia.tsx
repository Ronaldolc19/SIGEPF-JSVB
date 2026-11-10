import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useRef } from 'react';
import { personName, type Participation } from './expedientes';

type Incident = {
    id_incidencia: number;
    estado: string;
    descripcion: string;
    fecha_hecho: string;
    fecha_fin: string | null;
    revision: number;
    resultado: string | null;
    fecha_cierre: string | null;
};
type Attachment = {
    id_adjunto: number;
    clase: string;
    asunto: string;
    nombre_archivo: string;
    numero_oficio: string | null;
    fecha_oficio: string | null;
    created_at: string;
};
type Props = {
    incidencia: Incident;
    personal: Participation;
    tipo: string;
    unidad: string | null;
    archivos: Attachment[];
    expedienteArchivos: { id_archivo_expediente: number; nombre_archivo: string }[];
    historial: { id_seguimiento: number; nota: string; autor: string; estado_anterior: string; estado_nuevo: string; created_at: string }[];
    canManage: boolean;
    canReview: boolean;
    status?: string;
};
export default function Incidencia({
    incidencia,
    personal,
    tipo,
    unidad,
    archivos,
    expedienteArchivos,
    historial,
    canManage,
    canReview,
    status,
}: Props) {
    const id = incidencia.id_incidencia;
    const open = incidencia.estado !== 'Cerrada';
    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Incidencias y oficios', href: route('incidencias.index') },
                { title: `Incidencia #${id}`, href: '#' },
            ]}
        >
            <Head title={`Incidencia #${id}`} />
            <div className="mx-auto w-full max-w-6xl space-y-6 p-5 md:p-8">
                <header className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <p className="text-muted-foreground text-sm">
                            Incidencia #{id} · {incidencia.estado}
                        </p>
                        <h1 className="mt-2 text-2xl font-semibold">{tipo}</h1>
                        <p className="mt-2">{personName(personal)}</p>
                    </div>
                    {canManage && incidencia.estado === 'Registrada' && (
                        <Button asChild variant="outline">
                            <Link href={route('incidencias.edit', id)}>Corregir registro</Link>
                        </Button>
                    )}
                </header>
                {status && (
                    <p role="status" className="rounded-lg border border-emerald-500/30 bg-emerald-500/10 p-4">
                        {status}
                    </p>
                )}
                <section className="bg-card space-y-3 rounded-xl border p-5">
                    <h2 className="font-semibold">Datos de la incidencia</h2>
                    <p>Unidad al inicio: {unidad ?? 'Sin adscripción histórica registrada'}</p>
                    <p>
                        Periodo: {incidencia.fecha_hecho} — {incidencia.fecha_fin ?? incidencia.fecha_hecho}
                    </p>
                    <p className="whitespace-pre-wrap">{incidencia.descripcion}</p>
                    {incidencia.resultado && (
                        <div className="rounded-lg border p-4">
                            <h3 className="font-semibold">Resultado registrado por Enseñanza</h3>
                            <p className="mt-2 whitespace-pre-wrap">{incidencia.resultado}</p>
                            <p className="text-muted-foreground mt-2 text-xs">Cierre: {incidencia.fecha_cierre}</p>
                        </div>
                    )}
                </section>
                <section className="bg-card space-y-4 rounded-xl border p-5">
                    <h2 className="text-lg font-semibold">Oficios para el pasante</h2>
                    <p className="text-muted-foreground text-sm">Los oficios publicados por Enseñanza están disponibles aquí para su descarga.</p>
                    <Files
                        files={archivos.filter((file) => file.clase === 'oficio')}
                        id={id}
                        empty="Todavía no hay oficios enviados para esta incidencia."
                    />
                    {canReview && <Upload key={'oficio-' + id} id={id} clase="oficio" />}
                </section>
                <section className="bg-card space-y-4 rounded-xl border p-5">
                    <h2 className="text-lg font-semibold">Documentos de respaldo</h2>
                    <Files
                        files={archivos.filter((file) => file.clase === 'evidencia')}
                        id={id}
                        empty="Todavía no se han adjuntado documentos de respaldo."
                    />
                    {canManage && open && (
                        <>
                            <Upload key={'evidencia-' + id} id={id} clase="evidencia" />
                            <LinkEvidence id={id} options={expedienteArchivos} />
                        </>
                    )}
                    {!canManage && (
                        <p className="text-muted-foreground text-sm">
                            Si necesitas entregar un justificante, súbelo en tu expediente digital y avisa a Enseñanza para que lo vincule a esta
                            incidencia.
                        </p>
                    )}
                </section>
                {canManage && (open || canReview) && <Followup key={incidencia.revision} incident={incidencia} admin={canReview} />}
                <section className="space-y-4">
                    <h2 className="text-lg font-semibold">Historial de seguimiento</h2>
                    <ol className="space-y-3">
                        {historial.map((entry) => (
                            <li key={entry.id_seguimiento} className="bg-card rounded-xl border p-4">
                                <p className="text-sm font-semibold">
                                    {entry.autor} ·{' '}
                                    {entry.estado_anterior === entry.estado_nuevo
                                        ? entry.estado_nuevo
                                        : entry.estado_anterior + ' → ' + entry.estado_nuevo}
                                </p>
                                <p className="text-muted-foreground mt-1 text-xs">{entry.created_at}</p>
                                <p className="mt-3 whitespace-pre-wrap">{entry.nota}</p>
                            </li>
                        ))}
                    </ol>
                </section>
                <Button asChild variant="outline">
                    <Link href={route('incidencias.index')}>Volver al listado</Link>
                </Button>
            </div>
        </AppLayout>
    );
}
function Files({ files, id, empty }: { files: Attachment[]; id: number; empty: string }) {
    return files.length ? (
        <ul className="space-y-3">
            {files.map((file) => (
                <li key={file.id_adjunto} className="rounded-lg border p-4">
                    <p className="font-medium">{file.asunto}</p>
                    {file.numero_oficio && (
                        <p className="text-muted-foreground mt-1 text-sm">
                            Oficio {file.numero_oficio} · {file.fecha_oficio}
                        </p>
                    )}
                    <a href={route('incidencias.download', [id, file.id_adjunto])} className="text-primary mt-2 inline-block break-all underline">
                        Descargar {file.nombre_archivo}
                    </a>
                </li>
            ))}
        </ul>
    ) : (
        <p className="text-muted-foreground text-sm">{empty}</p>
    );
}
function Upload({ id, clase }: { id: number; clase: 'oficio' | 'evidencia' }) {
    const form = useForm<{ clase: string; asunto: string; numero_oficio: string; fecha_oficio: string; archivo: File | null }>({
        clase,
        asunto: '',
        numero_oficio: '',
        fecha_oficio: '',
        archivo: null,
    });
    const input = useRef<HTMLInputElement>(null);
    return (
        <form
            className="space-y-4 rounded-lg border p-4"
            onSubmit={(event) => {
                event.preventDefault();
                form.post(route('incidencias.attachment', id), {
                    forceFormData: true,
                    preserveScroll: true,
                    onSuccess: () => {
                        form.reset();
                        if (input.current) input.current.value = '';
                    },
                });
            }}
        >
            <h3 className="font-semibold">{clase === 'oficio' ? 'Enviar oficio al pasante' : 'Adjuntar evidencia'}</h3>
            {clase === 'oficio' && (
                <p className="text-muted-foreground text-sm">
                    Adjunta el oficio oficial en PDF. Al enviarlo quedará disponible en esta incidencia para el pasante; no se envía por correo
                    electrónico.
                </p>
            )}
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="sm:col-span-2">
                    <label htmlFor={'asunto-' + clase}>Asunto *</label>
                    <Input
                        id={'asunto-' + clase}
                        value={form.data.asunto}
                        maxLength={255}
                        onChange={(event) => form.setData('asunto', event.target.value)}
                    />
                    <InputError message={form.errors.asunto} />
                </div>
                {clase === 'oficio' && (
                    <>
                        <div>
                            <label htmlFor="numero-oficio">Número de oficio *</label>
                            <Input
                                id="numero-oficio"
                                maxLength={100}
                                value={form.data.numero_oficio}
                                onChange={(event) => form.setData('numero_oficio', event.target.value)}
                            />
                            <InputError message={form.errors.numero_oficio} />
                        </div>
                        <div>
                            <label htmlFor="fecha-oficio">Fecha del oficio *</label>
                            <Input
                                id="fecha-oficio"
                                type="date"
                                value={form.data.fecha_oficio}
                                onChange={(event) => form.setData('fecha_oficio', event.target.value)}
                            />
                            <InputError message={form.errors.fecha_oficio} />
                        </div>
                    </>
                )}
            </div>
            <div>
                <label htmlFor={'archivo-' + clase}>Archivo · máximo 10 MB *</label>
                <Input
                    ref={input}
                    id={'archivo-' + clase}
                    type="file"
                    accept={clase === 'oficio' ? '.pdf' : '.pdf,.jpg,.jpeg,.png'}
                    disabled={form.processing}
                    onChange={(event) => {
                        const file = event.target.files?.[0] ?? null;
                        form.clearErrors('archivo');
                        if (file && file.size > 10 * 1024 * 1024) {
                            form.setData('archivo', null);
                            form.setError('archivo', 'El archivo no debe superar 10 MB.');
                        } else form.setData('archivo', file);
                    }}
                />
                <InputError message={form.errors.archivo} />
            </div>
            {form.progress && <p role="status">Subiendo: {form.progress.percentage}%</p>}
            <Button disabled={form.processing || !form.data.archivo}>
                {form.processing ? 'Enviando…' : clase === 'oficio' ? 'Enviar oficio al pasante' : 'Adjuntar evidencia'}
            </Button>
        </form>
    );
}
function LinkEvidence({ id, options }: { id: number; options: Props['expedienteArchivos'] }) {
    const form = useForm({ id_archivo_expediente: '' });
    return (
        <form
            className="space-y-3 rounded-lg border p-4"
            onSubmit={(event) => {
                event.preventDefault();
                form.post(route('incidencias.link', id), { preserveScroll: true, onSuccess: () => form.reset() });
            }}
        >
            <label htmlFor="evidencia-expediente" className="font-semibold">
                Vincular un documento que ya entregó el pasante
            </label>
            <select
                id="evidencia-expediente"
                className="bg-background w-full rounded-md border p-3"
                value={form.data.id_archivo_expediente}
                onChange={(event) => form.setData('id_archivo_expediente', event.target.value)}
            >
                <option value="">Seleccionar documento de comisiones, vacaciones o incidencias</option>
                {options.map((file) => (
                    <option key={file.id_archivo_expediente} value={file.id_archivo_expediente}>
                        {file.nombre_archivo}
                    </option>
                ))}
            </select>
            {!options.length && <p className="text-muted-foreground text-sm">No hay archivos disponibles en esos apartados de su expediente.</p>}
            <InputError message={form.errors.id_archivo_expediente} />
            <Button variant="outline" disabled={form.processing || !form.data.id_archivo_expediente}>
                Vincular documento
            </Button>
        </form>
    );
}
function Followup({ incident, admin }: { incident: Incident; admin: boolean }) {
    const transitions: Record<string, string[]> = {
        Registrada: ['Registrada', 'En revisión'],
        'En revisión': ['En revisión', 'Pendiente de documentación', 'Cerrada'],
        'Pendiente de documentación': ['Pendiente de documentación', 'En revisión'],
        Cerrada: ['En revisión'],
    };
    const options = transitions[incident.estado] ?? ['En revisión'];
    const form = useForm({
        revision: incident.revision,
        estado: incident.estado === 'Cerrada' ? 'En revisión' : incident.estado,
        nota: '',
        resultado: '',
    });
    return (
        <form
            className="bg-card space-y-4 rounded-xl border p-5"
            onSubmit={(event) => {
                event.preventDefault();
                form.post(route('incidencias.followup', incident.id_incidencia), { preserveScroll: true });
            }}
        >
            <h2 className="text-lg font-semibold">{incident.estado === 'Cerrada' ? 'Reabrir incidencia' : 'Agregar seguimiento'}</h2>
            {admin && (
                <div>
                    <label htmlFor="estado">Estado</label>
                    <select
                        id="estado"
                        className="bg-background mt-2 w-full rounded-md border p-3"
                        value={form.data.estado}
                        onChange={(event) => form.setData('estado', event.target.value)}
                    >
                        {options.map((state) => (
                            <option key={state}>{state}</option>
                        ))}
                    </select>
                    <InputError message={form.errors.estado} />
                </div>
            )}
            <div>
                <label htmlFor="nota">Actuación, aclaración o motivo del cambio *</label>
                <textarea
                    id="nota"
                    rows={4}
                    maxLength={5000}
                    className="bg-background mt-2 w-full rounded-md border p-3"
                    value={form.data.nota}
                    onChange={(event) => form.setData('nota', event.target.value)}
                />
                <InputError message={form.errors.nota} />
            </div>
            {admin && form.data.estado === 'Cerrada' && (
                <div>
                    <label htmlFor="resultado">Resultado y referencia de la resolución *</label>
                    <textarea
                        id="resultado"
                        rows={3}
                        maxLength={5000}
                        className="bg-background mt-2 w-full rounded-md border p-3"
                        placeholder="Ejemplo: permiso autorizado mediante oficio… por…"
                        value={form.data.resultado}
                        onChange={(event) => form.setData('resultado', event.target.value)}
                    />
                    <InputError message={form.errors.resultado} />
                </div>
            )}
            <p className="text-muted-foreground text-sm">
                El seguimiento y los oficios son visibles para el pasante. El cierre registra la resolución de Enseñanza.
            </p>
            <InputError message={form.errors.revision} />
            <Button disabled={form.processing}>{form.processing ? 'Guardando…' : 'Guardar seguimiento'}</Button>
        </form>
    );
}
