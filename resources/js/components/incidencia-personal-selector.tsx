import { personName, type Participation } from '@/pages/expedientes';
import { Combobox, ComboboxButton, ComboboxInput, ComboboxOption, ComboboxOptions } from '@headlessui/react';
import { Check, ChevronDown } from 'lucide-react';
import { useEffect, useState } from 'react';

export default function IncidenciaPersonalSelector({
    personal,
    value,
    onChange,
    disabled,
    invalid,
}: {
    personal: Participation[];
    value: string;
    onChange: (value: string) => void;
    disabled: boolean;
    invalid: boolean;
}) {
    const [selected, setSelected] = useState<Participation | null>(() => personal.find((p) => String(p.id_personal) === value) ?? null);
    const [query, setQuery] = useState('');
    const [options, setOptions] = useState(personal);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(false);
    const [retry, setRetry] = useState(0);

    useEffect(() => {
        if (disabled) return;
        const controller = new AbortController();
        const timer = window.setTimeout(async () => {
            setLoading(true);
            setError(false);
            try {
                const response = await fetch(route('incidencias.create', { buscar: query }), {
                    signal: controller.signal,
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                });
                if (!response.ok) throw new Error('No se pudo buscar');
                const data: { personal: Participation[] } = await response.json();
                if (!controller.signal.aborted) setOptions(data.personal);
            } catch {
                if (!controller.signal.aborted) {
                    setOptions([]);
                    setError(true);
                }
            } finally {
                if (!controller.signal.aborted) setLoading(false);
            }
        }, 300);
        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [query, disabled, retry]);

    return (
        <div className="mt-2">
            <Combobox
                value={selected}
                by="id_personal"
                disabled={disabled}
                immediate
                onClose={() => setQuery('')}
                onChange={(person: Participation | null) => {
                    setSelected(person);
                    onChange(person ? String(person.id_personal) : '');
                    setQuery('');
                }}
            >
                <div className="relative">
                    <ComboboxInput
                        id="personal"
                        autoComplete="off"
                        maxLength={100}
                        aria-invalid={invalid}
                        aria-describedby="personal-help"
                        className="bg-background focus:border-ring focus:ring-ring/50 w-full rounded-md border py-3 pr-10 pl-3 focus:ring-2 focus:outline-none disabled:opacity-60"
                        placeholder="Escribe el nombre o los apellidos del pasante"
                        displayValue={(person: Participation | null) => (person ? personName(person) : '')}
                        onChange={(event) => {
                            setQuery(event.target.value);
                            setLoading(true);
                            setError(false);
                        }}
                    />
                    <ComboboxButton className="absolute inset-y-0 right-0 px-3" aria-label="Mostrar pasantes">
                        <ChevronDown className="size-4" />
                    </ComboboxButton>
                    <ComboboxOptions className="bg-popover text-popover-foreground absolute z-30 mt-1 max-h-72 w-full overflow-auto rounded-lg border p-1 shadow-lg">
                        {loading ? (
                            <p role="status" className="p-3 text-sm">
                                Buscando pasantes…
                            </p>
                        ) : error ? (
                            <div className="p-3 text-sm">
                                <p role="alert">No se pudo cargar la lista.</p>
                                <button type="button" className="mt-2 underline" onClick={() => setRetry((n) => n + 1)}>
                                    Reintentar
                                </button>
                            </div>
                        ) : options.length === 0 ? (
                            <p role="status" className="p-3 text-sm">
                                No se encontraron pasantes.
                            </p>
                        ) : (
                            options.map((person) => (
                                <ComboboxOption
                                    key={person.id_personal}
                                    value={person}
                                    className="data-[focus]:bg-accent data-[focus]:text-accent-foreground flex cursor-pointer items-center justify-between gap-3 rounded-md p-3"
                                >
                                    {({ selected: active }) => (
                                        <>
                                            <div>
                                                <p className="font-medium">{personName(person)}</p>
                                                <p className="text-muted-foreground text-xs">
                                                    Periodo: {person.fecha_ingreso?.slice(0, 10) ?? 'Sin inicio'} —{' '}
                                                    {person.fecha_termino?.slice(0, 10) ?? 'Sin término'}
                                                </p>
                                            </div>
                                            {active && <Check className="size-4 shrink-0" />}
                                        </>
                                    )}
                                </ComboboxOption>
                            ))
                        )}
                    </ComboboxOptions>
                </div>
            </Combobox>
            <p id="personal-help" className="text-muted-foreground mt-2 text-xs">
                {selected
                    ? `Periodo seleccionado: ${selected.fecha_ingreso?.slice(0, 10) ?? 'Sin inicio'} — ${selected.fecha_termino?.slice(0, 10) ?? 'Sin término'}`
                    : 'Escribe para buscar y selecciona un resultado. Se muestran hasta 30 coincidencias.'}
            </p>
        </div>
    );
}
