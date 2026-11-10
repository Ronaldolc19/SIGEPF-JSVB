import { CatalogReference } from '@/components/catalog-reference';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { type CatalogDefinition, type CatalogRecord } from '@/types/catalogos';
import { Head, Link, useForm } from '@inertiajs/react';

interface Props {
    catalogo: string;
    definition: CatalogDefinition;
    record: CatalogRecord | null;
}
export default function CatalogForm(props: Props) {
    return <Editor key={`${props.catalogo}-${props.record?.[props.definition.key] ?? 'new'}`} {...props} />;
}
function Editor({ catalogo, definition, record }: Props) {
    const initial = Object.fromEntries(
        Object.keys(definition.fields).map((column) => [column, record?.[column] == null ? '' : String(record[column])]),
    );
    const form = useForm<Record<string, string>>(initial);
    const title = record ? 'Editar registro' : 'Nuevo registro';
    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Catálogos', href: route('catalogos.index') },
                { title: definition.title, href: route('catalogos.show', catalogo) },
            ]}
        >
            <Head title={`${title} · ${definition.title}`} />
            <div className="mx-auto w-full max-w-4xl space-y-6 p-5 md:p-8">
                <div>
                    <p className="text-sm font-medium text-[#722f42] dark:text-rose-300">{definition.title}</p>
                    <h1 className="mt-2 text-2xl font-semibold">{title}</h1>
                    <p className="text-muted-foreground mt-2 text-sm">Los campos con * son obligatorios.</p>
                </div>
                <form
                    noValidate
                    onSubmit={(event) => {
                        event.preventDefault();
                        if (record) form.put(route('catalogos.update', { catalogo, registro: record[definition.key] }));
                        else form.post(route('catalogos.store', catalogo));
                    }}
                    className="bg-card space-y-6 rounded-xl border p-5 md:p-7"
                >
                    <fieldset disabled={form.processing} className="grid gap-6 sm:grid-cols-2">
                        <legend className="sr-only">Datos del registro</legend>
                        {Object.entries(definition.fields).map(([column, field]) => (
                            <div key={column} className={Object.keys(definition.fields).length === 1 ? 'sm:col-span-2' : ''}>
                                {field.reference ? (
                                    <CatalogReference
                                        catalogo={field.reference}
                                        id={column}
                                        label={field.label}
                                        required={field.required}
                                        value={form.data[column]}
                                        invalid={Boolean(form.errors[column])}
                                        onChange={(value) => form.setData(column, value)}
                                    />
                                ) : (
                                    <>
                                        <label htmlFor={column} className="mb-2 block text-sm font-medium">
                                            {field.label}
                                            {field.required ? ' *' : ''}
                                        </label>
                                        <Input
                                            id={column}
                                            name={column}
                                            required={field.required}
                                            maxLength={field.max}
                                            inputMode={column === 'cp' ? 'numeric' : 'text'}
                                            value={form.data[column]}
                                            onChange={(event) => form.setData(column, event.target.value)}
                                            aria-invalid={Boolean(form.errors[column])}
                                            aria-describedby={form.errors[column] ? `${column}-error` : undefined}
                                        />
                                    </>
                                )}
                                <InputError className="mt-2" id={`${column}-error`} message={form.errors[column]} role="alert" />
                                {field.reference && (
                                    <Link
                                        className="mt-2 inline-block text-xs underline"
                                        href={route('catalogos.show', field.reference)}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        Administrar opciones en otra pestaña
                                    </Link>
                                )}
                            </div>
                        ))}
                    </fieldset>
                    <div className="flex flex-wrap gap-3 border-t pt-5">
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? 'Guardando…' : 'Guardar registro'}
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href={route('catalogos.show', catalogo)}>Cancelar</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
