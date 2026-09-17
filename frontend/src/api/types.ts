/** Типы данных, приходящих от API. Повторяют ресурсы Laravel. */

export interface Organization {
  id: number;
  name: string;
  full_name: string | null;
  inn: string | null;
  kpp: string | null;
  ogrn: string | null;
  legal_address: string | null;
  actual_address: string | null;
  phone: string | null;
  email: string | null;
  bank_name: string | null;
  bank_account: string | null;
  bank_corr_account: string | null;
  bank_bik: string | null;
  director_name: string | null;
  director_position: string | null;
  logo_url: string | null;
  templates_count?: number;
  documents_count?: number;
  created_at: string;
  updated_at: string;
}

export type FieldType = 'text' | 'textarea' | 'date' | 'number';

export interface TemplateField {
  id: number;
  key: string;
  label: string;
  type: FieldType;
  required: boolean;
  default_value: string | null;
  hint: string | null;
  sort_order: number;
}

export interface TemplateVersion {
  id: number;
  version: number;
  original_name: string;
  size: number;
  placeholders: string[];
  comment: string | null;
  is_current: boolean;
  created_at: string;
}

export interface Template {
  id: number;
  name: string;
  description: string | null;
  organization_id: number | null;
  organization?: Organization;
  current_version?: TemplateVersion;
  versions?: TemplateVersion[];
  fields?: TemplateField[];
  documents_count?: number;
  created_at: string;
  updated_at: string;
}

export interface Document {
  id: number;
  name: string;
  data: Record<string, string>;
  template_id: number;
  template?: Template;
  template_version?: TemplateVersion;
  organization_id: number;
  organization?: Organization;
  pdf_url: string | null;
  docx_url: string | null;
  created_at: string;
}

/** Ответ со списком: Laravel заворачивает данные в поле data. */
export interface Collection<T> {
  data: T[];
  meta?: {
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
  };
}
