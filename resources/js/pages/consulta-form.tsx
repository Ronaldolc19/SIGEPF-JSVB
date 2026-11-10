import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { type CatalogOption } from './consulta';

type Material = {
    id_material: number;
    titulo: string;
    descripcion: string | null;
    id_tipo_documento: number;
    id_carrera: number | null;
    id_tipo_personal: number | null;
    estado: string;
    revision: number;
    nombre_archivo: string;
};
export default function ConsultaForm(props: {
    material: Material | null;
    tipos: CatalogOption[];
    carreras: CatalogOption[];
    personalTipos: CatalogOption[];
    estados: string[];
}) {
    return <Editor key={props.material ? `${props.material.id_material}-${props.material.revision}` : 'new'} {...props} />;
}
function Editor({
    material,
    tipos,
    carreras,
    personalTipos,
    estados,
}: {
    material: Material | null;
    tipos: CatalogOption[];
    carreras: CatalogOption[];
    personalTipos: CatalogOption[];
    estados: string[];
}) {
    const form = useForm({
        titulo: material?.titulo ?? '',
        descripcion: material?.descripcion ?? '',
        id_tipo_documento: String(material?.id_tipo_documento ?? ''),
        id_carrera: String(material?.id_carrera ?? ''),
        id_tipo_personal: String(material?.id_tipo_personal ?? ''),
        estado: material?.estado ?? 'Borrador',
        revision: material?.revision ?? 0,
        archivo: null as File | null,
        _method: material ? 'PUT' : 'POST',
    });
    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Portal de consulta', href: route('consulta.index') },
                { title: material ? 'Editar material' : 'Agregar material', href: '#' },
            ]}
        >
            <Head title={material ? 'Editar material' : 'Agregar material'} />
            <div className="mx-auto w-full max-w-3xl space-y-6 p-5 md:p-8">
                <header>
                    <h1 className="text-2xl font-semibold">{material ? 'Editar material' : 'Agregar material'}</h1>
                    <p className="text-muted-foreground mt-2">Adjunta el documento de Enseñanza y elige a quién estará disponible.</p>
                </header>
                <form
                    className="space-y-5"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(material ? route('consulta.update', material.id_material) : route('consulta.store'), { forceFormData: true });
                    }}
                >
                    <div>
                        <label htmlFor="titulo">Título *</label>
                        <Input
                            id="titulo"
                            maxLength={255}
                            value={form.data.titulo}
                            onChange={(event) => form.setData('titulo', event.target.value)}
                        />
                        <InputError message={form.errors.titulo} />
                    </div>
                    <div>
                        <label htmlFor="descripcion">Descripción e instrucciones</label>
                        <textarea
                            id="descripcion"
                            rows={5}
                            maxLength={5000}
                            className="bg-background mt-2 w-full rounded-md border p-3"
                            value={form.data.descripcion}
                            onChange={(event) => form.setData('descripcion', event.target.value)}
                        />
                        <InputError message={form.errors.descripcion} />
                    </div>
                    {(
                        [
                            ['id_tipo_documento', 'Tipo de documento *', tipos, 'Seleccionar'],
                            ['id_carrera', 'Carrera destinataria', carreras, 'Todas las carreras'],
                            ['id_tipo_personal', 'Tipo de personal destinatario', personalTipos, 'Todos los tipos de personal'],
                        ] as const
                    ).map(([key, label, options, placeholder]) => (
                        <div key={key}>
                            <label htmlFor={key}>{label}</label>
                            <select
                                id={key}
                                className="bg-background mt-2 w-full rounded-md border p-3"
                                value={form.data[key]}
                                onChange={(event) => form.setData(key, event.target.value)}
                            >
                                <option value="">{placeholder}</option>
                                {options.map((option) => (
                                    <option value={option.id} key={option.id}>
                                        {option.nombre}
                                    </option>
                                ))}
                            </select>
                            <InputError message={form.errors[key]} />
                        </div>
                    ))}
                    <p className="text-muted-foreground text-sm">
                        El material será general si eliges todas las carreras y todos los tipos. Si seleccionas ambos destinatarios, el pasante debe
                        reunir ambos en una misma participación.
                    </p>
                    <div>
                        <label htmlFor="archivo">{material ? 'Reemplazar archivo (opcional)' : 'Archivo *'}</label>
                        {material && <p className="text-muted-foreground my-2 text-sm break-all">Actual: {material.nombre_archivo}</p>}
                        <Input
                            id="archivo"
                            type="file"
                            accept=".pdf,.docx,.xlsx,.jpg,.jpeg,.png"
                            disabled={form.processing}
                            onChange={(event) => {
                                form.clearErrors('archivo');
                                const file = event.target.files?.[0] ?? null;
                                if (file && file.size > 20 * 1024 * 1024) {
                                    form.setError('archivo', 'El archivo debe pesar como máximo 20 MB.');
                                    form.setData('archivo', null);
                                } else form.setData('archivo', file);
                            }}
                        />
                        <p className="text-muted-foreground mt-2 text-xs">PDF, DOCX, XLSX, JPG o PNG. Máximo 20 MB.</p>
                        <InputError message={form.errors.archivo} />
                    </div>
                    <div>
                        <label htmlFor="estado">Estado *</label>
                        <select
                            id="estado"
                            className="bg-background mt-2 w-full rounded-md border p-3"
                            value={form.data.estado}
                            onChange={(event) => form.setData('estado', event.target.value)}
                        >
                            {estados.map((state) => (
                                <option key={state}>{state}</option>
                            ))}
                        </select>
                        <p className="text-muted-foreground mt-2 text-sm">
                            Borrador y Retirado solo son visibles para administración. Publicado habilita la consulta y descarga; la fecha se registra
                            automáticamente.
                        </p>
                        <InputError message={form.errors.estado} />
                    </div>
                    <InputError message={form.errors.revision} />
                    {form.progress && <p role="status">Subiendo archivo: {form.progress.percentage}%</p>}
                    <div className="flex gap-3">
                        <Button disabled={form.processing || !!form.errors.archivo}>
                            {form.processing ? 'Guardando…' : form.data.estado === 'Publicado' ? 'Guardar y publicar' : 'Guardar material'}
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href={route('consulta.index')}>Cancelar</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
