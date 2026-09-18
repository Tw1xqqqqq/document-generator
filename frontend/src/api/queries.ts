import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from './client';
import type { Collection, Document, Organization, Template, TemplateField } from './types';

/**
 * Хуки запросов на React Query.
 *
 * React Query сам хранит кэш, показывает состояние загрузки и обновляет
 * списки после изменений - иначе пришлось бы вручную писать это
 * в каждом компоненте.
 */

const keys = {
  organizations: ['organizations'] as const,
  organization: (id: number) => ['organizations', id] as const,
  templates: (organizationId?: number | null) => ['templates', organizationId ?? 'all'] as const,
  template: (id: number) => ['templates', id] as const,
  documents: (filters: Record<string, unknown>) => ['documents', filters] as const,
};

/* ----------------------------- Организации ----------------------------- */

export function useOrganizations() {
  return useQuery({
    queryKey: keys.organizations,
    queryFn: () => api.get<Collection<Organization>>('/organizations').then((r) => r.data),
  });
}

export function useSaveOrganization() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ id, values, logo }: { id?: number; values: Record<string, string>; logo?: File | null }) => {
      // Если есть файл логотипа, отправляем форму целиком.
      // PUT с файлами браузер не умеет, поэтому используется приём Laravel:
      // POST с полем _method=PUT.
      if (logo) {
        const formData = new FormData();
        Object.entries(values).forEach(([key, value]) => formData.append(key, value ?? ''));
        formData.append('logo', logo);

        if (id) {
          formData.append('_method', 'PUT');
          return api.postForm<{ data: Organization }>(`/organizations/${id}`, formData);
        }

        return api.postForm<{ data: Organization }>('/organizations', formData);
      }

      return id
        ? api.put<{ data: Organization }>(`/organizations/${id}`, values)
        : api.post<{ data: Organization }>('/organizations', values);
    },
    onSuccess: () => queryClient.invalidateQueries({ queryKey: keys.organizations }),
  });
}

export function useDeleteOrganization() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: number) => api.delete(`/organizations/${id}`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: keys.organizations });
      queryClient.invalidateQueries({ queryKey: ['templates'] });
      queryClient.invalidateQueries({ queryKey: ['documents'] });
    },
  });
}

/* ------------------------------- Шаблоны ------------------------------- */

export function useTemplates(organizationId?: number | null) {
  return useQuery({
    queryKey: keys.templates(organizationId),
    queryFn: () =>
      api
        .get<Collection<Template>>('/templates', {
          organization_id: organizationId ?? undefined,
        })
        .then((r) => r.data),
  });
}

export function useTemplate(id: number | undefined) {
  return useQuery({
    queryKey: keys.template(id!),
    queryFn: () => api.get<{ data: Template }>(`/templates/${id}`).then((r) => r.data),
    enabled: Boolean(id),
  });
}

export function useUploadTemplate() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: { name: string; description?: string; organization_id?: string; file: File }) => {
      const formData = new FormData();
      formData.append('name', payload.name);
      formData.append('description', payload.description ?? '');
      if (payload.organization_id) {
        formData.append('organization_id', payload.organization_id);
      }
      formData.append('file', payload.file);

      return api.postForm<{ data: Template }>('/templates', formData);
    },
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['templates'] }),
  });
}

export function useUpdateTemplate(id: number) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (values: { name: string; description?: string | null; organization_id?: number | null }) =>
      api.put<{ data: Template }>(`/templates/${id}`, values),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['templates'] }),
  });
}

export function useDeleteTemplate() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: number) => api.delete(`/templates/${id}`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['templates'] }),
  });
}

/** Загрузка новой версии файла шаблона. */
export function useUploadVersion(templateId: number) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ file, comment }: { file: File; comment?: string }) => {
      const formData = new FormData();
      formData.append('file', file);
      if (comment) formData.append('comment', comment);

      return api.postForm<{ data: Template }>(`/templates/${templateId}/versions`, formData);
    },
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['templates'] }),
  });
}

/** Возврат к предыдущей версии файла. */
export function useRestoreVersion(templateId: number) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (versionId: number) =>
      api.post<{ data: Template }>(`/templates/${templateId}/versions/${versionId}/restore`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['templates'] }),
  });
}

/** Сохранение настроек полей формы. */
export function useUpdateFields(templateId: number) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (fields: TemplateField[]) =>
      api.put<Collection<TemplateField>>(`/templates/${templateId}/fields`, { fields }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['templates'] }),
  });
}

/* ------------------------------ Документы ------------------------------ */

export function useDocuments(filters: { organization_id?: number; template_id?: number; search?: string }) {
  return useQuery({
    queryKey: keys.documents(filters),
    queryFn: () => api.get<Collection<Document>>('/documents', filters),
  });
}

export function useGenerateDocument() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: {
      template_id: number;
      organization_id: number;
      name?: string;
      data: Record<string, string>;
    }) => api.post<{ data: Document }>('/documents', payload),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['documents'] }),
  });
}

export function useDeleteDocument() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: number) => api.delete(`/documents/${id}`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['documents'] }),
  });
}

/** Предпросмотр: возвращает PDF, не сохраняя документ в журнал. */
export function previewDocument(payload: {
  template_id: number;
  organization_id: number;
  data: Record<string, string>;
}) {
  return api.postBlob('/documents/preview', payload);
}
