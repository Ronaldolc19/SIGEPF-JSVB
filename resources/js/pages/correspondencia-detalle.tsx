import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';

type Destinatario = {
    id_destinatario: number;
    id_personal: number;
    nombre: string;
    ap: string;
    am: string;
    consultado_at: string | null;
    confirmado_at: string | null;
};
type Respuesta = {
    id_respuesta: number;
    id_destinatario: number | null;
    comentario: string;
    nombre_archivo: string | null;
    estado: string;
    observaciones: string | null;
    revision: number;
    created_at: string;
};
type Props = {
    documento: {
        id_correspondencia: number;
        asunto: string;
        numero_oficio: string | null;
        remitente: string;
        destinatario_externo: string | null;
        descripcion: string | null;
        direccion: string;
        fecha_documento: string;
        fecha_movimiento: string;
        fecha_limite: string | null;
        estado: string;
        requiere_respuesta: boolean;
        revision: number;
    };
    isAdmin: boolean;
    canRespond: boolean;
    status: string | null;
    destinatarios: Destinatario[];
    archivos: { id_archivo: number; nombre_archivo: string; clase: string; id_adjunto_incidencia: number | null }[];
    respuestas: Respuesta[];
    historial: { id_historial: number; evento: string; nota: string | null; autor: string; created_at: string }[];
};
function Errors({ errors }: { errors: object }) {
    return (
        <>
            {Object.entries(errors).map(([key, value]) => (
                <p role="alert" className="text-destructive text-sm" key={key}>
                    {String(value)}
                </p>
            ))}
        </>
    );
}
function Confirmar({ id, destinatario }: { id: number; destinatario: Destinatario }) {
    const form = useForm({});
    return (
        <div>
            <Button
                type="button"
                disabled={form.processing}
                onClick={() => form.post(route('correspondencia.acknowledge', [id, destinatario.id_destinatario]))}
            >
                Confirmar recepción
            </Button>
            <Errors errors={form.errors} />
        </div>
    );
}
function Responder({ id, destinatario }: { id: number; destinatario?: Destinatario }) {
    const form = useForm({ id_destinatario: destinatario?.id_destinatario ?? '', comentario: '', archivo: null as File | null });
    return (
        <form
            className="space-y-3 rounded-xl border p-5"
            onSubmit={(e) => {
                e.preventDefault();
                const element = e.currentTarget;
                form.post(route('correspondencia.respond', id), {
                    forceFormData: true,
                    preserveScroll: true,
                    onSuccess: () => {
                        form.reset();
                        element.reset();
                    },
                });
            }}
        >
            <h3 className="font-semibold">
                {destinatario ? `Responder · participación #${destinatario.id_personal}` : 'Registrar respuesta o acuse de la contraparte externa'}
            </h3>
            <label className="block">
                Comentario
                <textarea
                    required
                    maxLength={5000}
                    className="bg-background mt-1 block w-full rounded-md border p-3"
                    rows={3}
                    value={form.data.comentario}
                    onChange={(e) => form.setData('comentario', e.target.value)}
                />
            </label>
            <label className="block">
                PDF de respuesta (opcional, máximo 10 MB)
                <Input type="file" accept="application/pdf,.pdf" onChange={(e) => form.setData('archivo', e.target.files?.[0] ?? null)} />
            </label>
            <Errors errors={form.errors} />
            <Button disabled={form.processing}>Enviar respuesta</Button>
        </form>
    );
}
function Revisar({ id, respuesta }: { id: number; respuesta: Respuesta }) {
    const form = useForm({ estado: 'Atendida', observaciones: '', revision: respuesta.revision });
    return (
        <form
            className="mt-4 space-y-3 border-t pt-4"
            onSubmit={(e) => {
                e.preventDefault();
                form.post(route('correspondencia.review', [id, respuesta.id_respuesta]), { preserveScroll: true });
            }}
        >
            <label>
                Resultado
                <select
                    className="bg-background ml-3 rounded-md border p-2"
                    value={form.data.estado}
                    onChange={(e) => form.setData('estado', e.target.value)}
                >
                    <option>Atendida</option>
                    <option>Por corregir</option>
                </select>
            </label>
            <label className="block">
                Observaciones
                <textarea
                    className="bg-background block w-full rounded-md border p-2"
                    required={form.data.estado === 'Por corregir'}
                    value={form.data.observaciones}
                    onChange={(e) => form.setData('observaciones', e.target.value)}
                />
            </label>
            <Errors errors={form.errors} />
            <Button disabled={form.processing}>Guardar revisión</Button>
        </form>
    );
}
export default function CorrespondenciaDetalle({ documento: d, isAdmin, canRespond, status, destinatarios, archivos, respuestas, historial }: Props) {
    const action = useForm({ revision: d.revision });
    const seguimiento = useForm({
        revision: d.revision,
        estado: d.estado === 'Atendida' ? 'En seguimiento' : d.estado === 'Borrador' ? 'Cancelada' : 'Atendida',
        nota: '',
    });
    const open = d.estado === 'En seguimiento';
    const states = d.estado === 'Atendida' ? ['En seguimiento'] : d.estado === 'Borrador' ? ['Cancelada'] : ['Atendida', 'Cancelada'];
    const nextState = states.includes(seguimiento.data.estado) ? seguimiento.data.estado : states[0];
    return (
        <AppLayout breadcrumbs={[{ title: 'Correspondencia', href: route('correspondencia.index') }]}>
            <Head title={d.asunto} />
            <div className="mx-auto w-full max-w-5xl space-y-6 p-6">
                <header>
                    <p className="text-muted-foreground text-sm">
                        Registro interno COR-{d.id_correspondencia} · {d.direccion} · {d.estado}
                    </p>
                    <h1 className="mt-2 text-2xl font-semibold">{d.asunto}</h1>
                </header>
                {status && (
                    <p role="status" className="bg-muted rounded-lg p-4">
                        {status}
                    </p>
                )}
                <Errors errors={action.errors} />
                <section className="grid gap-3 rounded-xl border p-5 sm:grid-cols-2">
                    <p>
                        <strong>Oficio:</strong> {d.numero_oficio ?? 'Sin número'}
                    </p>
                    <p>
                        <strong>Remitente:</strong> {d.remitente}
                    </p>
                    <p>
                        <strong>Fecha del documento:</strong> {d.fecha_documento}
                    </p>
                    <p>
                        <strong>Recepción/emisión:</strong> {d.fecha_movimiento}
                    </p>
                    {d.destinatario_externo && (
                        <p>
                            <strong>Contraparte externa:</strong> {d.destinatario_externo}
                        </p>
                    )}
                    <p>
                        <strong>Respuesta:</strong> {d.requiere_respuesta ? 'Requerida' : 'No requerida'}
                        {d.fecha_limite && ` · Fecha límite: ${d.fecha_limite}`}
                    </p>
                    {d.descripcion && <p className="whitespace-pre-wrap sm:col-span-2">{d.descripcion}</p>}
                </section>
                <section className="space-y-3">
                    <h2 className="text-lg font-semibold">Archivos</h2>
                    {archivos.map((a) => (
                        <a
                            className="hover:border-primary block rounded-xl border p-4"
                            key={a.id_archivo}
                            href={route('correspondencia.download', [d.id_correspondencia, a.id_archivo])}
                        >
                            {a.clase} · {a.nombre_archivo} {a.id_adjunto_incidencia ? '(vinculado a incidencia)' : ''} · Descargar PDF
                        </a>
                    ))}
                </section>
                {isAdmin && d.estado === 'Borrador' && (
                    <section className="space-y-3 rounded-xl border p-5">
                        <p>Revisa el documento y los destinatarios. La publicación habilita su consulta y fija esta información.</p>
                        <div className="flex flex-wrap gap-3">
                            <Button asChild variant="outline">
                                <Link href={route('correspondencia.edit', d.id_correspondencia)}>Editar borrador</Link>
                            </Button>
                            <Button
                                disabled={action.processing}
                                onClick={() => {
                                    action.setData('revision', d.revision);
                                    action.transform(() => ({ revision: d.revision }));
                                    action.post(route('correspondencia.publish', d.id_correspondencia));
                                }}
                            >
                                Publicar
                            </Button>
                            <Button
                                variant="destructive"
                                disabled={action.processing}
                                onClick={() => {
                                    if (window.confirm('¿Eliminar este borrador?')) {
                                        action.transform(() => ({ revision: d.revision }));
                                        action.delete(route('correspondencia.destroy', d.id_correspondencia));
                                    }
                                }}
                            >
                                Eliminar borrador
                            </Button>
                        </div>
                    </section>
                )}
                <section className="space-y-3">
                    <h2 className="text-lg font-semibold">{isAdmin ? 'Destinatarios y recepción' : 'Mi recepción'}</h2>
                    {!destinatarios.length && <p>Registro de correspondencia externa, sin entrega a pasantes.</p>}
                    {destinatarios.map((p) => (
                        <div className="space-y-3 rounded-xl border p-4" key={p.id_destinatario}>
                            <p className="font-medium">
                                {p.nombre} {p.ap} {p.am} · Participación #{p.id_personal}
                            </p>
                            <p className="text-sm">
                                {p.confirmado_at
                                    ? `Recepción confirmada: ${p.confirmado_at}`
                                    : p.consultado_at
                                      ? 'Consultado · pendiente de confirmar recepción'
                                      : 'Pendiente de recepción'}
                            </p>
                            {!isAdmin && canRespond && !p.confirmado_at && d.estado !== 'Cancelada' && (
                                <Confirmar id={d.id_correspondencia} destinatario={p} />
                            )}
                        </div>
                    ))}
                    {!isAdmin && (
                        <p className="text-muted-foreground text-sm">
                            Abrir el documento no confirma la recepción. La confirmación es un registro interno del sistema.
                        </p>
                    )}
                </section>
                <section className="space-y-3">
                    <h2 className="text-lg font-semibold">Respuestas</h2>
                    {!respuestas.length && <p className="text-muted-foreground">Aún no hay respuestas registradas.</p>}
                    {respuestas.map((r) => (
                        <article className="rounded-xl border p-5" key={r.id_respuesta}>
                            <p className="font-semibold">
                                Respuesta #{r.id_respuesta} · {r.estado}
                            </p>
                            <p className="text-muted-foreground text-sm">
                                {r.created_at} ·{' '}
                                {r.id_destinatario
                                    ? (() => {
                                          const p = destinatarios.find((p) => p.id_destinatario === r.id_destinatario);
                                          return p ? `${p.nombre} ${p.ap} · participación #${p.id_personal}` : 'Destinatario';
                                      })()
                                    : 'Contraparte externa'}
                            </p>
                            <p className="mt-3 whitespace-pre-wrap">{r.comentario}</p>
                            {r.nombre_archivo && (
                                <a
                                    className="mt-3 block underline"
                                    href={route('correspondencia.downloadResponse', [d.id_correspondencia, r.id_respuesta])}
                                >
                                    Descargar {r.nombre_archivo}
                                </a>
                            )}
                            {r.observaciones && <p className="bg-muted mt-3 rounded-md p-3">Observaciones: {r.observaciones}</p>}
                            {isAdmin && open && r.id_destinatario && r.estado === 'Pendiente' && (
                                <Revisar key={`${r.id_respuesta}-${r.revision}`} id={d.id_correspondencia} respuesta={r} />
                            )}
                        </article>
                    ))}
                </section>
                {canRespond &&
                    open &&
                    (isAdmin
                        ? d.destinatario_externo && <Responder id={d.id_correspondencia} />
                        : !!d.requiere_respuesta &&
                          destinatarios
                              .filter(
                                  (p) =>
                                      p.confirmado_at &&
                                      (!respuestas.find((r) => r.id_destinatario === p.id_destinatario) ||
                                          respuestas.find((r) => r.id_destinatario === p.id_destinatario)?.estado === 'Por corregir'),
                              )
                              .map((p) => <Responder key={p.id_destinatario} id={d.id_correspondencia} destinatario={p} />))}
                {isAdmin && d.estado !== 'Cancelada' && (
                    <form
                        className="space-y-3 rounded-xl border p-5"
                        onSubmit={(e) => {
                            e.preventDefault();
                            seguimiento.transform((data) => ({ ...data, estado: nextState, revision: d.revision }));
                            seguimiento.post(route('correspondencia.transition', d.id_correspondencia));
                        }}
                    >
                        <h2 className="font-semibold">Seguimiento administrativo</h2>
                        <label>
                            Nuevo estado
                            <select
                                className="bg-background ml-3 rounded-md border p-2"
                                value={nextState}
                                onChange={(e) => seguimiento.setData('estado', e.target.value)}
                            >
                                {(d.estado === 'Atendida'
                                    ? ['En seguimiento']
                                    : d.estado === 'Borrador'
                                      ? ['Cancelada']
                                      : ['Atendida', 'Cancelada']
                                ).map((s) => (
                                    <option key={s}>{s}</option>
                                ))}
                            </select>
                        </label>
                        <label className="block">
                            Motivo
                            <textarea
                                required
                                className="bg-background block w-full rounded-md border p-2"
                                value={seguimiento.data.nota}
                                onChange={(e) => seguimiento.setData('nota', e.target.value)}
                            />
                        </label>
                        <Errors errors={seguimiento.errors} />
                        <Button disabled={seguimiento.processing}>Guardar seguimiento</Button>
                    </form>
                )}
                {isAdmin && (
                    <section className="space-y-3">
                        <h2 className="font-semibold">Historial</h2>
                        {historial.map((h) => (
                            <div className="border-l-2 pl-4 text-sm" key={h.id_historial}>
                                <p className="font-medium">{h.evento}</p>
                                <p>{h.nota}</p>
                                <p className="text-muted-foreground">
                                    {h.autor} · {h.created_at}
                                </p>
                            </div>
                        ))}
                    </section>
                )}
            </div>
        </AppLayout>
    );
}
