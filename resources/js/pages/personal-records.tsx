import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';

interface Participation {
    id_personal: number;
    id_persona: number;
    persona: { nombre: string; ap: string; am: string | null };
    tipo: { nombre: string } | null;
    fecha_ingreso: string | null;
    fecha_termino: string | null;
    estado: string;
    edad_constancia?: number | null;
}
interface Props {
    promocion?: { nombre: string; fecha_inicio: string; fecha_fin: string; estado: string } | null;
    historialPeriodos?: {
        id_historial_periodo: number;
        inicio_anterior: string | null;
        termino_anterior: string | null;
        fecha_inicio: string;
        fecha_termino: string | null;
        motivo: string | null;
        responsable: string;
        created_at: string;
    }[];
    status?: string;
    academicos?: Record<string, string | number | null> | null;
    adscripciones?: (Record<string, string | number | null> & { id_adscripcion: number })[];
    participaciones: { data: Participation[]; prev_page_url: string | null; next_page_url: string | null; total: number } | null;
    detail: Participation | null;
    linked: boolean;
    isAdmin: boolean;
}
const fullName = (record: Participation) => [record.persona.nombre, record.persona.ap, record.persona.am].filter(Boolean).join(' ');

export default function PersonalRecords({
    participaciones,
    detail,
    linked,
    isAdmin,
    status,
    academicos,
    adscripciones = [],
    promocion,
    historialPeriodos = [],
}: Props) {
    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Inicio', href: route('dashboard') },
                { title: 'Personal en formación', href: route('personal.index') },
            ]}
        >
            <Head title={detail ? 'Detalle de participación' : 'Personal en formación'} />
            <div className="mx-auto w-full max-w-6xl space-y-6 p-5 md:p-8">
                {status && (
                    <p role="status" className="rounded-lg border border-emerald-500/30 bg-emerald-500/5 p-4">
                        {status}
                    </p>
                )}
                {isAdmin && (
                    <div className="flex flex-wrap gap-3">
                        <Button asChild>
                            <Link href={route('personal.create')}>Registrar personal en formación</Link>
                        </Button>
                    </div>
                )}
                <div>
                    <Badge variant="outline">Información registrada</Badge>
                    <h1 className="mt-3 text-2xl font-semibold">
                        {detail ? 'Detalle de participación' : isAdmin ? 'Personal en formación' : 'Participaciones a mi alcance'}
                    </h1>
                    <p className="text-muted-foreground mt-2 text-sm">
                        {isAdmin
                            ? 'Consulta de las participaciones registradas en el sistema.'
                            : 'Solo se muestran tus participaciones o las asignadas a tu asesoría vigente, según tus roles.'}
                    </p>
                </div>
                {!isAdmin && !linked && (
                    <p role="status" className="rounded-xl border border-amber-500/30 bg-amber-500/5 p-5 text-sm">
                        Tu cuenta aún no tiene una persona vigente vinculada. Solicita al administrador que revise el vínculo en Usuarios y permisos.
                    </p>
                )}
                {detail ? (
                    <>
                        <section className="bg-card rounded-xl border p-6">
                            <h2 className="text-xl font-semibold">{fullName(detail)}</h2>
                            {isAdmin && (
                                <div className="mt-4 flex flex-wrap gap-3">
                                    <Button asChild>
                                        <Link href={route('personal.registro.edit', detail.id_personal)}>Editar registro completo</Link>
                                    </Button>
                                    <Button variant="outline" asChild>
                                        <Link href={route('personas.show', detail.id_persona)}>Identificación del prestador</Link>
                                    </Button>
                                </div>
                            )}
                            <dl className="mt-5 grid gap-5 sm:grid-cols-2">
                                {[
                                    ['Participación', `#${detail.id_personal}`],
                                    ['Tipo de personal', detail.tipo?.nombre || 'Sin tipo disponible'],
                                    ['Estado', detail.estado],
                                    ['Promoción', promocion?.nombre ?? 'Pendiente de asignar'],
                                    ['Periodo programado', promocion ? `${promocion.fecha_inicio} — ${promocion.fecha_fin}` : 'Sin promoción'],
                                    ['Edad en la constancia', detail.edad_constancia == null ? 'Sin registrar' : String(detail.edad_constancia)],
                                    ['Ingreso', detail.fecha_ingreso || 'Sin fecha'],
                                    ['Término', detail.fecha_termino || 'Sin fecha'],
                                ].map(([label, value]) => (
                                    <div key={label}>
                                        <dt className="text-muted-foreground text-sm">{label}</dt>
                                        <dd className="mt-1 font-medium">{value}</dd>
                                    </div>
                                ))}
                            </dl>
                        </section>
                        {historialPeriodos.length > 0 && (
                            <section className="bg-card space-y-4 rounded-xl border p-6">
                                <h2 className="text-lg font-semibold">Historial del periodo individual</h2>
                                <p className="text-muted-foreground text-sm">
                                    Las prórrogas conservan la promoción original. La fecha de término no acredita por sí sola la liberación.
                                </p>
                                {historialPeriodos.map((item) => (
                                    <article key={item.id_historial_periodo} className="space-y-1 border-t pt-3 text-sm">
                                        <p className="font-medium">
                                            {item.fecha_inicio} — {item.fecha_termino ?? 'Sin término'}
                                        </p>
                                        {item.termino_anterior && <p>Término anterior: {item.termino_anterior}</p>}
                                        <p>{item.motivo || 'Vinculación inicial a la promoción'}</p>
                                        <p className="text-muted-foreground">
                                            Registró: {item.responsable} · {item.created_at}
                                        </p>
                                    </article>
                                ))}
                            </section>
                        )}
                        <section className="bg-card space-y-4 rounded-xl border p-6">
                            <h2 className="text-lg font-semibold">Datos académicos</h2>
                            {academicos ? (
                                <dl className="grid gap-4 sm:grid-cols-2">
                                    {Object.entries({
                                        institucion: 'Institución educativa',
                                        carrera: 'Carrera',
                                        nivel_academico: 'Nivel académico',
                                        matricula: 'Matrícula',
                                        promedio: 'Promedio',
                                    }).map(([key, label]) => (
                                        <div key={key}>
                                            <dt className="text-muted-foreground text-sm">{label}</dt>
                                            <dd>{academicos[key] ?? 'Sin registrar'}</dd>
                                        </div>
                                    ))}
                                </dl>
                            ) : (
                                <p className="text-muted-foreground text-sm">Datos académicos pendientes de completar.</p>
                            )}
                        </section>
                        <section className="space-y-4">
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <h2 className="text-xl font-semibold">Campo clínico e historial de adscripción</h2>
                                {isAdmin && (
                                    <Button asChild>
                                        <Link href={route('adscripciones.create', detail.id_personal)}>Registrar adscripción</Link>
                                    </Button>
                                )}
                            </div>
                            {!adscripciones.length && (
                                <p className="rounded-xl border border-dashed p-6 text-sm">
                                    Aún no hay una unidad médica asignada a esta participación.
                                </p>
                            )}
                            {adscripciones.map((record) => (
                                <article key={record.id_adscripcion} className="bg-card space-y-4 rounded-xl border p-6">
                                    <div className="flex flex-wrap items-center justify-between gap-3">
                                        <h3 className="font-semibold">
                                            {record.unidad || 'Unidad no disponible'} · {record.fecha_inicio} —{' '}
                                            {record.fecha_fin || 'Sin fecha de fin'}
                                        </h3>
                                        {isAdmin && (
                                            <Button variant="outline" size="sm" asChild>
                                                <Link href={route('adscripciones.edit', [detail.id_personal, record.id_adscripcion])}>
                                                    Editar o cerrar
                                                </Link>
                                            </Button>
                                        )}
                                    </div>
                                    <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                        {Object.entries({
                                            clave_clues: 'CLUES',
                                            institucion_salud: 'Institución de salud',
                                            entidad: 'Entidad federativa',
                                            municipio: 'Municipio',
                                            localidad: 'Localidad',
                                            jurisdiccion: 'Jurisdicción',
                                            tipo_unidad: 'Tipo de unidad',
                                            tipologia: 'Tipología',
                                            numero_campo_clinico: 'Número de campo clínico',
                                            programas: 'Programa',
                                            clave_programa: 'Clave de programa',
                                            vertientes: 'Vertiente',
                                            tipos_campos_clinicos: 'Tipo de campo clínico',
                                            fuentes_financiamiento_beca: 'Beca financiada por',
                                            motivo_fin: 'Motivo de cierre',
                                        }).map(([key, label]) => (
                                            <div key={key}>
                                                <dt className="text-muted-foreground text-sm">{label}</dt>
                                                <dd className="break-words">{record[key] || 'Sin registrar'}</dd>
                                            </div>
                                        ))}
                                    </dl>
                                </article>
                            ))}
                        </section>
                        <Button variant="outline" asChild>
                            <Link href={route('personal.index')}>Volver al listado</Link>
                        </Button>
                    </>
                ) : (
                    <>
                        <p role="status" className="text-muted-foreground text-sm">
                            {participaciones?.total ?? 0} participaciones disponibles
                        </p>
                        {participaciones?.data.length ? (
                            <div className="bg-card overflow-x-auto rounded-xl border">
                                <table className="w-full min-w-[600px] text-left text-sm">
                                    <caption className="sr-only">Participaciones autorizadas</caption>
                                    <thead className="bg-muted/50">
                                        <tr>
                                            {['Persona', 'Tipo', 'Estado', 'Acción'].map((title) => (
                                                <th scope="col" className="p-4" key={title}>
                                                    {title}
                                                </th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {participaciones.data.map((record) => (
                                            <tr className="border-t" key={record.id_personal}>
                                                <td className="p-4">
                                                    <p className="font-medium">{fullName(record)}</p>
                                                    <p className="text-muted-foreground mt-1 text-xs">Participación #{record.id_personal}</p>
                                                </td>
                                                <td className="p-4">{record.tipo?.nombre || 'Sin tipo disponible'}</td>
                                                <td className="p-4">
                                                    <Badge variant="secondary">{record.estado}</Badge>
                                                </td>
                                                <td className="p-4">
                                                    {isAdmin && (
                                                        <Button variant="outline" size="sm" asChild>
                                                            <Link href={route('personal.registro.edit', record.id_personal)}>Editar registro</Link>
                                                        </Button>
                                                    )}
                                                    <Button variant="outline" size="sm" asChild>
                                                        <Link href={route('personal.show', record.id_personal)}>
                                                            Ver detalle<span className="sr-only"> de {fullName(record)}</span>
                                                        </Link>
                                                    </Button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <div className="rounded-xl border border-dashed p-8 text-center">
                                <h2 className="font-semibold">No hay participaciones disponibles</h2>
                                <p className="text-muted-foreground mt-2 text-sm">
                                    {isAdmin
                                        ? 'Todavía no hay registros de personal en formación para consultar.'
                                        : 'Puede faltar el registro de tu participación o una asignación de asesoría vigente.'}
                                </p>
                            </div>
                        )}
                        <div className="flex gap-3">
                            {participaciones?.prev_page_url && (
                                <Button variant="outline" asChild>
                                    <Link href={participaciones.prev_page_url}>Anterior</Link>
                                </Button>
                            )}
                            {participaciones?.next_page_url && (
                                <Button variant="outline" asChild>
                                    <Link href={participaciones.next_page_url}>Siguiente</Link>
                                </Button>
                            )}
                        </div>
                    </>
                )}
            </div>
        </AppLayout>
    );
}
