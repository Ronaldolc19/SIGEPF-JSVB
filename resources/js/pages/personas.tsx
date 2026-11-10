import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import type { Persona } from './persona-form';

type Records = {
    data: Persona[];
    total: number;
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};
export default function Personas({ records, buscar }: { records: Records; buscar: string }) {
    const form = useForm({ buscar });
    return (
        <AppLayout breadcrumbs={[{ title: 'Personas', href: route('personas.index') }]}>
            <Head title="Personas" />
            <div className="space-y-6 p-5 md:p-8">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">Personas</h1>
                        <p className="text-muted-foreground mt-2 text-sm">Administra los datos personales de asesores y personal en formación.</p>
                    </div>
                    <Button asChild>
                        <Link href={route('personas.create')}>Registrar persona</Link>
                    </Button>
                </div>
                <form
                    className="flex flex-wrap gap-3"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.get(route('personas.index'), { preserveState: true });
                    }}
                >
                    <Input
                        className="w-full sm:max-w-md"
                        aria-label="Buscar por nombre, correo o CURP"
                        placeholder="Buscar por nombre, correo o CURP"
                        maxLength={100}
                        value={form.data.buscar}
                        onChange={(e) => form.setData('buscar', e.target.value)}
                    />
                    <Button variant="outline" disabled={form.processing}>
                        Buscar
                    </Button>
                    {buscar && (
                        <Button variant="ghost" asChild>
                            <Link href={route('personas.index')}>Limpiar</Link>
                        </Button>
                    )}
                </form>
                <p className="text-muted-foreground text-sm">{records.total} personas registradas</p>
                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-muted">
                            <tr>
                                <th className="p-4">Nombre</th>
                                <th className="p-4">Correo</th>
                                <th className="p-4">Teléfono</th>
                                <th className="p-4">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            {records.data.map((person) => (
                                <tr key={person.id_persona} className="border-t">
                                    <td className="p-4 font-medium">{[person.nombre, person.ap, person.am].filter(Boolean).join(' ')}</td>
                                    <td className="p-4">{person.correo || 'Sin registrar'}</td>
                                    <td className="p-4">{person.telefono || 'Sin registrar'}</td>
                                    <td className="p-4">
                                        <Link className="underline underline-offset-4" href={route('personas.show', person.id_persona)}>
                                            Ver detalle
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                            {!records.data.length && (
                                <tr>
                                    <td colSpan={4} className="text-muted-foreground p-10 text-center">
                                        {buscar
                                            ? 'No se encontraron coincidencias.'
                                            : 'Todavía no hay personas registradas. Comienza con Registrar persona.'}
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <div className="flex flex-wrap items-center gap-4">
                    <span className="text-sm">
                        Página {records.current_page} de {records.last_page}
                    </span>
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
        </AppLayout>
    );
}
