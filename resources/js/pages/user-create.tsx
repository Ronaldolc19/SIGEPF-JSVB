import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function UserCreate() {
    const form = useForm({ name: '', email: '', password: '', password_confirmation: '', rol: '' });
    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Usuarios y permisos', href: route('usuarios.index') },
                { title: 'Crear cuenta', href: route('usuarios.create') },
            ]}
        >
            <Head title="Crear cuenta de usuario" />
            <div className="mx-auto w-full max-w-2xl space-y-6 p-5 md:p-8">
                <div>
                    <h1 className="text-2xl font-semibold">Crear cuenta de usuario</h1>
                    <p className="text-muted-foreground mt-2 text-sm">
                        Primero registra el acceso. Después podrás llenar sus datos personales o vincular una persona existente.
                    </p>
                </div>
                <form
                    noValidate
                    className="space-y-5 rounded-xl border p-5"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(route('usuarios.store'), { onFinish: () => form.reset('password', 'password_confirmation') });
                    }}
                >
                    {(
                        [
                            ['name', 'Nombre de la cuenta', 'text'],
                            ['email', 'Correo de acceso', 'email'],
                            ['password', 'Contraseña (mínimo 12 caracteres)', 'password'],
                            ['password_confirmation', 'Confirmar contraseña', 'password'],
                        ] as const
                    ).map(([field, label, type]) => (
                        <div key={field} className="space-y-2">
                            <label className="text-sm font-medium" htmlFor={field}>
                                {label} *
                            </label>
                            <Input
                                id={field}
                                name={field}
                                type={type}
                                autoComplete={type === 'password' ? 'new-password' : field === 'email' ? 'email' : 'name'}
                                value={form.data[field]}
                                maxLength={255}
                                onChange={(event) => form.setData(field, event.target.value)}
                                required
                                aria-invalid={!!form.errors[field]}
                                aria-describedby={`${field}-error`}
                            />
                            <InputError id={`${field}-error`} message={form.errors[field]} />
                        </div>
                    ))}
                    <div className="space-y-2">
                        <label className="text-sm font-medium" htmlFor="rol">
                            Rol de acceso *
                        </label>
                        <select
                            id="rol"
                            name="rol"
                            value={form.data.rol}
                            onChange={(event) => form.setData('rol', event.target.value)}
                            required
                            aria-invalid={!!form.errors.rol}
                            aria-describedby="rol-error"
                            className="border-input bg-background h-10 w-full rounded-md border px-3 text-sm"
                        >
                            <option value="">Seleccionar rol</option>
                            <option value="administrador">Administrador</option>
                            <option value="asesor">Asesor</option>
                            <option value="pasante">Pasante — Personal en formación</option>
                        </select>
                        <InputError id="rol-error" message={form.errors.rol} />
                    </div>
                    <div className="flex flex-wrap gap-3">
                        <Button disabled={form.processing}>{form.processing ? 'Creando cuenta…' : 'Crear cuenta y continuar'}</Button>
                        <Button variant="outline" asChild>
                            <Link href={route('usuarios.index')}>Cancelar</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
