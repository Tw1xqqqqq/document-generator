import {
  ActionIcon,
  Alert,
  Anchor,
  Badge,
  Button,
  Card,
  Checkbox,
  Code,
  FileInput,
  Group,
  LoadingOverlay,
  Select,
  Stack,
  Table,
  Tabs,
  Text,
  TextInput,
  Title,
  Tooltip,
} from '@mantine/core';
import { notifications } from '@mantine/notifications';
import {
  IconArrowBackUp,
  IconArrowLeft,
  IconDeviceFloppy,
  IconDownload,
  IconHistory,
  IconListDetails,
  IconTags,
  IconUpload,
} from '@tabler/icons-react';
import { useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router';

import {
  useOrganizations,
  useRestoreVersion,
  useTemplate,
  useUpdateFields,
  useUpdateTemplate,
  useUploadVersion,
} from '../api/queries';
import type { FieldType, TemplateField } from '../api/types';

const fieldTypes: { value: FieldType; label: string }[] = [
  { value: 'text', label: 'Текст' },
  { value: 'textarea', label: 'Многострочный текст' },
  { value: 'date', label: 'Дата' },
  { value: 'number', label: 'Число' },
];

export function TemplateDetailPage() {
  const { id } = useParams();
  const templateId = Number(id);
  const navigate = useNavigate();

  const { data: template, isLoading } = useTemplate(templateId);
  const { data: organizations } = useOrganizations();

  const updateTemplate = useUpdateTemplate(templateId);
  const updateFields = useUpdateFields(templateId);
  const uploadVersion = useUploadVersion(templateId);
  const restoreVersion = useRestoreVersion(templateId);

  // Поля правим в локальном состоянии и сохраняем разом — так пользователь
  // может спокойно всё настроить, а не ждать запроса на каждое нажатие
  const [fields, setFields] = useState<TemplateField[]>([]);
  const [newFile, setNewFile] = useState<File | null>(null);
  const [versionComment, setVersionComment] = useState('');

  useEffect(() => {
    if (template?.fields) {
      setFields(template.fields);
    }
  }, [template]);

  const patchField = (key: string, patch: Partial<TemplateField>) => {
    setFields((current) =>
      current.map((field) => (field.key === key ? { ...field, ...patch } : field)),
    );
  };

  const saveFields = () => {
    updateFields.mutate(fields, {
      onSuccess: () => notifications.show({ color: 'teal', message: 'Настройки полей сохранены' }),
      onError: (error) => notifications.show({ color: 'red', message: (error as Error).message }),
    });
  };

  const handleUploadVersion = () => {
    if (!newFile) {
      notifications.show({ color: 'red', message: 'Выберите файл новой версии' });
      return;
    }

    uploadVersion.mutate(
      { file: newFile, comment: versionComment },
      {
        onSuccess: () => {
          notifications.show({
            color: 'teal',
            title: 'Новая версия загружена',
            message: 'Настройки полей сохранены, новые метки добавлены автоматически',
          });
          setNewFile(null);
          setVersionComment('');
        },
        onError: (error) => notifications.show({ color: 'red', message: (error as Error).message }),
      },
    );
  };

  if (isLoading || !template) {
    return <LoadingOverlay visible />;
  }

  const placeholders = template.current_version?.placeholders ?? [];

  return (
    <Stack>
      <Group justify="space-between" align="flex-start">
        <Group gap="sm">
          <ActionIcon variant="subtle" onClick={() => navigate('/templates')}>
            <IconArrowLeft size={20} />
          </ActionIcon>
          <div>
            <Title order={2}>{template.name}</Title>
            <Text c="dimmed" size="sm">
              {template.description || 'Без описания'}
            </Text>
          </div>
        </Group>
        <Button variant="light" onClick={() => navigate(`/generate?template=${template.id}`)}>
          Создать документ
        </Button>
      </Group>

      <Card padding="md">
        <Group align="flex-end" gap="md">
          <TextInput
            label="Название"
            defaultValue={template.name}
            onBlur={(event) =>
              event.currentTarget.value !== template.name &&
              updateTemplate.mutate({
                name: event.currentTarget.value,
                description: template.description,
                organization_id: template.organization_id,
              })
            }
            style={{ flex: 1 }}
          />
          <Select
            label="Организация"
            placeholder="Общий шаблон"
            data={organizations?.map((o) => ({ value: String(o.id), label: o.name })) ?? []}
            defaultValue={template.organization_id ? String(template.organization_id) : null}
            clearable
            onChange={(value) =>
              updateTemplate.mutate({
                name: template.name,
                description: template.description,
                organization_id: value ? Number(value) : null,
              })
            }
            w={260}
          />
        </Group>
      </Card>

      <Tabs defaultValue="fields">
        <Tabs.List>
          <Tabs.Tab value="fields" leftSection={<IconListDetails size={16} />}>
            Поля формы ({fields.length})
          </Tabs.Tab>
          <Tabs.Tab value="versions" leftSection={<IconHistory size={16} />}>
            Версии файла ({template.versions?.length ?? 0})
          </Tabs.Tab>
          <Tabs.Tab value="placeholders" leftSection={<IconTags size={16} />}>
            Метки ({placeholders.length})
          </Tabs.Tab>
        </Tabs.List>

        {/* Настройка полей */}
        <Tabs.Panel value="fields" pt="md">
          <Stack>
            <Alert variant="light">
              Ключи полей берутся из меток документа и не редактируются. Меняются подпись,
              тип, обязательность и значение по умолчанию.
            </Alert>

            <Table.ScrollContainer minWidth={900}>
              <Table verticalSpacing="xs">
                <Table.Thead>
                  <Table.Tr>
                    <Table.Th w={200}>Метка в файле</Table.Th>
                    <Table.Th>Подпись в форме</Table.Th>
                    <Table.Th w={180}>Тип</Table.Th>
                    <Table.Th w={200}>Значение по умолчанию</Table.Th>
                    <Table.Th w={120}>Обязательное</Table.Th>
                  </Table.Tr>
                </Table.Thead>
                <Table.Tbody>
                  {fields.map((field) => (
                    <Table.Tr key={field.key}>
                      <Table.Td>
                        <Code>{`\${${field.key}}`}</Code>
                      </Table.Td>
                      <Table.Td>
                        <TextInput
                          size="xs"
                          value={field.label}
                          onChange={(event) =>
                            patchField(field.key, { label: event.currentTarget.value })
                          }
                        />
                      </Table.Td>
                      <Table.Td>
                        <Select
                          size="xs"
                          data={fieldTypes}
                          value={field.type}
                          onChange={(value) =>
                            patchField(field.key, { type: (value as FieldType) ?? 'text' })
                          }
                          allowDeselect={false}
                        />
                      </Table.Td>
                      <Table.Td>
                        <TextInput
                          size="xs"
                          value={field.default_value ?? ''}
                          onChange={(event) =>
                            patchField(field.key, { default_value: event.currentTarget.value })
                          }
                        />
                      </Table.Td>
                      <Table.Td>
                        <Checkbox
                          checked={field.required}
                          onChange={(event) =>
                            patchField(field.key, { required: event.currentTarget.checked })
                          }
                        />
                      </Table.Td>
                    </Table.Tr>
                  ))}
                </Table.Tbody>
              </Table>
            </Table.ScrollContainer>

            <Group justify="flex-end">
              <Button
                leftSection={<IconDeviceFloppy size={16} />}
                onClick={saveFields}
                loading={updateFields.isPending}
              >
                Сохранить настройки
              </Button>
            </Group>
          </Stack>
        </Tabs.Panel>

        {/* История версий */}
        <Tabs.Panel value="versions" pt="md">
          <Stack>
            <Card padding="md">
              <Stack gap="sm">
                <Text fw={500}>Загрузить новую версию файла</Text>
                <Text size="sm" c="dimmed">
                  Прошлые версии сохраняются: документы, выпущенные по ним, останутся неизменными.
                </Text>
                <Group align="flex-end">
                  <FileInput
                    label="Файл docx"
                    placeholder="Выберите файл"
                    accept=".docx"
                    value={newFile}
                    onChange={setNewFile}
                    style={{ flex: 1 }}
                  />
                  <TextInput
                    label="Что изменилось"
                    placeholder="Например: добавлено поле «Срок оплаты»"
                    value={versionComment}
                    onChange={(event) => setVersionComment(event.currentTarget.value)}
                    style={{ flex: 1 }}
                  />
                  <Button
                    leftSection={<IconUpload size={16} />}
                    onClick={handleUploadVersion}
                    loading={uploadVersion.isPending}
                  >
                    Загрузить
                  </Button>
                </Group>
              </Stack>
            </Card>

            <Table verticalSpacing="sm">
              <Table.Thead>
                <Table.Tr>
                  <Table.Th w={100}>Версия</Table.Th>
                  <Table.Th>Файл</Table.Th>
                  <Table.Th>Комментарий</Table.Th>
                  <Table.Th w={160}>Загружена</Table.Th>
                  <Table.Th w={120} />
                </Table.Tr>
              </Table.Thead>
              <Table.Tbody>
                {template.versions?.map((version) => (
                  <Table.Tr key={version.id}>
                    <Table.Td>
                      <Group gap="xs">
                        <Text>v{version.version}</Text>
                        {version.is_current && (
                          <Badge size="xs" variant="light">
                            текущая
                          </Badge>
                        )}
                      </Group>
                    </Table.Td>
                    <Table.Td>
                      <Text size="sm">{version.original_name}</Text>
                      <Text size="xs" c="dimmed">
                        {Math.round(version.size / 1024)} КБ · меток: {version.placeholders.length}
                      </Text>
                    </Table.Td>
                    <Table.Td>
                      <Text size="sm" c="dimmed">
                        {version.comment || '—'}
                      </Text>
                    </Table.Td>
                    <Table.Td>
                      <Text size="sm">
                        {new Date(version.created_at).toLocaleString('ru-RU')}
                      </Text>
                    </Table.Td>
                    <Table.Td>
                      <Group gap="xs" justify="flex-end">
                        <Tooltip label="Скачать файл">
                          <ActionIcon
                            variant="subtle"
                            component="a"
                            href={`/api/templates/${template.id}/versions/${version.id}/download`}
                          >
                            <IconDownload size={18} />
                          </ActionIcon>
                        </Tooltip>
                        {!version.is_current && (
                          <Tooltip label="Сделать текущей">
                            <ActionIcon
                              variant="subtle"
                              onClick={() =>
                                restoreVersion.mutate(version.id, {
                                  onSuccess: () =>
                                    notifications.show({
                                      color: 'teal',
                                      message: `Версия v${version.version} снова актуальна`,
                                    }),
                                })
                              }
                            >
                              <IconArrowBackUp size={18} />
                            </ActionIcon>
                          </Tooltip>
                        )}
                      </Group>
                    </Table.Td>
                  </Table.Tr>
                ))}
              </Table.Tbody>
            </Table>
          </Stack>
        </Tabs.Panel>

        {/* Список меток */}
        <Tabs.Panel value="placeholders" pt="md">
          <Stack>
            <Alert variant="light" title="Как читать список">
              Метки <Code>{'${org.*}'}</Code> и <Code>{'${doc.*}'}</Code> сервис заполняет сам:
              первые — реквизитами выбранной организации, вторые — номером и датой документа.
              Остальные становятся полями формы.
            </Alert>

            <Group gap="xs">
              {placeholders.map((placeholder) => {
                const isSystem =
                  placeholder.startsWith('org.') || placeholder.startsWith('doc.');

                return (
                  <Badge
                    key={placeholder}
                    variant={isSystem ? 'light' : 'outline'}
                    color={isSystem ? 'indigo' : 'gray'}
                    size="lg"
                    radius="sm"
                  >
                    {`\${${placeholder}}`}
                  </Badge>
                );
              })}
            </Group>

            <Text size="sm" c="dimmed">
              Файл текущей версии:{' '}
              <Anchor
                href={`/api/templates/${template.id}/versions/${template.current_version?.id}/download`}
              >
                {template.current_version?.original_name}
              </Anchor>
            </Text>
          </Stack>
        </Tabs.Panel>
      </Tabs>
    </Stack>
  );
}
