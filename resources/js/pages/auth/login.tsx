import InputError from '@/components/input-error';
import { SignInPage } from '@/components/ui/sign-in';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowRight, Eye, EyeOff, LoaderCircle } from 'lucide-react';
import { useState } from 'react';

type LoginForm = { email: string; password: string; remember: boolean };
export default function Login({ status, canResetPassword }: { status?: string; canResetPassword: boolean }) {
    const form = useForm<LoginForm>({ email: '', password: '', remember: false });
    const [showPassword, setShowPassword] = useState(false);
    const inputClass =
        'w-full rounded-2xl border border-[#b8aeb0]/50 bg-white/70 p-4 text-sm outline-none transition focus:border-[#8e455a] focus:ring-2 focus:ring-[#8e455a]/20 disabled:opacity-60 dark:border-white/20 dark:bg-white/5';
    return (
        <>
            <Head title="Iniciar sesión · SIGEPF-JSVB" />
            <SignInPage>
                {status && (
                    <p role="status" className="mb-5 rounded-xl border border-emerald-600/30 bg-emerald-500/10 p-4 text-sm">
                        {status}
                    </p>
                )}
                <form
                    noValidate
                    className="space-y-5"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(route('login'), { onFinish: () => form.reset('password') });
                    }}
                >
                    <div className="space-y-2">
                        <label htmlFor="email" className="text-sm font-medium">
                            Correo electrónico
                        </label>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            autoComplete="username"
                            autoFocus
                            required
                            disabled={form.processing}
                            value={form.data.email}
                            onChange={(event) => form.setData('email', event.target.value)}
                            placeholder="Ingresa tu correo electrónico"
                            className={inputClass}
                            aria-invalid={!!form.errors.email}
                            aria-describedby={form.errors.email ? 'email-error' : undefined}
                        />
                        <InputError id="email-error" message={form.errors.email} role="alert" />
                    </div>
                    <div className="space-y-2">
                        <label htmlFor="password" className="text-sm font-medium">
                            Contraseña
                        </label>
                        <div className="relative">
                            <input
                                id="password"
                                name="password"
                                type={showPassword ? 'text' : 'password'}
                                autoComplete="current-password"
                                required
                                disabled={form.processing}
                                value={form.data.password}
                                onChange={(event) => form.setData('password', event.target.value)}
                                placeholder="Ingresa tu contraseña"
                                className={inputClass + ' pr-14'}
                                aria-invalid={!!form.errors.password}
                                aria-describedby={form.errors.password ? 'password-error' : undefined}
                            />
                            <button
                                type="button"
                                onClick={() => setShowPassword((value) => !value)}
                                aria-label={showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'}
                                aria-pressed={showPassword}
                                className="absolute inset-y-1 right-1 flex w-12 items-center justify-center rounded-xl text-[#837278] hover:text-[#772f43] focus-visible:outline-2 focus-visible:outline-[#8e455a] dark:text-[#c8bdc1]"
                            >
                                {showPassword ? <EyeOff className="size-5" /> : <Eye className="size-5" />}
                            </button>
                        </div>
                        <InputError id="password-error" message={form.errors.password} role="alert" />
                    </div>
                    <div className="flex flex-wrap items-center justify-between gap-3 text-sm">
                        <label className="flex cursor-pointer items-center gap-2">
                            <input
                                type="checkbox"
                                name="remember"
                                checked={form.data.remember}
                                onChange={(event) => form.setData('remember', event.target.checked)}
                                className="size-4 accent-[#772f43]"
                            />
                            Recordarme
                        </label>
                        {canResetPassword && (
                            <Link href={route('password.request')} className="font-medium text-[#772f43] hover:underline dark:text-[#e2b6c3]">
                                ¿Olvidaste tu contraseña?
                            </Link>
                        )}
                    </div>
                    <button
                        type="submit"
                        disabled={form.processing}
                        className="flex w-full items-center justify-center gap-3 rounded-2xl bg-[#772f43] p-4 font-semibold text-white shadow-lg shadow-[#772f43]/10 transition hover:bg-[#612638] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#772f43] disabled:opacity-60"
                    >
                        {form.processing && <LoaderCircle className="size-5 animate-spin" aria-hidden="true" />}
                        {form.processing ? 'Iniciando sesión…' : 'Iniciar sesión'}
                        {!form.processing && <ArrowRight className="size-4" aria-hidden="true" />}
                    </button>
                    <p className="pt-3 text-center text-sm text-[#71666b] dark:text-[#c8bdc1]">
                        ¿Todavía no tienes cuenta?{' '}
                        <Link href={route('register')} className="font-semibold text-[#772f43] hover:underline dark:text-[#e2b6c3]">
                            Crear cuenta
                        </Link>
                    </p>
                </form>
            </SignInPage>
        </>
    );
}
