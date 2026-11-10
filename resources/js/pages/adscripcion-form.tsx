import { CatalogReference } from '@/components/catalog-reference';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';

type Props = {
    personal: {
        id_personal: number;
        fecha_ingreso: string | null;
        fecha_termino: string | null;
        persona: { nombre: string; ap: string; am: string | null };
    };
    record: (Record<string, string | number | null> & { id_adscripcion: number }) | null;
};
const references = [
    ['id_unidad', 'Unidad médica', 'unidades_medicas'],
    ['id_programa', 'Programa', 'programas'],
    ['id_vertiente', 'Vertiente', 'vertientes'],
    ['id_tipo_campo_clinico', 'Tipo de campo clínico', 'tipos_campos_clinicos'],
    ['id_fuente_financiamiento_beca', 'Beca financiada por', 'fuentes_financiamiento_beca'],
] as const;
const fields = [
    ['numero_campo_clinico', 'Número de campo clínico', 'text'],
    ['fecha_inicio', 'Inicio de adscripción', 'date'],
    ['fecha_fin', 'Fin de adscripción', 'date'],
    ['motivo_fin', 'Motivo de cierre', 'text'],
] as const;

export default function AdscripcionForm(props: Props) {
    return <Editor key={props.record?.id_adscripcion ?? 'new'} {...props} />;
}

function Editor({ personal, record }: Props) {
    const form = useForm<Record<string, string>>({
        ...Object.fromEntries([...references, ...fields].map(([key]) => [key, String(record?.[key] ?? '')])),
        fecha_inicio: String(record?.fecha_inicio ?? personal.fecha_ingreso ?? ''),
    });
    const title = record ? 'Editar o cerrar adscripción' : 'Registrar adscripción';
    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Personal en formación', href: route('personal.index') },
                { title, href: '#' },
            ]}
        >
            <Head title={title} />
            <div className="mx-auto w-full max-w-4xl space-y-6 p-5 md:p-8">
                <h1 className="text-2xl font-semibold">{title}</h1>
                <p>{[personal.persona.nombre, personal.persona.ap, personal.persona.am].filter(Boolean).join(' ')}</p>
                <p className="text-muted-foreground text-sm">
                    La unidad aporta su CLUES, institución de salud, jurisdicción y ubicación. Para cambiar de unidad, cierra la adscripción anterior
                    y registra una nueva con fechas que no se superpongan.
                </p>
                <form
                    noValidate
                    className="space-y-6"
                    onSubmit={(event) => {
                        event.preventDefault();
                        if (record) form.put(route('adscripciones.update', [personal.id_personal, record.id_adscripcion]));
                        else form.post(route('adscripciones.store', personal.id_personal));
                    }}
                >
                    <fieldset disabled={form.processing} className="rounded-xl border p-5">
                        <legend className="px-2 font-semibold">Campo clínico de adscripción</legend>
                        <div className="grid gap-5 sm:grid-cols-2">
                            {references.map(([key, label, catalogo]) => (
                                <div key={key}>
                                    <CatalogReference
                                        id={key}
                                        label={label}
                                        catalogo={catalogo}
                                        value={form.data[key]}
                                        onChange={(value) => form.setData(key, value)}
                                        required={key === 'id_unidad'}
                                        invalid={!!form.errors[key]}
                                    />
                                    <InputError id={key + '-error'} message={form.errors[key]} />
                                </div>
                            ))}
                            {fields.map(([key, label, type]) => (
                                <div key={key} className="space-y-2">
                                    <label htmlFor={key} className="text-sm font-medium">
                                        {label}
                                        {key === 'fecha_inicio' ? ' *' : ''}
                                    </label>
                                    <Input
                                        id={key}
                                        type={type}
                                        value={form.data[key]}
                                        maxLength={key === 'motivo_fin' ? 2000 : 100}
                                        onChange={(event) => form.setData(key, event.target.value)}
                                        aria-invalid={!!form.errors[key]}
                                        aria-describedby={key + '-error'}
                                    />
                                    <InputError id={key + '-error'} message={form.errors[key]} />
                                </div>
                            ))}
                        </div>
                        <p className="text-muted-foreground mt-5 text-sm">
                            El número de campo clínico es el identificador del documento de asignación. Si aún no cuentas con programa, vertiente,
                            tipo de campo o financiamiento confirmado, puedes completarlos después.
                        </p>
                    </fieldset>
                    {form.hasErrors && (
                        <p role="alert" className="text-sm text-red-600">
                            Revisa los campos señalados antes de guardar.
                        </p>
                    )}
                    <div className="flex flex-wrap gap-3">
                        <Button disabled={form.processing}>{form.processing ? 'Guardando…' : 'Guardar adscripción'}</Button>
                        <Button variant="outline" asChild>
                            <Link href={route('personal.show', personal.id_personal)}>Cancelar</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
