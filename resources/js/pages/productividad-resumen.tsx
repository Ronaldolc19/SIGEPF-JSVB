import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';
type Row = {
    id_programacion: number;
    actividad: string;
    categoria: string;
    unidad_medida: string;
    unidad: string;
    fecha_inicio: string;
    fecha_fin: string;
    meta_unidad: number | null;
    meta_individual: number | null;
    realizadas: number;
    validadas: number;
    avance_individual: number | null;
    aportacion_unidad: number | null;
    registros: number;
    sin_actividad: number;
    no_aplica: number;
    observaciones: { fecha: string; resultado: string; descripcion: string | null; revision: string | null }[];
};
const percentage = (value: number | null) => (value === null ? 'No calculable' : `${value}%`);
export default function Resumen({
    persona,
    filas,
    historicos,
}: {
    persona: { nombre: string; id_personal: number };
    filas: Row[];
    historicos: { actividad: string; cantidad: number }[];
}) {
    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Productividad semanal', href: route('productividad.index') },
                { title: 'Resumen del periodo', href: route('productividad.report', persona.id_personal) },
            ]}
        >
            <Head title="Resumen de actividades y metas" />
            <div className="mx-auto w-full max-w-7xl space-y-6 p-5 md:p-8">
                <header>
                    <h1 className="text-2xl font-semibold">Resumen de actividades y metas</h1>
                    <p className="mt-2">
                        {persona.nombre} · Participación #{persona.id_personal}
                    </p>
                    <p className="text-muted-foreground mt-2">
                        Acumulado del periodo de cada actividad. Los porcentajes consideran solamente cantidades validadas. La aportación a la meta de
                        la unidad no equivale al cumplimiento de una meta individual.
                    </p>
                </header>
                {!filas.length && (
                    <p role="status" className="rounded-xl border p-5">
                        No hay actividades programadas para esta participación. Enseñanza debe configurar su promoción, carrera, adscripción y
                        periodo.
                    </p>
                )}
                {filas.length > 0 && (
                    <div className="overflow-x-auto rounded-xl border">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted">
                                <tr>
                                    {[
                                        'Actividad y periodo',
                                        'Meta unidad',
                                        'Meta individual',
                                        'Capturado',
                                        'Validado',
                                        'Aportación unidad',
                                        'Avance individual',
                                        'Registro',
                                    ].map((h) => (
                                        <th key={h} className="p-3">
                                            {h}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {filas.map((r) => (
                                    <tr key={r.id_programacion} className="border-t align-top">
                                        <td className="min-w-64 p-3">
                                            <strong>{r.actividad}</strong>
                                            <p>
                                                {r.categoria} · {r.unidad_medida}
                                            </p>
                                            <p>{r.unidad}</p>
                                            <p className="text-muted-foreground">
                                                {r.fecha_inicio} — {r.fecha_fin}
                                            </p>
                                        </td>
                                        <td className="p-3">{r.meta_unidad ?? 'Sin definir'}</td>
                                        <td className="p-3">{r.meta_individual ?? 'Sin definir'}</td>
                                        <td className="p-3">{r.registros ? r.realizadas : '—'}</td>
                                        <td className="p-3">{r.validadas}</td>
                                        <td className="p-3">{percentage(r.aportacion_unidad)}</td>
                                        <td className="p-3">{percentage(r.avance_individual)}</td>
                                        <td className="p-3">
                                            {r.registros ? `${r.registros} capturas` : 'Sin registro'}
                                            {r.sin_actividad > 0 && <p>{r.sin_actividad} sin actividad</p>}
                                            {r.no_aplica > 0 && <p>{r.no_aplica} no aplica</p>}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
                <section className="space-y-3">
                    <h2 className="text-lg font-semibold">Contexto y observaciones</h2>
                    {filas
                        .filter((r) => r.observaciones.length > 0)
                        .map((r) => (
                            <details key={r.id_programacion} className="rounded-xl border p-4">
                                <summary className="cursor-pointer font-medium">
                                    {r.actividad} · {r.observaciones.length} anotaciones
                                </summary>
                                <ul className="mt-3 space-y-3">
                                    {r.observaciones.map((o, i) => (
                                        <li key={i} className="border-t pt-3">
                                            <p>
                                                {o.fecha} · {o.resultado}
                                            </p>
                                            <p className="text-sm whitespace-pre-wrap">{o.descripcion}</p>
                                            {o.revision && <p className="text-sm">Revisor: {o.revision}</p>}
                                        </li>
                                    ))}
                                </ul>
                            </details>
                        ))}
                </section>
                {historicos.length > 0 && (
                    <section className="space-y-3 rounded-xl border p-5">
                        <h2 className="font-semibold">Registros anteriores sin programación</h2>
                        <p className="text-muted-foreground text-sm">Se conservan como historial. No se les asignaron metas retroactivamente.</p>
                        <ul className="space-y-2">
                            {historicos.map((r, i) => (
                                <li key={i}>
                                    {r.actividad}: {r.cantidad}
                                </li>
                            ))}
                        </ul>
                    </section>
                )}
                <Button variant="outline" asChild>
                    <Link href={route('productividad.index')}>Volver a productividad semanal</Link>
                </Button>
            </div>
        </AppLayout>
    );
}
