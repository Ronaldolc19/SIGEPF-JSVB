import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';

export type Participation = {
    id_personal: number;
    persona: { nombre: string; ap: string; am: string | null };
    fecha_ingreso: string | null;
    fecha_termino: string | null;
    entregados?: number;
};
export const personName = (personal: Participation) => [personal.persona.nombre, personal.persona.ap, personal.persona.am].filter(Boolean).join(' ');
export default function Expedientes({
    participaciones,
    buscar,
    isAdmin,
}: {
    participaciones: { data: Participation[]; prev_page_url: string | null; next_page_url: string | null };
    buscar: string;
    isAdmin: boolean;
}) {
    const form = useForm({ buscar });
    return (
        <AppLayout breadcrumbs={[{ title: 'Expediente digital', href: route('expedientes.index') }]}>
            <Head title="Expediente digital" />
            <div className="mx-auto w-full max-w-6xl space-y-6 p-5 md:p-8">
                <header>
                    <h1 className="text-2xl font-semibold">{isAdmin ? 'Expedientes digitales' : 'Mi expediente digital'}</h1>
                    <p className="text-muted-foreground mt-2">
                        {isAdmin
                            ? 'Consulta las entregas de los pasantes y registra observaciones.'
                            : 'Selecciona tu participación para subir documentos y consultar el estado de tus entregas.'}
                    </p>
                </header>
                {isAdmin && (
                    <form
                        className="flex gap-3"
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.get(route('expedientes.index'), { preserveState: true, replace: true });
                        }}
                    >
                        <label className="sr-only" htmlFor="buscar">
                            Buscar pasante por nombre o apellido
                        </label>
                        <Input
                            id="buscar"
                            placeholder="Buscar por nombre o apellido"
                            value={form.data.buscar}
                            maxLength={100}
                            onChange={(event) => form.setData('buscar', event.target.value)}
                        />
                        <Button disabled={form.processing}>Buscar</Button>
                    </form>
                )}
                {!participaciones.data.length && (
                    <div role="status" className="rounded-xl border p-6">
                        <h2 className="font-semibold">No hay participaciones disponibles</h2>
                        <p className="text-muted-foreground mt-2">
                            {isAdmin
                                ? 'Registra al personal en formación o cambia la búsqueda.'
                                : 'Solicita al administrador que vincule tu cuenta con tu persona y participación para habilitar tu expediente.'}
                        </p>
                    </div>
                )}
                <div className="grid gap-4 md:grid-cols-2">
                    {participaciones.data.map((personal) => (
                        <article key={personal.id_personal} className="bg-card space-y-4 rounded-xl border p-5">
                            <h2 className="font-semibold">{personName(personal)}</h2>
                            <p className="text-muted-foreground text-sm">
                                Participación #{personal.id_personal} · {personal.fecha_ingreso?.slice(0, 10) ?? 'Sin inicio'} —{' '}
                                {personal.fecha_termino?.slice(0, 10) ?? 'Sin término'}
                            </p>
                            <p>
                                <strong>{personal.entregados ?? 0} de 10</strong> conceptos con archivos
                            </p>
                            <p className="text-muted-foreground text-xs">La entrega de archivos no implica aprobación del expediente.</p>
                            <Button asChild>
                                <Link href={route('expedientes.show', personal.id_personal)}>Abrir expediente</Link>
                            </Button>
                        </article>
                    ))}
                </div>
                <nav aria-label="Paginación" className="flex gap-4">
                    {participaciones.prev_page_url && <Link href={participaciones.prev_page_url}>Anterior</Link>}
                    {participaciones.next_page_url && <Link href={participaciones.next_page_url}>Siguiente</Link>}
                </nav>
            </div>
        </AppLayout>
    );
}
