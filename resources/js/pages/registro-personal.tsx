import InputError from '@/components/input-error';
import { RegistrationCatalog } from '@/components/registration-catalog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

type Props = {
    promociones: {
        id_promocion: number;
        nombre: string;
        fecha_inicio: string;
        fecha_fin: string;
        estado: string;
        carreras: { id_carrera: number; nombre: string }[];
    }[];
    selectedPromotion?: number | null;
    initial?: Record<string, string | number | null>;
    personalId?: number | null;
    account?: { id: number; name: string; email: string } | null;
};
const references = [
    ['id_genero', 'Género', 'generos', 0, true],
    ['id_nacionalidad', 'Nacionalidad', 'nacionalidades', 0, true],
    ['id_entidad_federativa', 'Entidad federativa del prestador', 'entidades_federativas', 0, true],
    ['id_municipio', 'Municipio de residencia', 'municipios', 0, false],
    ['id_localidad', 'Localidad de residencia', 'localidades', 0, false],
    ['id_tipo_personal', 'Tipo de personal en formación', 'tipos_personal', 1, true],
    ['id_institucion', 'Institución educativa de procedencia', 'instituciones', 1, true],
    ['id_carrera', 'Carrera', 'carreras', 1, true],
    ['id_nivel_academico', 'Nivel académico', 'nivel_academico', 1, false],
    ['id_unidad', 'Unidad médica de adscripción', 'unidades_medicas', 2, true],
    ['id_programa', 'Programa y clave de la constancia', 'programas', 2, true],
    ['id_vertiente', 'Vertiente indicada en la constancia', 'vertientes', 2, true],
    ['id_tipo_campo_clinico', 'Tipo de campo clínico (código de la constancia)', 'tipos_campos_clinicos', 2, true],
    ['id_fuente_financiamiento_beca', 'Beca con cargo a', 'fuentes_financiamiento_beca', 2, true],
] as const;
const fields = [
    ['ap', 'Apellido paterno', 'text', 0, true, 255],
    ['am', 'Apellido materno', 'text', 0, false, 255],
    ['nombre', 'Nombre(s)', 'text', 0, true, 255],
    ['edad_constancia', 'Edad indicada en la constancia', 'number', 0, true, 3],
    ['curp', 'CURP', 'text', 0, true, 18],
    ['rfc', 'RFC', 'text', 0, false, 13],
    ['fecha_nacimiento', 'Fecha de nacimiento (opcional)', 'date', 0, false, 10],
    ['desc_domicilio', 'Domicilio habitual', 'text', 0, false, 255],
    ['telefono', 'Teléfono', 'tel', 0, false, 20],
    ['correo', 'Correo electrónico', 'email', 0, false, 255],
    ['fecha_ingreso', 'Fecha de inicio del servicio', 'date', 1, true, 10],
    ['fecha_termino', 'Fecha de término del servicio', 'date', 1, true, 10],
    ['matricula', 'Matrícula (opcional)', 'text', 1, false, 100],
    ['promedio', 'Promedio (opcional)', 'number', 1, false, 6],
    ['numero_campo_clinico', 'Número de campo clínico', 'text', 2, true, 100],
] as const;
const steps = ['Identificación', 'Formación y periodo', 'Adscripción', 'Revisar y guardar'];

export default function RegistroPersonal(props: Props) {
    return <Editor key={props.personalId ?? props.initial?.id_persona ?? props.account?.id ?? 'new'} {...props} />;
}

function Editor({ initial = {}, personalId = null, account = null, promociones, selectedPromotion }: Props) {
    const selected = promociones.find((p) => p.id_promocion === selectedPromotion);
    const form = useForm<Record<string, string>>({
        ...Object.fromEntries([...references, ...fields].map(([key]) => [key, String(initial[key] ?? '')])),
        id_promocion: String(initial.id_promocion ?? selectedPromotion ?? ''),
        motivo_periodo: String(initial.motivo_periodo ?? ''),
        fecha_ingreso: String(initial.fecha_ingreso ?? selected?.fecha_inicio ?? ''),
        fecha_termino: String(initial.fecha_termino ?? selected?.fecha_fin ?? ''),
        id_persona: personalId ? '' : String(initial.id_persona ?? ''),
        id_usuario: account ? String(account.id) : '',
        id_adscripcion: String(initial.id_adscripcion ?? ''),
        fecha_inicio_adscripcion: String(initial.fecha_inicio_adscripcion ?? initial.fecha_ingreso ?? selected?.fecha_inicio ?? ''),
        fecha_fin_adscripcion: String(initial.fecha_fin_adscripcion ?? initial.fecha_termino ?? selected?.fecha_fin ?? ''),
        correo: String(initial.correo ?? account?.email ?? ''),
    });
    const [step, setStep] = useState(0);
    const promotion = promociones.find((p) => String(p.id_promocion) === form.data.id_promocion);
    const [unit, setUnit] = useState<Record<string, string | number | null> | null>(null);
    const [unitError, setUnitError] = useState(false);
    const [unitLoading, setUnitLoading] = useState(false);
    const [customDates, setCustomDates] = useState(!!initial.id_adscripcion);
    const heading = useRef<HTMLHeadingElement>(null);
    const title = personalId ? 'Editar registro del pasante' : 'Registrar personal en formación';
    useEffect(() => {
        heading.current?.focus();
    }, [step]);
    useEffect(() => {
        setUnit(null);
        setUnitError(false);
        if (!form.data.id_unidad) {
            setUnitLoading(false);
            return;
        }
        const abort = new AbortController();
        setUnitLoading(true);
        fetch(route('personal.unidad', form.data.id_unidad), { signal: abort.signal, headers: { Accept: 'application/json' } })
            .then((response) => {
                if (!response.ok || response.redirected) throw new Error();
                return response.json();
            })
            .then((data) => {
                if (!abort.signal.aborted) setUnit(data);
            })
            .catch(() => {
                if (!abort.signal.aborted) setUnitError(true);
            })
            .finally(() => {
                if (!abort.signal.aborted) setUnitLoading(false);
            });
        return () => abort.abort();
    }, [form.data.id_unidad]);
    function change(key: string, value: string) {
        form.setData((current) => {
            const data = { ...current, [key]: value };
            if (key === 'id_municipio') data.id_localidad = '';
            if (!customDates && key === 'fecha_ingreso') data.fecha_inicio_adscripcion = value;
            if (!customDates && key === 'fecha_termino') data.fecha_fin_adscripcion = value;
            return data;
        });
        form.clearErrors(key);
    }
    function next() {
        if (step === 1 && !personalId && !form.data.id_promocion) {
            form.setError('id_promocion', 'Selecciona una promoción para continuar.');
            return;
        }
        const missing = [...references, ...fields].filter(([key, , , section, required]) => section === step && required && !form.data[key]);
        if (missing.length) {
            missing.forEach(([key]) => form.setError(key, 'Completa este dato para continuar.'));
            return;
        }
        setStep((current) => Math.min(3, current + 1));
    }
    function errorStep(errors: Record<string, string>) {
        if (errors.id_promocion || errors.motivo_periodo) {
            setStep(1);
            return;
        }
        const field = [...references, ...fields].find(([key]) => !!errors[key]);
        setStep(field ? field[3] : 2);
    }
    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Personal en formación', href: route('personal.index') },
                { title, href: '#' },
            ]}
        >
            <Head title={title} />
            <div className="mx-auto w-full max-w-5xl space-y-6 p-5 md:p-8">
                <header className="space-y-2">
                    <h1 className="text-2xl font-semibold">{title}</h1>
                    <p className="text-muted-foreground text-sm">
                        Ten a la mano la constancia. Completa las secciones y guarda todo al finalizar. Los campos con * son obligatorios.
                    </p>
                </header>
                {account && (
                    <p className="rounded-lg border p-4 text-sm">
                        Cuenta que se vinculará: {account.name} · {account.email}
                    </p>
                )}
                {form.data.id_persona && (
                    <p className="rounded-lg border p-4 text-sm">Se utilizará la persona existente. Revisa sus datos antes de continuar.</p>
                )}
                <nav aria-label="Pasos del registro">
                    <ol className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                        {steps.map((name, index) => (
                            <li key={name}>
                                <button
                                    type="button"
                                    disabled={form.processing}
                                    onClick={() => setStep(index)}
                                    aria-current={step === index ? 'step' : undefined}
                                    className={
                                        'w-full rounded-lg border p-3 text-left text-sm ' +
                                        (step === index ? 'border-primary bg-primary text-primary-foreground' : 'bg-card text-muted-foreground')
                                    }
                                >
                                    <span className="mr-2 font-semibold">{index + 1}.</span>
                                    {name}
                                </button>
                            </li>
                        ))}
                    </ol>
                </nav>
                <form
                    noValidate
                    className="space-y-6"
                    onSubmit={(event) => {
                        event.preventDefault();
                        if (step < 3) {
                            next();
                            return;
                        }
                        if (personalId) form.put(route('personal.registro.update', personalId), { onError: errorStep });
                        else form.post(route('personal.store'), { onError: errorStep });
                    }}
                >
                    <section className="bg-card space-y-5 rounded-xl border p-5 md:p-7">
                        <h2 ref={heading} tabIndex={-1} className="text-xl font-semibold outline-none">
                            {steps[step]}
                        </h2>
                        {step === 0 && (
                            <p className="text-muted-foreground text-sm">
                                La entidad del prestador corresponde a la sección I de la constancia. La ubicación de la unidad se mostrará por
                                separado.
                            </p>
                        )}
                        {step === 1 && (
                            <div className="space-y-3 rounded-xl border p-4">
                                <label htmlFor="id_promocion" className="font-medium">
                                    Promoción {personalId ? '' : '*'}
                                </label>
                                <select
                                    id="id_promocion"
                                    className="bg-background block w-full rounded-md border p-2"
                                    disabled={!!initial.id_promocion || form.processing}
                                    value={form.data.id_promocion}
                                    onChange={(e) => {
                                        const next = promociones.find((p) => String(p.id_promocion) === e.target.value);
                                        form.setData((current) => ({
                                            ...current,
                                            id_promocion: e.target.value,
                                            id_carrera: next?.carreras.some((c) => String(c.id_carrera) === current.id_carrera)
                                                ? current.id_carrera
                                                : '',
                                            ...(!personalId && next
                                                ? {
                                                      fecha_ingreso: next.fecha_inicio,
                                                      fecha_termino: next.fecha_fin,
                                                      fecha_inicio_adscripcion: next.fecha_inicio,
                                                      fecha_fin_adscripcion: next.fecha_fin,
                                                  }
                                                : {}),
                                        }));
                                    }}
                                >
                                    <option value="">{personalId ? 'Pendiente de asignar' : 'Seleccionar promoción'}</option>
                                    {promociones.map((p) => (
                                        <option key={p.id_promocion} value={p.id_promocion}>
                                            {p.nombre} · {p.estado}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={form.errors.id_promocion} />
                                {promotion && (
                                    <p className="text-muted-foreground text-sm">
                                        Periodo programado: {promotion.fecha_inicio} — {promotion.fecha_fin}. Puedes ajustar las fechas individuales
                                        con un motivo; la promoción se conserva.
                                    </p>
                                )}
                                {!promociones.length && (
                                    <Link className="text-sm underline" href={route('promociones.create')}>
                                        Registra primero una promoción con sus carreras.
                                    </Link>
                                )}
                                {personalId && !initial.id_promocion && (
                                    <p className="text-muted-foreground text-sm">
                                        Para vincular un registro anterior también puedes seleccionar una promoción cerrada. Se conservarán sus fechas
                                        individuales.
                                    </p>
                                )}
                                <label htmlFor="motivo_periodo" className="block text-sm font-medium">
                                    Motivo del ajuste de periodo o prórroga
                                </label>
                                <textarea
                                    id="motivo_periodo"
                                    className="bg-background w-full rounded-md border p-3 text-sm"
                                    maxLength={2000}
                                    rows={3}
                                    value={form.data.motivo_periodo}
                                    onChange={(e) => form.setData('motivo_periodo', e.target.value)}
                                    placeholder="Indica el motivo y la referencia de la autorización, cuando corresponda."
                                />
                                <InputError message={form.errors.motivo_periodo} />
                            </div>
                        )}
                        {step === 1 && (
                            <p className="text-muted-foreground text-sm">
                                La institución, carrera y fechas se toman del documento. Puedes agregar la clave de la institución desde “Agregar
                                opción”. Matrícula, nivel y promedio pueden completarse después.
                            </p>
                        )}
                        {step === 2 && (
                            <p className="text-muted-foreground text-sm">
                                Transcribe los códigos del documento, por ejemplo A o B para campo clínico y NA para vertiente cuando así aparezca. No
                                se asignan automáticamente por carrera.
                            </p>
                        )}
                        {step < 3 && (
                            <fieldset disabled={form.processing} className="grid gap-5 sm:grid-cols-2">
                                {fields
                                    .filter((field) => field[3] === step)
                                    .map(([key, label, type, , required, max]) => (
                                        <div key={key} className="space-y-2">
                                            <label htmlFor={key} className="text-sm font-medium">
                                                {label}
                                                {required ? ' *' : ''}
                                            </label>
                                            <Input
                                                id={key}
                                                type={type}
                                                required={required}
                                                maxLength={max}
                                                value={form.data[key]}
                                                step={key === 'promedio' ? '0.01' : undefined}
                                                onChange={(event) => change(key, event.target.value)}
                                                aria-invalid={!!form.errors[key]}
                                                aria-describedby={key + '-error'}
                                            />
                                            <InputError id={key + '-error'} message={form.errors[key]} />
                                        </div>
                                    ))}
                                {references
                                    .filter((field) => field[3] === step)
                                    .map(([key, label, catalogo, , required]) => (
                                        <div key={key}>
                                            {key === 'id_carrera' && promotion ? (
                                                <div className="space-y-2">
                                                    <label htmlFor={key} className="text-sm font-medium">
                                                        Carrera de la promoción *
                                                    </label>
                                                    <select
                                                        id={key}
                                                        className="bg-background block w-full rounded-md border p-2"
                                                        value={form.data.id_carrera}
                                                        onChange={(e) => change('id_carrera', e.target.value)}
                                                    >
                                                        <option value="">Seleccionar carrera</option>
                                                        {promotion.carreras.map((c) => (
                                                            <option key={c.id_carrera} value={c.id_carrera}>
                                                                {c.nombre}
                                                            </option>
                                                        ))}
                                                    </select>
                                                </div>
                                            ) : (
                                                <RegistrationCatalog
                                                    id={key}
                                                    label={label}
                                                    catalogo={catalogo}
                                                    value={form.data[key]}
                                                    onChange={(value) => change(key, value)}
                                                    required={required}
                                                    invalid={!!form.errors[key]}
                                                    municipio={key === 'id_localidad' ? form.data.id_municipio : undefined}
                                                    disabled={key === 'id_localidad' && !form.data.id_municipio}
                                                />
                                            )}
                                            <InputError id={key + '-error'} message={form.errors[key]} />
                                        </div>
                                    ))}
                            </fieldset>
                        )}
                        {step === 2 && (
                            <>
                                <section className="bg-muted/30 space-y-3 rounded-lg border p-4" aria-live="polite">
                                    <h3 className="font-medium">Datos de la unidad seleccionada</h3>
                                    {unitLoading ? (
                                        <p>Cargando unidad…</p>
                                    ) : unitError ? (
                                        <p role="alert">No se pudieron obtener los datos. Vuelve a seleccionar la unidad.</p>
                                    ) : unit ? (
                                        <dl className="grid gap-3 sm:grid-cols-2">
                                            {Object.entries({
                                                clave_clues: 'CLUES',
                                                institucion_salud: 'Institución de salud',
                                                entidad: 'Entidad federativa',
                                                municipio: 'Municipio',
                                                localidad: 'Localidad',
                                                jurisdiccion: 'Jurisdicción',
                                                tipo_unidad: 'Tipo de unidad',
                                            }).map(([key, label]) => (
                                                <div key={key}>
                                                    <dt className="text-muted-foreground text-xs">{label}</dt>
                                                    <dd className="text-sm">{unit[key] || 'Pendiente en el catálogo de unidades'}</dd>
                                                </div>
                                            ))}
                                        </dl>
                                    ) : (
                                        <p className="text-muted-foreground text-sm">Selecciona una unidad para consultar sus datos.</p>
                                    )}
                                    <a
                                        href={route('catalogos.show', 'unidades_medicas')}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="text-primary text-sm underline"
                                    >
                                        Consultar o completar unidades (abre otra pestaña)
                                    </a>
                                </section>
                                <label className="flex items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        checked={customDates}
                                        onChange={(event) => {
                                            setCustomDates(event.target.checked);
                                            if (!event.target.checked)
                                                form.setData((data) => ({
                                                    ...data,
                                                    fecha_inicio_adscripcion: data.fecha_ingreso,
                                                    fecha_fin_adscripcion: data.fecha_termino,
                                                }));
                                        }}
                                    />
                                    La adscripción tiene fechas diferentes al periodo completo
                                </label>
                                {customDates ? (
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        {[
                                            ['fecha_inicio_adscripcion', 'Inicio de adscripción'],
                                            ['fecha_fin_adscripcion', 'Término de adscripción'],
                                        ].map(([key, label]) => (
                                            <div key={key}>
                                                <label htmlFor={key} className="text-sm">
                                                    {label}
                                                </label>
                                                <Input
                                                    id={key}
                                                    type="date"
                                                    value={form.data[key]}
                                                    onChange={(event) => change(key, event.target.value)}
                                                />
                                                <InputError message={form.errors[key]} />
                                            </div>
                                        ))}
                                    </div>
                                ) : (
                                    <p className="text-muted-foreground text-sm">
                                        Se usarán las fechas del servicio: {form.data.fecha_ingreso || 'Sin inicio'} —{' '}
                                        {form.data.fecha_termino || 'Sin término'}.
                                    </p>
                                )}
                                <InputError message={form.errors.fecha_inicio_adscripcion} />
                                <InputError message={form.errors.fecha_fin_adscripcion} />
                            </>
                        )}
                        {step === 3 && (
                            <div className="space-y-5">
                                <p className="text-muted-foreground text-sm">
                                    Comprueba los datos antes de guardar. Puedes regresar a cualquier sección sin perder lo capturado.
                                </p>
                                <dl className="grid gap-4 sm:grid-cols-2">
                                    {Object.entries({
                                        Nombre: [form.data.ap, form.data.am, form.data.nombre].filter(Boolean).join(' '),
                                        CURP: form.data.curp,
                                        Promoción: promotion?.nombre ?? 'Pendiente de asignar',
                                        Carrera:
                                            promotion?.carreras.find((c) => String(c.id_carrera) === form.data.id_carrera)?.nombre ??
                                            'Según catálogo seleccionado',
                                        'Motivo del ajuste': form.data.motivo_periodo || 'Sin ajuste de periodo',
                                        'Edad en la constancia': form.data.edad_constancia,
                                        Periodo: form.data.fecha_ingreso + ' — ' + form.data.fecha_termino,
                                        Unidad: unit?.nom_unidad,
                                        'Campo clínico': form.data.numero_campo_clinico,
                                    }).map(([label, value]) => (
                                        <div key={label}>
                                            <dt className="text-muted-foreground text-sm">{label}</dt>
                                            <dd className="font-medium">{value || 'Pendiente'}</dd>
                                        </div>
                                    ))}
                                </dl>
                                <p className="rounded-lg border p-4 text-sm">
                                    Al guardar se registran juntos los datos personales, académicos y la adscripción.
                                </p>
                            </div>
                        )}
                        <InputError message={form.errors.id_persona} />
                        <InputError message={form.errors.id_usuario} />
                        <InputError message={form.errors.id_adscripcion} />
                        {form.hasErrors && (
                            <p role="alert" className="text-sm text-red-600">
                                Revisa los campos señalados. Tu información sigue en el formulario.
                            </p>
                        )}
                    </section>
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <Button
                            type="button"
                            variant="outline"
                            disabled={step === 0 || form.processing}
                            onClick={() => setStep((current) => current - 1)}
                        >
                            Anterior
                        </Button>
                        <Link
                            href={personalId ? route('personal.show', personalId) : route('personal.index')}
                            className="text-muted-foreground text-sm underline"
                        >
                            Cancelar
                        </Link>
                        <Button disabled={form.processing}>
                            {form.processing ? 'Guardando…' : step === 3 ? 'Guardar registro completo' : 'Continuar'}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
