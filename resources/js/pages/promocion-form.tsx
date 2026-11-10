import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import type { Promotion } from './promociones';

type Props = { initial: (Promotion & { carreras: number[] }) | null; carreras: { id_carrera: number; nombre: string }[] };
export default function PromocionForm(props: Props) {
    return <Editor key={`${props.initial?.id_promocion}-${props.initial?.revision}`} {...props} />;
}
function Editor({ initial, carreras }: Props) {
    const form = useForm({
        nombre: initial?.nombre ?? '',
        fecha_inicio: initial?.fecha_inicio ?? '',
        fecha_fin: initial?.fecha_fin ?? '',
        estado: initial?.estado ?? 'Programada',
        permite_inscripciones: !!(initial?.permite_inscripciones ?? true),
        carreras: initial?.carreras ?? ([] as number[]),
        revision: initial?.revision ?? 0,
    });
    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Promociones', href: route('promociones.index') },
                { title: initial ? 'Editar' : 'Nueva promoción', href: '#' },
            ]}
        >
            <Head title={initial ? 'Editar promoción' : 'Nueva promoción'} />
            <div className="mx-auto w-full max-w-3xl space-y-6 p-5 md:p-8">
                <header>
                    <h1 className="text-2xl font-semibold">{initial ? 'Editar promoción' : 'Nueva promoción'}</h1>
                    <p className="text-muted-foreground mt-2">Define el periodo programado y las carreras autorizadas por Enseñanza.</p>
                </header>
                <form
                    className="bg-card space-y-6 rounded-xl border p-6"
                    onSubmit={(e) => {
                        e.preventDefault();
                        if (initial) form.put(route('promociones.update', initial.id_promocion));
                        else form.post(route('promociones.store'));
                    }}
                >
                    <fieldset disabled={form.processing} className="space-y-5">
                        <div className="space-y-2">
                            <label htmlFor="nombre">Nombre de la promoción *</label>
                            <Input
                                id="nombre"
                                maxLength={150}
                                required
                                placeholder="Ej. Agosto 2026 – julio 2027"
                                value={form.data.nombre}
                                onChange={(e) => form.setData('nombre', e.target.value)}
                            />
                            <InputError message={form.errors.nombre} />
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2">
                            {(['fecha_inicio', 'fecha_fin'] as const).map((key) => (
                                <div className="space-y-2" key={key}>
                                    <label htmlFor={key}>{key === 'fecha_inicio' ? 'Inicio programado' : 'Término programado'} *</label>
                                    <Input id={key} type="date" required value={form.data[key]} onChange={(e) => form.setData(key, e.target.value)} />
                                    <InputError message={form.errors[key]} />
                                </div>
                            ))}
                        </div>
                        <p className="text-muted-foreground text-sm">
                            Cuando haya participantes, estas fechas se conservarán. Las prórrogas se registran en el periodo individual del pasante.
                        </p>
                        <div className="space-y-2">
                            <label htmlFor="estado">Estado *</label>
                            <select
                                id="estado"
                                className="bg-background block w-full rounded-md border p-2"
                                value={form.data.estado}
                                onChange={(e) => {
                                    form.setData('estado', e.target.value);
                                    if (e.target.value === 'Cerrada') form.setData('permite_inscripciones', false);
                                }}
                            >
                                {['Programada', 'En curso', 'Cerrada'].map((s) => (
                                    <option key={s}>{s}</option>
                                ))}
                            </select>
                            <InputError message={form.errors.estado} />
                        </div>
                        <label className="flex items-center gap-3 text-sm">
                            <input
                                type="checkbox"
                                checked={form.data.permite_inscripciones}
                                disabled={form.data.estado === 'Cerrada'}
                                onChange={(e) => form.setData('permite_inscripciones', e.target.checked)}
                            />
                            Permitir nuevas incorporaciones
                        </label>
                        {form.data.estado === 'Cerrada' && (
                            <p className="rounded-lg border border-amber-500/30 p-3 text-sm">
                                La promoción pasará al historial. Sus pasantes conservarán sus fechas y estado; cerrar no significa liberarlos ni
                                bloquear sus documentos.
                            </p>
                        )}
                        <fieldset className="space-y-3">
                            <legend className="font-medium">Carreras participantes *</legend>
                            <p className="text-muted-foreground text-sm">Una misma carrera puede participar en distintas promociones.</p>
                            <div className="grid max-h-72 gap-3 overflow-y-auto rounded-lg border p-4 sm:grid-cols-2">
                                {carreras.map((c) => (
                                    <label key={c.id_carrera} className="flex items-start gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            className="mt-1"
                                            checked={form.data.carreras.includes(c.id_carrera)}
                                            onChange={(e) =>
                                                form.setData(
                                                    'carreras',
                                                    e.target.checked
                                                        ? [...form.data.carreras, c.id_carrera]
                                                        : form.data.carreras.filter((id) => id !== c.id_carrera),
                                                )
                                            }
                                        />
                                        {c.nombre}
                                    </label>
                                ))}
                            </div>
                            {!carreras.length && <p>Registra primero las carreras en Catálogos.</p>}
                            <InputError message={form.errors.carreras} />
                            {Object.entries(form.errors)
                                .filter(([key]) => key.startsWith('carreras.'))
                                .map(([key, error]) => (
                                    <InputError key={key} message={error} />
                                ))}
                        </fieldset>
                    </fieldset>
                    <InputError message={form.errors.revision} />
                    <div className="flex gap-3">
                        <Button disabled={form.processing}>{form.processing ? 'Guardando…' : 'Guardar promoción'}</Button>
                        <Button variant="outline" asChild>
                            <Link href={initial ? route('promociones.show', initial.id_promocion) : route('promociones.index')}>Cancelar</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
