import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { personName, type Participation } from './expedientes';

export default function AcademicoForm({ personal, tipos }: { personal: Participation[]; tipos: { id_tipo_documento: number; nombre: string }[] }) {
    const form = useForm({
        id_personal: personal.length === 1 ? String(personal[0].id_personal) : '',
        id_tipo_documento: '',
        titulo: '',
        archivo: null as File | null,
    });
    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Repositorio académico', href: route('academico.index') },
                { title: 'Entregar documento', href: '#' },
            ]}
        >
            <Head title="Entregar documento académico" />
            <form
                className="mx-auto w-full max-w-3xl space-y-5 p-6"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(route('academico.store'), { forceFormData: true });
                }}
            >
                <h1 className="text-2xl font-semibold">Entregar documento académico</h1>
                <p className="text-muted-foreground">
                    Entrega el trabajo solicitado por Enseñanza. Si necesitas corregir una entrega anterior, abre su detalle para subir una nueva
                    versión.
                </p>
                {!personal.length && (
                    <p role="status" className="rounded-lg border p-4">
                        Tu cuenta no tiene un periodo en formación disponible. Solicita al administrador revisar la vinculación y el estado de tu
                        registro.
                    </p>
                )}
                {!tipos.length && <p role="status">Enseñanza debe registrar primero los tipos de documentos.</p>}
                {personal[0] && (
                    <div className="space-y-1">
                        <p className="font-medium">{personName(personal[0])}</p>
                        <p className="text-muted-foreground text-sm">
                            Periodo de servicio: {personal[0].fecha_ingreso?.slice(0, 10)} —{' '}
                            {personal[0].fecha_termino?.slice(0, 10) ?? 'Sin término'}
                        </p>
                    </div>
                )}
                <InputError message={form.errors.id_personal} />
                <label className="block">
                    Tipo de documento *
                    <select
                        className="bg-background mt-2 w-full rounded-md border p-3"
                        required
                        value={form.data.id_tipo_documento}
                        onChange={(e) => form.setData('id_tipo_documento', e.target.value)}
                    >
                        <option value="">Seleccionar</option>
                        {tipos.map((t) => (
                            <option key={t.id_tipo_documento} value={t.id_tipo_documento}>
                                {t.nombre}
                            </option>
                        ))}
                    </select>
                </label>
                <InputError message={form.errors.id_tipo_documento} />
                <label className="block">
                    Título del trabajo *
                    <Input required maxLength={255} value={form.data.titulo} onChange={(e) => form.setData('titulo', e.target.value)} />
                </label>
                <InputError message={form.errors.titulo} />
                <label className="block">
                    Documento PDF *
                    <Input
                        type="file"
                        required
                        accept="application/pdf,.pdf"
                        onChange={(e) => form.setData('archivo', e.target.files?.[0] ?? null)}
                    />
                </label>
                <p className="text-muted-foreground text-sm">Máximo 10 MB. El archivo estará disponible para su revisión dentro del sistema.</p>
                <InputError message={form.errors.archivo} />
                {form.progress && <p role="status">Subiendo: {form.progress.percentage}%</p>}
                <div className="flex gap-3">
                    <Button disabled={form.processing || !personal.length || !tipos.length}>Entregar para revisión</Button>
                    <Button asChild variant="outline">
                        <Link href={route('academico.index')}>Cancelar</Link>
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}
