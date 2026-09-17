import {
  Alert,
  Badge,
  Button,
  Card,
  Divider,
  Group,
  Modal,
  NumberInput,
  Select,
  SimpleGrid,
  Stack,
  Text,
  TextInput,
  Textarea,
  Title,
} from '@mantine/core';
import { DateInput } from '@mantine/dates';
import { useDisclosure } from '@mantine/hooks';
import { notifications } from '@mantine/notifications';
import { IconDownload, IconEye, IconFileTypePdf, IconWand } from '@tabler/icons-react';
import dayjs from 'dayjs';
import { useEffect, useMemo, useState } from 'react';
import { useSearchParams } from 'react-router';

import { ApiError } from '../api/client';
import {
  previewDocument,
  useGenerateDocument,
  useOrganizations,
  useTemplate,
  useTemplates,
} from '../api/queries';
import type { Document, TemplateField } from '../api/types';

export function GeneratePage() {
  const [searchParams] = useSearchParams();

  const { data: organizations } = useOrganizations();
  const [organizationId, setOrganizationId] = useState<string | null>(null);
  const [templateId, setTemplateId] = useState<string | null>(searchParams.get('template'));

  const { data: templates } = useTemplates(organizationId ? Number(organizationId) : null);
  const { data: template } = useTemplate(templateId ? Number(templateId) : undefined);

  const generate = useGenerateDocument();

  const [values, setValues] = useState<Record<string, string>>({});
  const [documentName, setDocumentName] = useState('');
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [created, setCreated] = useState<Document | null>(null);

  const [previewUrl, setPreviewUrl] = useState<string | null>(null);
  const [previewOpened, { open: openPreview, close: closePreview }] = useDisclosure(false);
  const [previewLoading, setPreviewLoading] = useState(false);

  // Первая организация выбирается сама: в большинстве случаев она одна
  useEffect(() => {
    if (!organizationId && organizations?.length) {
      setOrganizationId(String(organizations[0].id));
    }
  }, [organizations, organizationId]);

  // При смене шаблона подставляем значения по умолчанию и сегодняшнюю дату
  useEffect(() => {
    if (!template?.fields) return;

    const defaults: Record<string, string> = {
      'doc.date': dayjs().format('YYYY-MM-DD'),
      'doc.number': '',
    };

    template.fields.forEach((field) => {
      defaults[field.key] = field.default_value ?? '';
    });

    setValues(defaults);
    setErrors({});
    setCreated(null);
    setDocumentName('');
  }, [template]);

  const setValue = (key: string, value: string) => {
    setValues((current) => ({ ...current, [key]: value }));
    setErrors((current) => ({ ...current, [`data.${key}`]: '' }));
  };

  const payload = useMemo(
    () => ({
      template_id: Number(templateId),
      organization_id: Number(organizationId),
      data: values,
    }),
    [templateId, organizationId, values],
  );

  const ready = Boolean(templateId && organizationId && template?.current_version);

  const handlePreview = async () => {
    setPreviewLoading(true);
    try {
      const blob = await previewDocument(payload);
      // Файл живёт только в памяти браузера: временная ссылка на него
      setPreviewUrl(URL.createObjectURL(blob));
      openPreview();
    } catch (error) {
      if (error instanceof ApiError && error.status === 422) {
        setErrors(error.fieldErrors);
        notifications.show({ color: 'red', message: 'Заполните обязательные поля' });
      } else {
        notifications.show({ color: 'red', message: (error as Error).message });
      }
    } finally {
      setPreviewLoading(false);
    }
  };

  const handleGenerate = () => {
    generate.mutate(
      { ...payload, name: documentName || undefined },
      {
        onSuccess: (response) => {
          setCreated(response.data);
          notifications.show({ color: 'teal', message: 'Документ создан' });
        },
        onError: (error) => {
          if (error instanceof ApiError && error.status === 422) {
            setErrors(error.fieldErrors);
            notifications.show({ color: 'red', message: 'Проверьте заполнение полей' });
            return;
          }
          notifications.show({ color: 'red', message: (error as Error).message });
        },
      },
    );
  };

  const renderField = (field: TemplateField) => {
    const error = errors[`data.${field.key}`] || undefined;
    const common = {
      key: field.key,
      label: field.label,
      description: field.hint || undefined,
      withAsterisk: field.required,
      error,
    };

    if (field.type === 'date') {
      return (
        <DateInput
          {...common}
          valueFormat="DD.MM.YYYY"
          placeholder="дд.мм.гггг"
          value={values[field.key] || null}
          onChange={(value) => setValue(field.key, toIsoDate(value))}
        />
      );
    }

    if (field.type === 'number') {
      return (
        <NumberInput
          {...common}
          value={values[field.key] ?? ''}
          onChange={(value) => setValue(field.key, String(value ?? ''))}
          thousandSeparator=" "
          decimalSeparator=","
          hideControls
        />
      );
    }

    if (field.type === 'textarea') {
      return (
        <Textarea
          {...common}
          autosize
          minRows={2}
          value={values[field.key] ?? ''}
          onChange={(event) => setValue(field.key, event.currentTarget.value)}
        />
      );
    }

    return (
      <TextInput
        {...common}
        value={values[field.key] ?? ''}
        onChange={(event) => setValue(field.key, event.currentTarget.value)}
      />
    );
  };

  return (
    <Stack>
      <div>
        <Title order={2}>Генерация документа</Title>
        <Text c="dimmed" size="sm">
          Выберите организацию и шаблон — форма соберётся из полей этого шаблона
        </Text>
      </div>

      <Card padding="md">
        <SimpleGrid cols={{ base: 1, sm: 2 }}>
          <Select
            label="Организация"
            placeholder="Выберите организацию"
            data={organizations?.map((o) => ({ value: String(o.id), label: o.name })) ?? []}
            value={organizationId}
            onChange={setOrganizationId}
            allowDeselect={false}
            withAsterisk
          />
          <Select
            label="Шаблон"
            placeholder="Выберите шаблон"
            data={
              templates?.map((t) => ({
                value: String(t.id),
                label: t.organization_id ? t.name : `${t.name} (общий)`,
              })) ?? []
            }
            value={templateId}
            onChange={setTemplateId}
            allowDeselect={false}
            withAsterisk
            searchable
          />
        </SimpleGrid>
      </Card>

      {!ready && (
        <Alert variant="light" title="Что дальше">
          После выбора шаблона появятся поля для заполнения. Реквизиты организации
          подставятся автоматически.
        </Alert>
      )}

      {ready && template && (
        <Card padding="md">
          <Stack>
            <Group justify="space-between">
              <Text fw={600}>{template.name}</Text>
              <Badge variant="light">версия {template.current_version?.version}</Badge>
            </Group>

            <Divider label="Данные документа" labelPosition="left" />

            <SimpleGrid cols={{ base: 1, sm: 2 }}>
              <TextInput
                label="Номер документа"
                placeholder="42"
                value={values['doc.number'] ?? ''}
                onChange={(event) => setValue('doc.number', event.currentTarget.value)}
              />
              <DateInput
                label="Дата документа"
                valueFormat="DD.MM.YYYY"
                value={values['doc.date'] || null}
                onChange={(value) => setValue('doc.date', toIsoDate(value))}
              />
            </SimpleGrid>

            {Boolean(template.fields?.length) && (
              <>
                <Divider label="Поля шаблона" labelPosition="left" />
                <SimpleGrid cols={{ base: 1, sm: 2 }}>
                  {template.fields?.map(renderField)}
                </SimpleGrid>
              </>
            )}

            <Divider label="Сохранение" labelPosition="left" />

            <TextInput
              label="Название документа в журнале"
              placeholder={`${template.name} от ${dayjs().format('DD.MM.YYYY')}`}
              value={documentName}
              onChange={(event) => setDocumentName(event.currentTarget.value)}
            />

            <Group justify="flex-end">
              <Button
                variant="default"
                leftSection={<IconEye size={16} />}
                onClick={handlePreview}
                loading={previewLoading}
              >
                Предпросмотр
              </Button>
              <Button
                leftSection={<IconWand size={16} />}
                onClick={handleGenerate}
                loading={generate.isPending}
              >
                Создать документ
              </Button>
            </Group>
          </Stack>
        </Card>
      )}

      {created && (
        <Alert color="teal" title="Документ готов" icon={<IconFileTypePdf size={18} />}>
          <Stack gap="sm" align="flex-start">
            <Text size="sm">{created.name}</Text>
            <Group>
              <Button
                size="xs"
                component="a"
                href={created.pdf_url ?? '#'}
                leftSection={<IconDownload size={14} />}
              >
                Скачать PDF
              </Button>
              <Button
                size="xs"
                variant="light"
                component="a"
                href={created.docx_url ?? '#'}
                leftSection={<IconDownload size={14} />}
              >
                Скачать DOCX
              </Button>
            </Group>
          </Stack>
        </Alert>
      )}

      <Modal
        opened={previewOpened}
        onClose={() => {
          closePreview();
          // Освобождаем память браузера: временная ссылка больше не нужна
          if (previewUrl) URL.revokeObjectURL(previewUrl);
          setPreviewUrl(null);
        }}
        title="Предпросмотр документа"
        size="xl"
      >
        {previewUrl && (
          <iframe
            src={previewUrl}
            title="Предпросмотр"
            style={{ width: '100%', height: '75vh', border: 0 }}
          />
        )}
      </Modal>
    </Stack>
  );
}

/** Приводит значение календаря к формату, который ждёт API: 2026-09-17. */
function toIsoDate(value: unknown): string {
  if (!value) return '';
  if (typeof value === 'string') return value;

  return dayjs(value as Date).format('YYYY-MM-DD');
}
