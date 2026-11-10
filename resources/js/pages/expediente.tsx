import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useRef } from 'react';
import { personName, type Participation } from './expedientes';

type FileRecord = { id_archivo_expediente: number; nombre_archivo: string; tamano: number; created_at: string };
type Document = { id_tipo_expediente: number; nombre: string; observaciones: string | null; archivos: FileRecord[] };
type Props = { personal: Participation; documentos: Document[]; canUpload: boolean; canDelete: boolean; canReview: boolean; status?: string };
export default function Expediente({ personal, documentos, canUpload, canDelete, canReview, status }: Props) {
    const delivered = documentos.filter((document) => document.archivos.length > 0).length;
    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Expediente digital', href: route('expedientes.index') },
                { title: personName(personal), href: '#' },
            ]}
        >
            <Head title="Expediente digital" />
            <div className="mx-auto w-full max-w-7xl space-y-6 p-5 md:p-8">
                <header className="space-y-2">
                    <h1 className="text-2xl font-semibold">Expediente digital</h1>
                    <p className="font-medium">{personName(personal)}</p>
                    <p className="text-muted-foreground text-sm">
                        Participación #{personal.id_personal} · {personal.fecha_ingreso?.slice(0, 10)} —{' '}
                        {personal.fecha_termino?.slice(0, 10) ?? 'Sin término'}
                    </p>
                </header>
                {status && (
                    <p role="status" className="rounded-lg border border-emerald-500/40 bg-emerald-500/10 p-4">
                        {status}
                    </p>
                )}
                <section className="bg-card space-y-3 rounded-xl border p-5">
                    <p>
                        <strong>{delivered} de 10</strong> conceptos con archivos entregados
                    </p>
                    <progress aria-label="Conceptos con archivos entregados" value={delivered} max={10} className="accent-primary h-2 w-full" />
                    <p className="text-muted-foreground text-sm">
                        {canReview
                            ? 'Sí indica que hay archivos cargados; No indica que todavía no hay archivos.'
                            : 'El estado indica si ya cargaste algún archivo para cada documento.'}{' '}
                        La carga de archivos no representa aprobación del expediente.
                    </p>
                    {canUpload && (
                        <p className="text-sm">
                            Sube PDF o escaneos en JPG/PNG, de hasta 10 MB por archivo. Puedes agregar varios archivos por documento. Verifica que
                            sean legibles.
                        </p>
                    )}
                </section>
                <div className="overflow-x-auto rounded-xl border">
                    <table className={`w-full text-left text-sm ${canReview ? 'min-w-[850px]' : 'min-w-[580px]'}`}>
                        <caption className="sr-only">Control documental del expediente</caption>
                        <thead className="bg-muted">
                            <tr>
                                {(canReview
                                    ? ['No.', 'Documento y archivos', 'Sí', 'No', 'Observaciones']
                                    : ['No.', 'Documento y archivos', 'Estado']
                                ).map((title) => (
                                    <th key={title} scope="col" className="p-4">
                                        {title}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {documentos.map((document) => (
                                <DocumentRow
                                    key={document.id_tipo_expediente}
                                    document={document}
                                    personal={personal.id_personal}
                                    canUpload={canUpload}
                                    canDelete={canDelete}
                                    canReview={canReview}
                                />
                            ))}
                        </tbody>
                    </table>
                </div>
                <Button variant="outline" asChild>
                    <Link href={route('expedientes.index')}>Volver a expedientes</Link>
                </Button>
            </div>
        </AppLayout>
    );
}
function DocumentRow({
    document,
    personal,
    canUpload,
    canDelete,
    canReview,
}: {
    document: Document;
    personal: number;
    canUpload: boolean;
    canDelete: boolean;
    canReview: boolean;
}) {
    const form = useForm<{ archivo: File | null }>({ archivo: null });
    const observation = useForm({ observaciones: document.observaciones ?? '' });
    const input = useRef<HTMLInputElement>(null);
    return (
        <tr className="align-top">
            <td className="p-4">{document.id_tipo_expediente}</td>
            <th scope="row" className="w-1/2 space-y-4 p-4 font-normal">
                <p className="font-semibold">{document.nombre}</p>
                <ul className="space-y-3">
                    {document.archivos.map((file) => (
                        <li key={file.id_archivo_expediente} className="rounded-lg border p-3">
                            <a
                                className="text-primary break-all underline"
                                href={route('expedientes.download', [personal, file.id_archivo_expediente])}
                            >
                                Descargar: {file.nombre_archivo}
                            </a>
                            <p className="text-muted-foreground mt-1 text-xs">
                                {Math.ceil(file.tamano / 1024)} KB · {file.created_at}
                            </p>
                            {canDelete && <RemoveFile personal={personal} file={file} />}
                        </li>
                    ))}
                </ul>
                {canUpload && (
                    <form
                        className="space-y-2"
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.post(route('expedientes.store', [personal, document.id_tipo_expediente]), {
                                forceFormData: true,
                                preserveScroll: true,
                                onSuccess: () => {
                                    form.reset();
                                    if (input.current) input.current.value = '';
                                },
                            });
                        }}
                    >
                        <label className="text-xs" htmlFor={'archivo-' + document.id_tipo_expediente}>
                            Agregar archivo a {document.nombre}
                        </label>
                        <Input
                            ref={input}
                            id={'archivo-' + document.id_tipo_expediente}
                            type="file"
                            accept=".pdf,.jpg,.jpeg,.png"
                            disabled={form.processing}
                            onChange={(event) => {
                                const file = event.target.files?.[0] ?? null;
                                form.clearErrors();
                                if (file && file.size > 10 * 1024 * 1024) {
                                    form.setError('archivo', 'Cada archivo debe pesar como máximo 10 MB.');
                                    form.setData('archivo', null);
                                    return;
                                }
                                form.setData('archivo', file);
                            }}
                        />
                        <InputError message={form.errors.archivo} />
                        {form.progress && <p role="status">Subiendo: {form.progress.percentage}%</p>}
                        <Button size="sm" disabled={form.processing || !form.data.archivo}>
                            {form.processing ? 'Subiendo…' : 'Subir archivo'}
                        </Button>
                    </form>
                )}
            </th>
            {canReview ? (
                <>
                    <td className="p-4 text-center" aria-label={document.archivos.length ? 'Entregado' : 'Sin entrega'}>
                        {document.archivos.length ? '✓' : '—'}
                    </td>
                    <td className="p-4 text-center">{document.archivos.length ? '—' : '✓'}</td>
                    <td className="min-w-64 p-4">
                        <form
                            className="space-y-2"
                            onSubmit={(event) => {
                                event.preventDefault();
                                observation.put(route('expedientes.observations', [personal, document.id_tipo_expediente]), { preserveScroll: true });
                            }}
                        >
                            <label className="sr-only" htmlFor={'observacion-' + document.id_tipo_expediente}>
                                Observaciones: {document.nombre}
                            </label>
                            <textarea
                                id={'observacion-' + document.id_tipo_expediente}
                                className="bg-background w-full rounded-md border p-3 text-sm"
                                rows={4}
                                maxLength={2000}
                                value={observation.data.observaciones}
                                onChange={(event) => observation.setData('observaciones', event.target.value)}
                            />
                            <InputError message={observation.errors.observaciones} />
                            <Button size="sm" variant="outline" disabled={observation.processing}>
                                {observation.processing ? 'Guardando…' : 'Guardar observación'}
                            </Button>
                            {observation.recentlySuccessful && (
                                <p role="status" className="text-xs">
                                    Observación guardada
                                </p>
                            )}
                        </form>
                    </td>
                </>
            ) : (
                <td className="p-4">
                    <span
                        className={`inline-flex rounded-full border px-3 py-1 text-xs font-medium whitespace-nowrap ${document.archivos.length ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300' : 'bg-muted text-muted-foreground'}`}
                    >
                        {document.archivos.length ? 'Archivo cargado' : 'Sin archivos'}
                    </span>
                </td>
            )}
        </tr>
    );
}
function RemoveFile({ personal, file }: { personal: number; file: FileRecord }) {
    const form = useForm<{ archivo: string }>({ archivo: '' });
    return (
        <form
            className="mt-2"
            onSubmit={(event) => {
                event.preventDefault();
                if (window.confirm('¿Retirar este archivo del expediente? Podrás subir el documento correcto después.'))
                    form.delete(route('expedientes.destroy', [personal, file.id_archivo_expediente]), { preserveScroll: true });
            }}
        >
            <Button size="sm" variant="outline" disabled={form.processing}>
                Retirar archivo
            </Button>
            <InputError message={form.errors.archivo} />
        </form>
    );
}
