import { CatalogReference } from '@/components/catalog-reference';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import type { ComponentProps } from 'react';
import RegistroPersonal from './registro-personal';

export const personaFields = [
    ['nombre', 'Nombre(s)', 'text', 255],
    ['ap', 'Apellido paterno', 'text', 255],
    ['am', 'Apellido materno', 'text', 255],
    ['fecha_nacimiento', 'Fecha de nacimiento', 'date', 10],
    ['curp', 'CURP', 'text', 18],
    ['rfc', 'RFC', 'text', 13],
    ['correo', 'Correo electrónico', 'email', 255],
    ['telefono', 'Teléfono', 'tel', 20],
    ['desc_domicilio', 'Domicilio', 'text', 255],
] as const;
export type Persona = { id_persona: number } & Record<string, string | number | null>;
const references = [
    ['id_entidad_federativa', 'Entidad federativa del prestador (constancia)', 'entidades_federativas'],
    ['id_genero', 'Género', 'generos'],
    ['id_nacionalidad', 'Nacionalidad', 'nacionalidades'],
    ['id_localidad', 'Localidad de residencia', 'localidades'],
] as const;

type Props = {
    promociones: ComponentProps<typeof RegistroPersonal>['promociones'];
    persona: Persona | null;
    municipio?: number | null;
    perfiles?: string[];
    account?: { id: number; name: string; email: string; perfil: string } | null;
};
export default function PersonaForm({ persona, municipio, perfiles = [], account, promociones }: Props) {
    return (
        <Editor
            key={persona?.id_persona ?? `new-${account?.id ?? ''}`}
            persona={persona}
            municipio={municipio}
            perfiles={perfiles}
            account={account}
            promociones={promociones}
        />
    );
}
function Editor({ persona, municipio, perfiles = [], account, promociones }: Props) {
    const form = useForm<Record<string, string>>({
        ...Object.fromEntries([...personaFields, ...references].map(([field]) => [field, String(persona?.[field] ?? '')])),
        id_municipio: String(municipio ?? ''),
        perfil: account?.perfil ?? '',
        id_usuario: account ? String(account.id) : '',
        correo: String(persona?.correo ?? account?.email ?? ''),
        id_tipo_personal: '',
        fecha_ingreso: '',
        fecha_termino: '',
    });
    const title = persona ? 'Editar persona' : 'Registrar persona';
    if (form.data.perfil === 'personal_formacion' && !perfiles.length) {
        return <RegistroPersonal promociones={promociones} initial={{ ...form.data, id_persona: persona?.id_persona ?? '' }} account={account} />;
    }
    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Personas', href: route('personas.index') },
                { title, href: '#' },
            ]}
        >
            <Head title={title} />
            <div className="mx-auto w-full max-w-4xl space-y-6 p-5 md:p-8">
                {account && (
                    <p className="rounded-lg border p-4 text-sm">
                        Cuenta: <strong>{account.name}</strong> · {account.email}. Al guardar, esta persona quedará vinculada automáticamente a la
                        cuenta.
                    </p>
                )}
                <InputError message={form.errors.id_usuario} />
                <div>
                    <h1 className="text-2xl font-semibold">{title}</h1>
                    <p className="text-muted-foreground mt-2 text-sm">
                        Datos personales para vincular posteriormente su cuenta y su participación en el sistema. Los campos con * son obligatorios.
                    </p>
                </div>
                <form
                    noValidate
                    onSubmit={(event) => {
                        event.preventDefault();
                        if (persona) form.put(route('personas.update', persona.id_persona));
                        else form.post(route('personas.store'));
                    }}
                    className="space-y-6"
                >
                    <fieldset disabled={form.processing} className="space-y-4 rounded-xl border p-5">
                        <legend className="px-2 font-semibold">Perfil de la persona</legend>
                        {perfiles.length ? (
                            <p className="text-sm">
                                Perfil registrado: <strong>{perfiles.join(' / ')}</strong>. La edición de los datos personales conserva sus registros
                                e historial.
                            </p>
                        ) : (
                            <>
                                <label htmlFor="perfil" className="text-sm font-medium">
                                    Registrar como
                                </label>
                                <select
                                    id="perfil"
                                    name="perfil"
                                    value={form.data.perfil}
                                    onChange={(event) => {
                                        form.setData({
                                            ...form.data,
                                            perfil: event.target.value,
                                            id_tipo_personal: '',
                                            fecha_ingreso: '',
                                            fecha_termino: '',
                                        });
                                        form.clearErrors('perfil', 'id_tipo_personal', 'fecha_ingreso', 'fecha_termino');
                                    }}
                                    aria-invalid={!!form.errors.perfil}
                                    aria-describedby="perfil-error"
                                    className="border-input bg-background h-10 w-full rounded-md border px-3 text-sm"
                                >
                                    <option value="">Asignar después</option>
                                    <option value="asesor">Asesor</option>
                                    <option value="personal_formacion">Personal en formación (pasante)</option>
                                </select>
                                {form.data.perfil === 'personal_formacion' && (
                                    <div className="grid gap-5 sm:grid-cols-2">
                                        <div>
                                            <CatalogReference
                                                catalogo="tipos_personal"
                                                id="id_tipo_personal"
                                                label="Tipo de personal"
                                                value={form.data.id_tipo_personal}
                                                required
                                                onChange={(value) => form.setData('id_tipo_personal', value)}
                                                invalid={!!form.errors.id_tipo_personal}
                                            />
                                            <InputError id="id_tipo_personal-error" message={form.errors.id_tipo_personal} />
                                        </div>
                                        {(['fecha_ingreso', 'fecha_termino'] as const).map((field) => (
                                            <div key={field} className="space-y-2">
                                                <label htmlFor={field} className="text-sm font-medium">
                                                    {field === 'fecha_ingreso' ? 'Fecha de ingreso' : 'Fecha de término'}
                                                </label>
                                                <Input
                                                    type="date"
                                                    id={field}
                                                    value={form.data[field]}
                                                    onChange={(event) => form.setData(field, event.target.value)}
                                                    aria-invalid={!!form.errors[field]}
                                                    aria-describedby={`${field}-error`}
                                                />
                                                <InputError id={`${field}-error`} message={form.errors[field]} />
                                            </div>
                                        ))}
                                        <p className="text-muted-foreground text-sm">La participación se registrará con estado En formación.</p>
                                    </div>
                                )}
                                <p className="text-muted-foreground text-sm">
                                    Al guardar se registra el perfil seleccionado. La cuenta de acceso y sus permisos se administran en Usuarios y
                                    permisos.
                                </p>
                            </>
                        )}
                        <InputError id="perfil-error" message={form.errors.perfil} />
                    </fieldset>
                    <fieldset disabled={form.processing} className="rounded-xl border p-5">
                        <legend className="px-2 font-semibold">Identidad y contacto</legend>
                        <div className="grid gap-5 sm:grid-cols-2">
                            {personaFields.map(([field, label, type, max]) => (
                                <div key={field} className="space-y-2">
                                    <label htmlFor={field} className="text-sm font-medium">
                                        {label}
                                        {['nombre', 'ap'].includes(field) ? ' *' : ''}
                                    </label>
                                    <Input
                                        id={field}
                                        name={field}
                                        type={type}
                                        maxLength={max}
                                        value={form.data[field]}
                                        required={['nombre', 'ap'].includes(field)}
                                        aria-invalid={!!form.errors[field]}
                                        aria-describedby={form.errors[field] ? `${field}-error` : undefined}
                                        onChange={(e) => form.setData(field, e.target.value)}
                                    />
                                    <InputError id={`${field}-error`} message={form.errors[field]} />
                                </div>
                            ))}
                        </div>
                    </fieldset>
                    <fieldset disabled={form.processing} className="rounded-xl border p-5">
                        <legend className="px-2 font-semibold">Datos de catálogo</legend>
                        <div className="grid gap-5 sm:grid-cols-2">
                            {references.map(([field, label, catalogo]) => (
                                <div key={field}>
                                    {field === 'id_localidad' && (
                                        <div className="mb-5">
                                            <CatalogReference
                                                catalogo="municipios"
                                                id="id_municipio"
                                                label="Municipio de residencia"
                                                value={form.data.id_municipio}
                                                onChange={(value) => {
                                                    form.setData((data) => ({ ...data, id_municipio: value, id_localidad: '' }));
                                                    form.clearErrors('id_localidad', 'id_municipio');
                                                }}
                                                invalid={!!form.errors.id_municipio}
                                            />
                                            <InputError id="id_municipio-error" message={form.errors.id_municipio} />
                                        </div>
                                    )}
                                    <CatalogReference
                                        key={field === 'id_localidad' ? `${field}-${form.data.id_municipio}` : field}
                                        catalogo={catalogo}
                                        id={field}
                                        label={label}
                                        value={form.data[field]}
                                        onChange={(value) => form.setData(field, value)}
                                        invalid={!!form.errors[field]}
                                        municipio={field === 'id_localidad' ? form.data.id_municipio : undefined}
                                        disabled={field === 'id_localidad' && !form.data.id_municipio}
                                    />
                                    <InputError id={`${field}-error`} message={form.errors[field]} />
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
                        <Button disabled={form.processing}>{form.processing ? 'Guardando…' : 'Guardar persona'}</Button>
                        <Button variant="outline" asChild>
                            <Link
                                href={
                                    persona
                                        ? route('personas.show', persona.id_persona)
                                        : account
                                          ? route('usuarios.persona.edit', account.id)
                                          : route('personas.index')
                                }
                            >
                                Cancelar
                            </Link>
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
