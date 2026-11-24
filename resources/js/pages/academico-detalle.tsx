import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';

type Version = {
    id_documento: number;
    id_personal: number;
    id_tipo_documento: number;
    titulo: string | null;
    nombre_archivo: string;
    version: number;
    estado: string;
    revision: number;
    created_at: string;
};
type Observation = { id_revision_doc: number; id_documento: number; observacion: string; estado: string; fecha_observacion: string; revisor: string };
function Review({ documento: d }: { documento: Version }) {
    const form = useForm({ estado: 'Por corregir', observacion: '', revision: d.revision });
    return (
        <form
            className="space-y-4 rounded-xl border p-5"
            onSubmit={(e) => {
                e.preventDefault();
                form.post(route('academico.review', d.id_documento), { preserveScroll: true });
            }}
        >
            <h2 className="text-lg font-semibold">Revisar versión {d.version}</h2>
            <p className="text-muted-foreground text-sm">
                Descarga y revisa el archivo antes de registrar tu decisión. El resultado se conservará en el historial.
            </p>
            <label className="block">
                Resultado
                <select
                    className="bg-background ml-3 rounded-md border p-2"
                    value={form.data.estado}
                    onChange={(e) => form.setData('estado', e.target.value)}
                >
                    <option>Por corregir</option>
                    <option>Aprobado</option>
                </select>
            </label>
            <label className="block">
                Observaciones *
                <textarea
                    required
                    maxLength={5000}
                    rows={4}
                    className="bg-background mt-2 block w-full rounded-md border p-3"
                    value={form.data.observacion}
                    onChange={(e) => form.setData('observacion', e.target.value)}
                />
            </label>
            {Object.entries(form.errors).map(([k, error]) => (
                <InputError key={k} message={error} />
            ))}
            <Button disabled={form.processing}>Guardar revisión</Button>
        </form>
    );
}
function Correction({ documento: d }: { documento: Version }) {
    const form = useForm({
        id_personal: d.id_personal,
        id_tipo_documento: d.id_tipo_documento,
        id_documento_anterior: d.id_documento,
        titulo: d.titulo ?? d.nombre_archivo,
        revision: d.revision,
        archivo: null as File | null,
    });
    return (
        <form
            className="space-y-4 rounded-xl border p-5"
            onSubmit={(e) => {
                e.preventDefault();
                form.post(route('academico.store'), { forceFormData: true });
            }}
        >
            <h2 className="text-lg font-semibold">Entregar corrección</h2>
            <p className="text-muted-foreground text-sm">
                Se creará la versión {d.version + 1}. El archivo anterior y sus observaciones permanecerán disponibles.
            </p>
            <label className="block">
                Documento corregido PDF (máximo 10 MB)
                <Input type="file" required accept="application/pdf,.pdf" onChange={(e) => form.setData('archivo', e.target.files?.[0] ?? null)} />
            </label>
            {Object.entries(form.errors).map(([k, error]) => (
                <InputError key={k} message={error} />
            ))}
            {form.progress && <p role="status">Subiendo: {form.progress.percentage}%</p>}
            <Button disabled={form.processing}>Enviar nueva versión</Button>
        </form>
    );
}
export default function AcademicoDetalle({
    documento: d,
    versiones,
    observaciones,
    persona,
    tipo,
    canReview,
    canUpload,
    status,
}: {
    documento: Version;
    versiones: Version[];
    observaciones: Observation[];
    persona: string;
    tipo: string;
    canReview: boolean;
    canUpload: boolean;
    status?: string;
}) {
    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Repositorio académico', href: route('academico.index') },
                { title: 'Documento', href: '#' },
            ]}
        >
            <Head title={d.titulo ?? d.nombre_archivo} />
            <div className="mx-auto w-full max-w-4xl space-y-6 p-6">
                <header className="space-y-2">
                    <p className="text-muted-foreground text-sm">
                        {persona} · {tipo}
                    </p>
                    <h1 className="text-2xl font-semibold">{d.titulo ?? d.nombre_archivo}</h1>
                    <span className="bg-muted inline-block rounded-full px-3 py-1 text-sm">
                        Versión {d.version} · {d.estado}
                    </span>
                </header>
                {status && (
                    <p role="status" className="bg-muted rounded-lg p-4">
                        {status}
                    </p>
                )}
                <Button asChild>
                    <a href={route('academico.download', d.id_documento)}>Descargar versión actual</a>
                </Button>
                {d.estado === 'Pendiente de revisión' && !canReview && (
                    <p className="rounded-lg border p-4">Tu entrega está pendiente de revisión. Las observaciones aparecerán en esta página.</p>
                )}
                {d.estado === 'Aprobado' && (
                    <p className="rounded-lg border p-4">
                        Documento aprobado. Este resultado corresponde al trabajo académico; no constituye la liberación del servicio social.
                    </p>
                )}
                {canReview && <Review key={`${d.id_documento}-${d.revision}`} documento={d} />}
                {canUpload && <Correction key={`${d.id_documento}-${d.revision}`} documento={d} />}
                <section className="space-y-4">
                    <h2 className="text-lg font-semibold">Historial de entregas y revisiones</h2>
                    {versiones.map((v) => (
                        <article className="space-y-3 rounded-xl border p-5" key={v.id_documento}>
                            <div className="flex flex-wrap justify-between gap-3">
                                <h3 className="font-semibold">
                                    Versión {v.version} · {v.estado}
                                </h3>
                                <a className="underline" href={route('academico.download', v.id_documento)}>
                                    Descargar PDF
                                </a>
                            </div>
                            <p className="text-muted-foreground text-sm">
                                {v.nombre_archivo} · Entregado: {v.created_at}
                            </p>
                            {observaciones
                                .filter((o) => o.id_documento === v.id_documento)
                                .map((o) => (
                                    <div className="space-y-2 border-l-2 pl-4" key={o.id_revision_doc}>
                                        <p className="font-medium">{o.estado}</p>
                                        <p className="whitespace-pre-wrap">{o.observacion}</p>
                                        <p className="text-muted-foreground text-sm">
                                            {o.revisor} · {o.fecha_observacion}
                                        </p>
                                    </div>
                                ))}
                            {!observaciones.some((o) => o.id_documento === v.id_documento) && (
                                <p className="text-muted-foreground text-sm">Sin revisión registrada.</p>
                            )}
                        </article>
                    ))}
                </section>
                <Button asChild variant="outline">
                    <Link href={route('academico.index')}>Volver al repositorio</Link>
                </Button>
            </div>
        </AppLayout>
    );
}
