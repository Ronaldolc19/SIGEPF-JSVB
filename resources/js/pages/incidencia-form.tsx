import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { personName, type Participation } from './expedientes';

export default function IncidenciaForm({
    personal,
    tipos,
    initial,
    incidenciaId,
    buscar,
}: {
    personal: Participation[];
    tipos: { id_tipo_incidencia: number; nombre: string; categoria: string }[];
    initial: Record<string, string | number | null>;
    incidenciaId: number | null;
    buscar: string;
}) {
    const form = useForm({
        id_personal: String(initial.id_personal ?? ''),
        id_tipo_incidencia: String(initial.id_tipo_incidencia ?? ''),
        fecha_hecho: String(initial.fecha_hecho ?? ''),
        fecha_fin: String(initial.fecha_fin ?? initial.fecha_hecho ?? ''),
        descripcion: String(initial.descripcion ?? ''),
        revision: Number(initial.revision ?? 0),
    });
    const search = useForm({ buscar });
    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Incidencias', href: route('incidencias.index') },
                { title: incidenciaId ? 'Corregir registro' : 'Registrar', href: '#' },
            ]}
        >
            <Head title="Registrar incidencia" />
            <div className="mx-auto w-full max-w-3xl space-y-6 p-5 md:p-8">
                <h1 className="text-2xl font-semibold">{incidenciaId ? 'Corregir incidencia registrada' : 'Registrar incidencia'}</h1>
                {!incidenciaId && (
                    <form
                        className="space-y-2 rounded-lg border p-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            search.get(route('incidencias.create'), { preserveState: true });
                        }}
                    >
                        <label htmlFor="buscar-personal" className="text-sm">
                            Localizar pasante (hasta 30 coincidencias)
                        </label>
                        <div className="flex gap-3">
                            <Input
                                id="buscar-personal"
                                value={search.data.buscar}
                                onChange={(event) => search.setData('buscar', event.target.value)}
                            />
                            <Button variant="outline">Buscar</Button>
                        </div>
                    </form>
                )}
                <form
                    className="space-y-5"
                    onSubmit={(event) => {
                        event.preventDefault();
                        if (incidenciaId) form.put(route('incidencias.update', incidenciaId));
                        else form.post(route('incidencias.store'));
                    }}
                >
                    <div>
                        <label htmlFor="personal">Pasante y participación *</label>
                        <select
                            id="personal"
                            className="bg-background mt-2 w-full rounded-md border p-3"
                            disabled={!!incidenciaId}
                            value={form.data.id_personal}
                            onChange={(event) => form.setData('id_personal', event.target.value)}
                        >
                            <option value="">Seleccionar</option>
                            {personal.map((person) => (
                                <option key={person.id_personal} value={person.id_personal}>
                                    {personName(person)} · Participación #{person.id_personal}
                                </option>
                            ))}
                        </select>
                        <InputError message={form.errors.id_personal} />
                    </div>
                    <div>
                        <label htmlFor="tipo">Tipo de incidencia *</label>
                        <select
                            id="tipo"
                            className="bg-background mt-2 w-full rounded-md border p-3"
                            value={form.data.id_tipo_incidencia}
                            onChange={(event) => form.setData('id_tipo_incidencia', event.target.value)}
                        >
                            <option value="">Seleccionar</option>
                            {tipos.map((type) => (
                                <option key={type.id_tipo_incidencia} value={type.id_tipo_incidencia}>
                                    {type.categoria} · {type.nombre}
                                </option>
                            ))}
                        </select>
                        <InputError message={form.errors.id_tipo_incidencia} />
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        {(['fecha_hecho', 'fecha_fin'] as const).map((key) => (
                            <div key={key}>
                                <label htmlFor={key}>{key === 'fecha_hecho' ? 'Fecha del hecho o inicio *' : 'Fecha de término *'}</label>
                                <Input id={key} type="date" value={form.data[key]} onChange={(event) => form.setData(key, event.target.value)} />
                                <InputError message={form.errors[key]} />
                            </div>
                        ))}
                    </div>
                    <p className="text-muted-foreground text-sm">
                        Para un solo día, usa la misma fecha. La unidad se toma de la adscripción correspondiente al inicio.
                    </p>
                    <div>
                        <label htmlFor="descripcion">Descripción de los hechos o motivo *</label>
                        <textarea
                            id="descripcion"
                            rows={5}
                            maxLength={5000}
                            className="bg-background mt-2 w-full rounded-md border p-3"
                            value={form.data.descripcion}
                            onChange={(event) => form.setData('descripcion', event.target.value)}
                        />
                        <InputError message={form.errors.descripcion} />
                    </div>
                    <p className="text-muted-foreground text-sm">
                        Describe hechos concretos y evita datos clínicos innecesarios. Al guardar podrás adjuntar respaldo y dar seguimiento.
                    </p>
                    <InputError message={form.errors.revision} />
                    <div className="flex gap-3">
                        <Button disabled={form.processing}>{form.processing ? 'Guardando…' : 'Guardar incidencia'}</Button>
                        <Button asChild variant="outline">
                            <Link href={incidenciaId ? route('incidencias.show', incidenciaId) : route('incidencias.index')}>Cancelar</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
