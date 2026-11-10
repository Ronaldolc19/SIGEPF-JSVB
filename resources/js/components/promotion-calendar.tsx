import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import type { SharedData } from '@/types';
import { Link, useForm, usePage } from '@inertiajs/react';
import { CalendarDays, Check, ChevronDown } from 'lucide-react';
import { useState } from 'react';

const formatDate = (value: string | null) =>
    value
        ? new Intl.DateTimeFormat('es-MX', { day: 'numeric', month: 'short', year: 'numeric' }).format(new Date(`${value.slice(0, 10)}T12:00:00`))
        : 'Sin fecha';

export function PromotionCalendar() {
    const { promotionCalendar: calendar, auth } = usePage<SharedData>().props;
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');
    const form = useForm({ promocion: '', destino: 'personal.index' });
    if (!calendar) return null;
    const currentRoute = route().current() ?? '';
    const destination = ['personal.index', 'expedientes.index', 'incidencias.index'].includes(currentRoute) ? currentRoute : 'personal.index';
    const select = (value: string) => {
        form.transform(() => ({ promocion: value, destino: destination }));
        form.post(route('promociones.select'), { onSuccess: () => setOpen(false) });
    };
    const options = calendar.options.filter((p) => p.nombre.toLocaleLowerCase('es').includes(search.toLocaleLowerCase('es')));
    const emptyLabel = calendar.isAdmin ? calendar.label : calendar.options.length ? calendar.label : 'Promoción pendiente';
    const modes = calendar.isAdmin
        ? [
              ['vigentes', 'Seguimiento actual', 'Promociones en curso, prórrogas y registros pendientes.'],
              ['todas', 'Todas las promociones', 'Consulta conjunta, incluido el historial.'],
              ['sin', 'Sin promoción asignada', 'Participaciones pendientes de vincular.'],
              ['prorrogas', 'Periodos extendidos', 'Pasantes que continúan en formación.'],
          ]
        : calendar.participaciones.length > 1
          ? [['todas', 'Mis promociones', 'Consultar todas mis participaciones.']]
          : [];
    return (
        <Dialog
            open={open}
            onOpenChange={(value) => {
                if (!form.processing) {
                    setOpen(value);
                    if (value) {
                        setSearch('');
                        form.clearErrors();
                    }
                }
            }}
        >
            <DialogTrigger asChild>
                <Button
                    variant="outline"
                    className="border-primary/20 bg-primary/5 text-primary gap-2 rounded-full px-3"
                    title={`Calendario de promociones: ${emptyLabel}`}
                    aria-label={`Abrir calendario de promociones. ${emptyLabel}`}
                >
                    <CalendarDays className="size-4 shrink-0" />
                    <span className="hidden max-w-44 truncate text-xs font-semibold sm:inline lg:max-w-64">{emptyLabel}</span>
                    <ChevronDown className="hidden size-3 sm:block" />
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90dvh] w-[calc(100%_-_2rem)] max-w-lg overflow-y-auto rounded-2xl p-0">
                <DialogHeader className="border-b px-6 pt-6 pb-4 text-left">
                    <div className="bg-primary/10 text-primary mb-2 flex size-10 items-center justify-center rounded-xl">
                        <CalendarDays className="size-5" />
                    </div>
                    <DialogTitle>{calendar.isAdmin ? 'Selecciona una promoción' : 'Mi promoción de servicio'}</DialogTitle>
                    <DialogDescription>
                        {calendar.isAdmin
                            ? 'El periodo seleccionado se aplicará al navegar por personal, expedientes e incidencias.'
                            : `${auth.user.name}, aquí puedes consultar la promoción y las fechas de tus participaciones.`}
                    </DialogDescription>
                </DialogHeader>
                <div className="space-y-4 px-6">
                    {calendar.options.length > 5 && (
                        <Input
                            aria-label="Buscar promoción"
                            placeholder="Buscar promoción…"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                        />
                    )}
                    <div className="max-h-80 space-y-2 overflow-y-auto" aria-label="Promociones disponibles">
                        {options.map((p) => (
                            <button
                                key={p.id_promocion}
                                type="button"
                                disabled={form.processing}
                                aria-pressed={calendar.selected === String(p.id_promocion)}
                                className={
                                    'focus-visible:outline-ring w-full rounded-xl border p-4 text-left transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 disabled:opacity-60 ' +
                                    (calendar.selected === String(p.id_promocion)
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'bg-card hover:bg-accent')
                                }
                                onClick={() => select(String(p.id_promocion))}
                            >
                                <span className="flex items-center justify-between gap-3">
                                    <span className="text-sm font-semibold">{p.nombre}</span>
                                    {calendar.selected === String(p.id_promocion) && <Check className="size-4 shrink-0" />}
                                </span>
                                <span className="mt-1 block text-xs opacity-80">
                                    {formatDate(p.fecha_inicio)} — {formatDate(p.fecha_fin)} · {p.estado}
                                </span>
                                {!calendar.isAdmin &&
                                    calendar.participaciones
                                        .filter((person) => person.id_promocion === p.id_promocion)
                                        .map((person) => (
                                            <span key={person.id_personal} className="mt-3 block border-t border-current/20 pt-2 text-xs">
                                                Mi periodo: {formatDate(person.fecha_ingreso)} — {formatDate(person.fecha_termino)}
                                                <span className="mt-1 block">
                                                    {person.estado}
                                                    {person.fecha_termino && person.fecha_termino > p.fecha_fin ? ' · Periodo extendido' : ''}
                                                </span>
                                            </span>
                                        ))}
                            </button>
                        ))}
                        {!options.length && (
                            <p className="text-muted-foreground rounded-xl border border-dashed p-5 text-sm">
                                {search
                                    ? 'No hay promociones que coincidan con la búsqueda.'
                                    : calendar.isAdmin
                                      ? 'Todavía no hay promociones registradas.'
                                      : 'Aún no tienes una promoción asignada. Solicita al área de Enseñanza que revise tu participación.'}
                            </p>
                        )}
                    </div>
                    {modes.length > 0 && (
                        <details className="rounded-xl border p-3">
                            <summary className="cursor-pointer text-sm font-medium">
                                {calendar.isAdmin ? 'Otras consultas' : 'Consultar mi historial completo'}
                            </summary>
                            <div className="mt-3 space-y-2">
                                {modes.map(([value, label, description]) => (
                                    <button
                                        key={value}
                                        type="button"
                                        disabled={form.processing}
                                        aria-pressed={calendar.selected === value}
                                        onClick={() => select(value)}
                                        className={
                                            'w-full rounded-lg p-3 text-left text-sm ' +
                                            (calendar.selected === value ? 'bg-primary/10 text-primary' : 'hover:bg-accent')
                                        }
                                    >
                                        <span className="font-medium">{label}</span>
                                        <span className="text-muted-foreground mt-1 block text-xs">{description}</span>
                                    </button>
                                ))}
                            </div>
                        </details>
                    )}
                    <InputError message={form.errors.promocion ?? form.errors.destino} />
                    {form.processing && (
                        <p role="status" className="text-muted-foreground text-sm">
                            Cambiando periodo…
                        </p>
                    )}
                </div>
                <DialogFooter className="flex-row items-center justify-between border-t px-6 py-4">
                    {calendar.isAdmin && (
                        <Link href={route('promociones.index')} className="mr-auto text-sm underline" onClick={() => setOpen(false)}>
                            Administrar promociones
                        </Link>
                    )}
                    <DialogClose asChild>
                        <Button type="button" variant="outline" disabled={form.processing}>
                            Cerrar
                        </Button>
                    </DialogClose>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
