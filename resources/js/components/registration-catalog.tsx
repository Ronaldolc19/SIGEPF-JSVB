import { CatalogReference } from '@/components/catalog-reference';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useState } from 'react';

type Props = React.ComponentProps<typeof CatalogReference>;
const simpleCatalogs = [
    'generos',
    'nacionalidades',
    'entidades_federativas',
    'instituciones',
    'carreras',
    'nivel_academico',
    'tipos_personal',
    'programas',
    'vertientes',
    'tipos_campos_clinicos',
    'fuentes_financiamiento_beca',
];

export function RegistrationCatalog(props: Props) {
    const [open, setOpen] = useState(false);
    const [values, setValues] = useState<Record<string, string>>({});
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);
    const [revision, setRevision] = useState(0);
    const fields = [
        { key: 'nombre', label: 'Nombre tal como aparece en el documento', required: true, max: 255 },
        ...(props.catalogo === 'programas' ? [{ key: 'clave_programa', label: 'Clave del programa', required: true, max: 50 }] : []),
        ...(props.catalogo === 'instituciones'
            ? [{ key: 'clave_institucion', label: 'Clave de la institución (si aparece)', required: false, max: 50 }]
            : []),
    ];
    return (
        <div className="space-y-2">
            <CatalogReference {...props} key={revision} />
            {simpleCatalogs.includes(props.catalogo) && (
                <Button
                    type="button"
                    variant="link"
                    size="sm"
                    className="h-auto p-0"
                    onClick={() => {
                        setValues({});
                        setErrors({});
                        setOpen(true);
                    }}
                >
                    ¿No aparece? Agregar opción
                </Button>
            )}
            <Dialog
                open={open}
                onOpenChange={(value) => {
                    if (!saving) setOpen(value);
                }}
            >
                <DialogContent>
                    <DialogTitle>Agregar: {props.label}</DialogTitle>
                    <DialogDescription>
                        Transcribe el dato de la constancia. La nueva opción quedará disponible sin salir de este registro.
                    </DialogDescription>
                    <form
                        className="space-y-4"
                        onSubmit={async (event) => {
                            event.preventDefault();
                            event.stopPropagation();
                            setSaving(true);
                            setErrors({});
                            try {
                                const token = document.cookie
                                    .split('; ')
                                    .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
                                    ?.slice('XSRF-TOKEN='.length);
                                const response = await fetch(route('catalogos.store', props.catalogo), {
                                    method: 'POST',
                                    credentials: 'same-origin',
                                    headers: {
                                        Accept: 'application/json',
                                        'Content-Type': 'application/json',
                                        'X-XSRF-TOKEN': decodeURIComponent(token ?? ''),
                                    },
                                    body: JSON.stringify(values),
                                });
                                if (response.status === 422) {
                                    const result: { errors: Record<string, string[]> } = await response.json();
                                    setErrors(Object.fromEntries(Object.entries(result.errors).map(([field, messages]) => [field, messages[0]])));
                                    return;
                                }
                                if (!response.ok || response.redirected) throw new Error('No se pudo guardar');
                                const result: { option: { id: number; label: string; context?: string } } = await response.json();
                                props.onChange(String(result.option.id));
                                props.onOption?.(result.option);
                                setRevision((value) => value + 1);
                                setOpen(false);
                            } catch {
                                setErrors({ general: 'No se pudo agregar. Revisa tu conexión y tus permisos de catálogos.' });
                            } finally {
                                setSaving(false);
                            }
                        }}
                    >
                        {fields.map((field) => (
                            <div key={field.key} className="space-y-2">
                                <label htmlFor={props.id + '-new-' + field.key} className="text-sm font-medium">
                                    {field.label}
                                    {field.required ? ' *' : ''}
                                </label>
                                <Input
                                    id={props.id + '-new-' + field.key}
                                    required={field.required}
                                    maxLength={field.max}
                                    value={values[field.key] ?? ''}
                                    disabled={saving}
                                    onChange={(event) => setValues((current) => ({ ...current, [field.key]: event.target.value }))}
                                />
                                <InputError message={errors[field.key]} />
                            </div>
                        ))}
                        <InputError message={errors.general} />
                        <Button disabled={saving}>{saving ? 'Guardando…' : 'Agregar y seleccionar'}</Button>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    );
}
