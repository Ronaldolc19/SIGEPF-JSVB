import { CatalogReference } from '@/components/catalog-reference';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { type CatalogDefinition, type CatalogRecord } from '@/types/catalogos';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';

interface Props {
    catalogo: string;
    definition: CatalogDefinition;
    records: {
        data: CatalogRecord[];
        total: number;
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    filters: Record<string, string | number | null>;
    status: string | null;
}
export default function CatalogList(props: Props) {
    return <Listing key={`${props.catalogo}-${JSON.stringify(props.filters)}`} {...props} />;
}
function Listing({ catalogo, definition, records, filters, status }: Props) {
    const [values, setValues] = useState<Record<string, string>>(
        Object.fromEntries(Object.entries(filters).map(([key, value]) => [key, String(value ?? '')])),
    );
    const [selected, setSelected] = useState<CatalogRecord | null>(null);
    const deletion = useForm<Record<string, string>>({});
    const fields = Object.entries(definition.fields);
    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Catálogos', href: route('catalogos.index') },
                { title: definition.title, href: route('catalogos.show', catalogo) },
            ]}
        >
            <Head title={definition.title} />
            <div className="mx-auto w-full max-w-7xl space-y-6 p-5 md:p-8">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p className="text-muted-foreground text-sm">{definition.group}</p>
                        <h1 className="mt-2 text-2xl font-semibold">{definition.title}</h1>
                    </div>
                    <Button asChild>
                        <Link href={route('catalogos.create', catalogo)}>
                            <Plus aria-hidden="true" />
                            Nuevo registro
                        </Link>
                    </Button>
                </div>
                {status && (
                    <p role="status" className="rounded-xl border border-emerald-500/30 bg-emerald-500/5 p-4 text-sm">
                        {status}
                    </p>
                )}
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        router.get(route('catalogos.show', catalogo), values, { preserveScroll: true });
                    }}
                    className="bg-card grid items-end gap-4 rounded-xl border p-5 md:grid-cols-2 xl:grid-cols-3"
                >
                    <div>
                        <label htmlFor="catalog-record-search" className="mb-2 block text-sm font-medium">
                            Buscar registro
                        </label>
                        <Input
                            id="catalog-record-search"
                            type="search"
                            maxLength={100}
                            placeholder="Nombre o datos del registro…"
                            value={values.buscar ?? ''}
                            onChange={(event) => setValues({ ...values, buscar: event.target.value })}
                        />
                    </div>
                    {fields
                        .filter(([, field]) => field.reference)
                        .map(([column, field]) => (
                            <CatalogReference
                                key={column}
                                catalogo={field.reference!}
                                id={`filter-${column}`}
                                label={field.label}
                                value={values[column] ?? ''}
                                onChange={(value) => setValues({ ...values, [column]: value })}
                            />
                        ))}
                    <div className="flex gap-2">
                        <Button type="submit" variant="outline">
                            Aplicar filtros
                        </Button>
                        <Button variant="ghost" asChild>
                            <Link href={route('catalogos.show', catalogo)}>Limpiar</Link>
                        </Button>
                    </div>
                </form>
                <p className="text-muted-foreground text-sm" role="status">
                    {records.total} registros · Página {records.current_page} de {records.last_page}
                </p>
                <div className="bg-card overflow-x-auto rounded-xl border">
                    <table className="w-full text-left text-sm">
                        <caption className="sr-only">Registros de {definition.title}</caption>
                        <thead className="bg-muted/50">
                            <tr>
                                {fields.map(([column, field]) => (
                                    <th scope="col" className="p-4 whitespace-nowrap" key={column}>
                                        {field.label}
                                    </th>
                                ))}
                                <th scope="col" className="p-4">
                                    Acciones
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {records.data.map((record) => (
                                <tr className="border-t" key={record[definition.key]}>
                                    {fields.map(([column, field]) => (
                                        <td className="max-w-64 min-w-36 p-4 break-words" key={column}>
                                            {String((field.reference ? record[`${column}_label`] : record[column]) ?? '—')}
                                        </td>
                                    ))}
                                    <td className="p-4">
                                        <div className="flex gap-2">
                                            <Button variant="outline" size="sm" asChild>
                                                <Link href={route('catalogos.edit', { catalogo, registro: record[definition.key] })}>
                                                    Editar<span className="sr-only"> {record[definition.label]}</span>
                                                </Link>
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="text-red-700 dark:text-red-400"
                                                onClick={() => {
                                                    deletion.clearErrors();
                                                    setSelected(record);
                                                }}
                                            >
                                                Eliminar<span className="sr-only"> {record[definition.label]}</span>
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {records.data.length === 0 && (
                        <div className="p-8 text-center">
                            <p className="font-semibold">No hay registros para mostrar</p>
                            <p className="text-muted-foreground mt-2 text-sm">Agrega un registro o modifica la búsqueda y los filtros.</p>
                        </div>
                    )}
                </div>
                <div className="flex gap-3">
                    {records.prev_page_url && (
                        <Button variant="outline" asChild>
                            <Link href={records.prev_page_url}>Anterior</Link>
                        </Button>
                    )}
                    {records.next_page_url && (
                        <Button variant="outline" asChild>
                            <Link href={records.next_page_url}>Siguiente</Link>
                        </Button>
                    )}
                </div>
            </div>
            <Dialog
                open={selected !== null}
                onOpenChange={(open) => {
                    if (!open && !deletion.processing) setSelected(null);
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Eliminar registro</DialogTitle>
                        <DialogDescription>
                            ¿Deseas eliminar «{selected?.[definition.label]}»? Dejará de estar disponible para nuevas capturas. Los registros con
                            datos relacionados no pueden eliminarse.
                        </DialogDescription>
                    </DialogHeader>
                    {Object.values(deletion.errors).map((error) => (
                        <p key={error} role="alert" className="text-sm text-red-600">
                            {error}
                        </p>
                    ))}
                    <div className="flex gap-3">
                        <Button
                            variant="destructive"
                            disabled={deletion.processing}
                            onClick={() => {
                                if (selected)
                                    deletion.delete(route('catalogos.destroy', { catalogo, registro: selected[definition.key] }), {
                                        onSuccess: () => setSelected(null),
                                    });
                            }}
                        >
                            {deletion.processing ? 'Eliminando…' : 'Confirmar eliminación'}
                        </Button>
                        <Button variant="outline" disabled={deletion.processing} onClick={() => setSelected(null)}>
                            Cancelar
                        </Button>
                    </div>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
