import { CatalogReference } from '@/components/catalog-reference';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';

type RecordData = Record<string, string | number | null>;
type Props = {
    personal: RecordData & { id_personal: number; id_persona: number; persona: { nombre: string; ap: string; am: string | null } };
    academicos: RecordData | null;
};
const references = [
    ['id_tipo_personal', 'Tipo de personal', 'tipos_personal'],
    ['id_institucion', 'Institución educativa', 'instituciones'],
    ['id_carrera', 'Carrera', 'carreras'],
    ['id_nivel_academico', 'Nivel académico', 'nivel_academico'],
] as const;
const fields = [
    ['fecha_ingreso', 'Inicio de la participación', 'date'],
    ['fecha_termino', 'Término de la participación', 'date'],
    ['matricula', 'Matrícula', 'text'],
    ['promedio', 'Promedio (escala de la institución)', 'number'],
] as const;

export default function PersonalForm(props: Props) {
    return <Editor key={props.personal.id_personal} {...props} />;
}

function Editor({ personal, academicos }: Props) {
    const source = { ...personal, ...academicos };
    const form = useForm<Record<string, string>>(Object.fromEntries([...references, ...fields].map(([key]) => [key, String(source[key] ?? '')])));
    const name = [personal.persona.nombre, personal.persona.ap, personal.persona.am].filter(Boolean).join(' ');
    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Personal en formación', href: route('personal.index') },
                { title: 'Completar participación', href: '#' },
            ]}
        >
            <Head title="Completar participación" />
            <div className="mx-auto w-full max-w-4xl space-y-6 p-5 md:p-8">
                <div>
                    <h1 className="text-2xl font-semibold">Identificación del prestador</h1>
                    <p className="text-muted-foreground mt-2">
                        {name} · Participación #{personal.id_personal}
                    </p>
                </div>
                <section className="space-y-3 rounded-xl border p-5">
                    <h2 className="font-semibold">Datos personales</h2>
                    <p className="text-muted-foreground text-sm">
                        Nombre, nacimiento, género, nacionalidad, CURP, RFC, domicilio y contacto se mantienen en la ficha de la persona.
                    </p>
                    <Button variant="outline" asChild>
                        <Link href={route('personas.edit', personal.id_persona)}>Editar identificación y domicilio</Link>
                    </Button>
                </section>
                <form
                    noValidate
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.put(route('personal.update', personal.id_personal));
                    }}
                    className="space-y-6"
                >
                    <fieldset disabled={form.processing} className="rounded-xl border p-5">
                        <legend className="px-2 font-semibold">Participación y formación académica</legend>
                        <div className="grid gap-5 sm:grid-cols-2">
                            {references.map(([key, label, catalogo]) => (
                                <div key={key}>
                                    <CatalogReference
                                        id={key}
                                        label={label}
                                        catalogo={catalogo}
                                        value={form.data[key]}
                                        onChange={(value) => form.setData(key, value)}
                                        required
                                        invalid={!!form.errors[key]}
                                    />
                                    <InputError id={key + '-error'} message={form.errors[key]} />
                                </div>
                            ))}
                            {fields.map(([key, label, type]) => (
                                <div key={key} className="space-y-2">
                                    <label htmlFor={key} className="text-sm font-medium">
                                        {label}
                                        {['fecha_ingreso', 'matricula'].includes(key) ? ' *' : ''}
                                    </label>
                                    <Input
                                        id={key}
                                        type={type}
                                        value={form.data[key]}
                                        maxLength={100}
                                        step={type === 'number' ? '0.01' : undefined}
                                        min={type === 'number' ? 0 : undefined}
                                        max={type === 'number' ? 100 : undefined}
                                        onChange={(event) => form.setData(key, event.target.value)}
                                        aria-invalid={!!form.errors[key]}
                                        aria-describedby={key + '-error'}
                                    />
                                    <InputError id={key + '-error'} message={form.errors[key]} />
                                </div>
                            ))}
                        </div>
                    </fieldset>
                    {form.hasErrors && (
                        <p role="alert" className="text-sm text-red-600">
                            Revisa los campos señalados antes de guardar.
                        </p>
                    )}
                    <div className="flex flex-wrap gap-3">
                        <Button disabled={form.processing}>{form.processing ? 'Guardando…' : 'Guardar participación'}</Button>
                        <Button variant="outline" asChild>
                            <Link href={route('personal.show', personal.id_personal)}>Volver al detalle</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
