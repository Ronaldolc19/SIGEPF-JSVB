import { Input } from '@/components/ui/input';
import { useEffect, useState } from 'react';

interface Option {
    id: number;
    label: string;
    context?: string;
}

export function CatalogReference({
    catalogo,
    id,
    label,
    value,
    onChange,
    required = false,
    invalid = false,
    municipio,
    disabled = false,
    onOption,
}: {
    catalogo: string;
    id: string;
    label: string;
    value: string;
    onChange: (value: string) => void;
    required?: boolean;
    invalid?: boolean;
    municipio?: string;
    disabled?: boolean;
    onOption?: (option: Option) => void;
}) {
    const [search, setSearch] = useState('');
    const [options, setOptions] = useState<Option[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(false);
    useEffect(() => {
        if (disabled) {
            setOptions([]);
            setLoading(false);
            setError(false);
            return;
        }
        const controller = new AbortController();
        setLoading(true);
        setError(false);
        const timer = setTimeout(async () => {
            try {
                const url = new URL(route('catalogos.options', catalogo));
                url.searchParams.set('buscar', search);
                if (value) url.searchParams.set('selected', value);
                if (municipio) url.searchParams.set('id_municipio', municipio);
                const response = await fetch(url, { signal: controller.signal, credentials: 'same-origin', headers: { Accept: 'application/json' } });
                if (!response.ok || response.redirected) throw new Error('Unavailable');
                const data: { options: Option[] } = await response.json();
                if (!controller.signal.aborted) setOptions(data.options);
            } catch {
                if (!controller.signal.aborted) setError(true);
            } finally {
                if (!controller.signal.aborted) setLoading(false);
            }
        }, 250);
        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [catalogo, search, value, municipio, disabled]);
    return (
        <div className="space-y-2">
            <label htmlFor={id} className="text-sm font-medium">
                {label}
                {required ? ' *' : ''}
            </label>
            <Input
                type="search"
                aria-label={`Buscar opciones de ${label}`}
                placeholder={`Buscar ${label.toLowerCase()}…`}
                maxLength={100}
                value={search}
                disabled={disabled}
                onChange={(event) => setSearch(event.target.value)}
            />
            <select
                id={id}
                name={id}
                required={required}
                value={value}
                disabled={disabled || loading || error}
                onChange={(event) => {
                    onChange(event.target.value);
                    const option = options.find((item) => String(item.id) === event.target.value);
                    if (option) onOption?.(option);
                }}
                aria-invalid={invalid}
                aria-describedby={`${id}-help${invalid ? ` ${id}-error` : ''}`}
                className="border-input bg-background h-10 w-full rounded-md border px-3 text-sm disabled:opacity-60"
            >
                <option value="">{loading ? 'Cargando opciones…' : 'Seleccionar'}</option>
                {value && !options.some((option) => String(option.id) === value) && (
                    <option value={value}>{loading ? 'Cargando selección…' : 'Selección no disponible'}</option>
                )}
                {options.map((option) => (
                    <option key={option.id} value={option.id}>
                        {option.label}
                        {option.context ? ` · ${option.context}` : ''}
                    </option>
                ))}
            </select>
            <p id={`${id}-help`} className="text-muted-foreground text-xs" role="status">
                {disabled
                    ? 'Selecciona primero un municipio.'
                    : error
                      ? 'No se pudieron cargar las opciones. Revisa tu conexión y vuelve a buscar.'
                      : loading
                        ? 'Buscando…'
                        : options.length === 0
                          ? 'Sin opciones. Revisa la búsqueda o registra primero este catálogo.'
                          : 'Se muestran hasta 30 coincidencias y la selección actual. Escribe para encontrar otras.'}
            </p>
        </div>
    );
}
