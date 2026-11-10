import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';
import { ArrowUpRight, BookOpen } from 'lucide-react';
import { useState } from 'react';

interface Catalog {
    slug: string;
    title: string;
    group: string;
    count: number;
}
export default function Catalogos({ catalogos }: { catalogos: Catalog[] }) {
    const [search, setSearch] = useState('');
    const normalize = (text: string) =>
        text
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase();
    const filtered = catalogos.filter((catalog) => normalize(`${catalog.title} ${catalog.group}`).includes(normalize(search.trim())));
    return (
        <AppLayout breadcrumbs={[{ title: 'Catálogos', href: route('catalogos.index') }]}>
            <Head title="Catálogos" />
            <div className="mx-auto w-full max-w-6xl space-y-7 p-5 md:p-8">
                <div className="rounded-2xl bg-[#263e3b] p-7 text-white">
                    <Badge className="bg-white/15 text-white">Administración</Badge>
                    <h1 className="mt-4 text-3xl font-semibold">Catálogos del sistema</h1>
                    <p className="mt-3 max-w-2xl text-sm leading-6 text-emerald-50/80">
                        Prepara la información que utilizarás para registrar al personal y organizar su seguimiento. Comienza con municipios, después
                        localidades y finalmente unidades médicas.
                    </p>
                </div>
                <div className="max-w-md">
                    <label htmlFor="catalog-search" className="mb-2 block text-sm font-medium">
                        Buscar catálogo
                    </label>
                    <Input
                        id="catalog-search"
                        type="search"
                        placeholder="Municipios, carreras, documentos…"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                    />
                </div>
                {[...new Set(filtered.map((catalog) => catalog.group))].map((group) => (
                    <section key={group}>
                        <h2 className="mb-4 text-lg font-semibold">{group}</h2>
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            {filtered
                                .filter((catalog) => catalog.group === group)
                                .map((catalog) => (
                                    <Link
                                        key={catalog.slug}
                                        href={route('catalogos.show', catalog.slug)}
                                        className="bg-card flex items-start gap-3 rounded-xl border p-5 transition-colors hover:border-[#722f42]/50"
                                    >
                                        <BookOpen className="mt-1 size-5 shrink-0 text-[#722f42] dark:text-rose-300" aria-hidden="true" />
                                        <div className="flex-1">
                                            <h3 className="text-sm font-semibold">{catalog.title}</h3>
                                            <p className="text-muted-foreground mt-2 text-xs">{catalog.count} registros disponibles</p>
                                        </div>
                                        <ArrowUpRight className="text-muted-foreground size-4" aria-hidden="true" />
                                    </Link>
                                ))}
                        </div>
                    </section>
                ))}
                {filtered.length === 0 && (
                    <p role="status" className="text-muted-foreground rounded-xl border border-dashed p-8 text-center">
                        No hay catálogos que coincidan con la búsqueda.
                    </p>
                )}
            </div>
        </AppLayout>
    );
}
