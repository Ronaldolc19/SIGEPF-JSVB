export interface CatalogField {
    label: string;
    required: boolean;
    max?: number;
    reference?: string;
    pattern?: string;
}

export interface CatalogDefinition {
    title: string;
    group: string;
    key: string;
    label: string;
    fields: Record<string, CatalogField>;
}

export type CatalogRecord = Record<string, string | number | null>;
