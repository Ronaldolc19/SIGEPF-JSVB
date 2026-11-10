import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

interface Person {
    id_persona: number;
    nombre: string;
    ap: string;
    am: string | null;
    correo: string | null;
    asesor_exists?: boolean;
    participaciones_count?: number;
}
interface Props {
    account: { id: number; name: string; email: string; id_persona: number | null };
    currentPerson: Person | null;
    personas: { data: Person[]; prev_page_url: string | null; next_page_url: string | null };
    buscar: string;
    status?: string | null;
}
const name = (person: Person) => [person.nombre, person.ap, person.am].filter(Boolean).join(' ');

export default function UserPersona({ account, currentPerson, personas, buscar, status }: Props) {
    const [search, setSearch] = useState(buscar);
    const [selected, setSelected] = useState<Person | null>(currentPerson);
    const form = useForm<{ id_persona: number | null }>({ id_persona: currentPerson?.id_persona ?? null });
    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Usuarios y permisos', href: route('usuarios.index') },
                { title: 'Vincular persona', href: route('usuarios.persona.edit', account.id) },
            ]}
        >
            <Head title="Vincular persona" />
            <div className="mx-auto w-full max-w-4xl space-y-6 p-5 md:p-8">
                {status && (
                    <p role="status" className="rounded-lg border p-4 text-sm">
                        {status}
                    </p>
                )}
                {!account.id_persona && (
                    <Button asChild>
                        <Link href={route('personas.create', { usuario: account.id })}>Registrar sus datos personales</Link>
                    </Button>
                )}
                <div>
                    <h1 className="text-2xl font-semibold">Vincular cuenta con persona</h1>
                    <p className="text-muted-foreground mt-2 text-sm">
                        {account.name} · {account.email}
                    </p>
                </div>
                <div className="bg-card rounded-xl border p-5">
                    <p className="text-sm font-medium">
                        Vínculo actual: {currentPerson ? name(currentPerson) : account.id_persona ? 'Persona eliminada' : 'Sin persona vinculada'}
                    </p>
                    <p className="text-muted-foreground mt-2 text-sm">
                        Esta relación identifica a quién pertenece la cuenta. No cambia sus roles ni crea participaciones, asesorías o adscripciones.
                        Verifica la identidad antes de guardar.
                    </p>
                </div>
                <form
                    className="flex gap-3"
                    onSubmit={(event) => {
                        event.preventDefault();
                        router.get(route('usuarios.persona.edit', account.id), { buscar: search }, { preserveState: true, preserveScroll: true });
                    }}
                >
                    <label className="sr-only" htmlFor="person-search">
                        Buscar persona por nombre, apellido o correo
                    </label>
                    <Input
                        id="person-search"
                        type="search"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Nombre, apellido o correo"
                        maxLength={100}
                    />
                    <Button type="submit" variant="outline">
                        Buscar
                    </Button>
                </form>
                <form
                    className="space-y-5"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.put(route('usuarios.persona.update', account.id));
                    }}
                >
                    <fieldset disabled={form.processing} className="space-y-3">
                        <legend className="mb-3 font-semibold">Personas disponibles</legend>
                        <label className="bg-card flex items-center gap-3 rounded-xl border p-4">
                            <input
                                type="radio"
                                name="persona"
                                checked={form.data.id_persona === null}
                                onChange={() => {
                                    form.setData('id_persona', null);
                                    setSelected(null);
                                }}
                                className="size-4 accent-[#722f42]"
                            />
                            <span className="text-sm">Sin vínculo con una persona</span>
                        </label>
                        {personas.data.map((person) => (
                            <label key={person.id_persona} className="bg-card flex items-start gap-3 rounded-xl border p-4">
                                <input
                                    type="radio"
                                    name="persona"
                                    checked={form.data.id_persona === person.id_persona}
                                    onChange={() => {
                                        form.setData('id_persona', person.id_persona);
                                        setSelected(person);
                                    }}
                                    className="mt-1 size-4 accent-[#722f42]"
                                />
                                <span className="min-w-0">
                                    <span className="block text-sm font-medium">
                                        {name(person)} · #{person.id_persona}
                                    </span>
                                    <span className="text-muted-foreground mt-1 block text-sm">{person.correo || 'Sin correo registrado'}</span>
                                    <span className="mt-2 flex flex-wrap gap-2">
                                        {person.asesor_exists && <Badge variant="secondary">Asesor registrado</Badge>}
                                        <Badge variant="outline">{person.participaciones_count} participaciones</Badge>
                                    </span>
                                </span>
                            </label>
                        ))}
                    </fieldset>
                    {personas.data.length === 0 && (
                        <p role="status" className="text-muted-foreground rounded-xl border border-dashed p-6 text-sm">
                            No hay personas disponibles con esta búsqueda. Solo aparecen registros existentes, no eliminados y sin otra cuenta
                            vinculada. Si todavía no existen personas, primero deberán registrarse.{' '}
                            <Link
                                className="underline underline-offset-4"
                                href={route('personas.create', account.id_persona ? {} : { usuario: account.id })}
                            >
                                Registrar persona
                            </Link>
                        </p>
                    )}
                    <div className="flex gap-3">
                        {personas.prev_page_url && (
                            <Button variant="outline" asChild>
                                <Link href={personas.prev_page_url} preserveState preserveScroll>
                                    Anterior
                                </Link>
                            </Button>
                        )}
                        {personas.next_page_url && (
                            <Button variant="outline" asChild>
                                <Link href={personas.next_page_url} preserveState preserveScroll>
                                    Siguiente
                                </Link>
                            </Button>
                        )}
                    </div>
                    <div className="bg-muted/40 rounded-xl border p-4 text-sm">
                        Al guardar:{' '}
                        {selected ? (
                            <>
                                la cuenta quedará vinculada con <strong>{name(selected)}</strong>.
                            </>
                        ) : (
                            'la cuenta quedará sin persona vinculada y no podrá consultar participaciones propias o asignadas mediante ese vínculo.'
                        )}
                    </div>
                    {form.errors.id_persona && (
                        <p role="alert" className="text-sm text-red-600">
                            {form.errors.id_persona}
                        </p>
                    )}
                    <div className="flex gap-3">
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? 'Guardando…' : 'Guardar vínculo'}
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href={route('usuarios.index')}>Cancelar</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
