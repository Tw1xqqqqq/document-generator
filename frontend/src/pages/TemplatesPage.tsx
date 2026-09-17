import {
  ActionIcon,
  Alert,
  Badge,
  Button,
  Card,
  Group,
  LoadingOverlay,
  Modal,
  Select,
  SimpleGrid,
  Stack,
  Text,
  TextInput,
  Textarea,
  Title,
  Tooltip,
} from '@mantine/core';
import { Dropzone } from '@mantine/dropzone';
import { useForm } from '@mantine/form';
import { useDisclosure } from '@mantine/hooks';
import { notifications } from '@mantine/notifications';
import {
  IconFileTypeDocx,
  IconFileUpload,
  IconPlus,
  IconSettings,
  IconTrash,
  IconX,
} from '@tabler/icons-react';
import { useState } from 'react';
import { useNavigate } from 'react-router';

import { ApiError } from '../api/client';
import { useDeleteTemplate, useOrganizations, useTemplates, useUploadTemplate } from '../api/queries';
import type { Template } from '../api/types';

export function TemplatesPage() {
  const navigate = useNavigate();
  const [organizationFilter, setOrganizationFilter] = useState<string | null>(null);

  const { data: organizations } = useOrganizations();
  const { data: templates, isLoading } = useTemplates(
    organizationFilter ? Number(organizationFilter) : null,
  );
  const upload = useUploadTemplate();
  const remove = useDeleteTemplate();

  const [opened, { open, close }] = useDisclosure(false);
  const [file, setFile] = useState<File | null>(null);

  const form = useForm({
    initialValues: { name: '', description: '', organization_id: '' },
  });

  const organizationOptions =
    organizations?.map((organization) => ({
      value: String(organization.id),
      label: organization.name,
    })) ?? [];

  const handleUpload = form.onSubmit((values) => {
    if (!file) {
      notifications.show({ color: 'red', message: 'Выберите файл шаблона' });
      return;
    }

    upload.mutate(
      { ...values, file },
      {
        onSuccess: (response) => {
          const found = response.data.current_version?.placeholders.length ?? 0;
          notifications.show({
            color: 'teal',
            title: 'Шаблон загружен',
            message: `Найдено меток: ${found}. Проверьте настройку полей.`,
          });
          close();
          form.reset();
          setFile(null);
          navigate(`/templates/${response.data.id}`);
        },
        onError: (error) => {
          if (error instanceof ApiError && error.status === 422) {
            form.setErrors(error.fieldErrors);
            // Ошибку по файлу форма показать не может — выводим уведомлением
            if (error.fieldErrors.file) {
              notifications.show({ color: 'red', message: error.fieldErrors.file });
            }
            return;
          }
          notifications.show({ color: 'red', message: (error as Error).message });
        },
      },
    );
  });

  const handleDelete = (template: Template) => {
    const warning = template.documents_count
      ? `У шаблона есть выпущенные документы (${template.documents_count}). Они тоже будут удалены. Продолжить?`
      : `Удалить шаблон «${template.name}»?`;

    if (!window.confirm(warning)) return;

    remove.mutate(template.id, {
      onSuccess: () => notifications.show({ color: 'teal', message: 'Шаблон удалён' }),
    });
  };

  return (
    <Stack>
      <Group justify="space-between" align="flex-end">
        <div>
          <Title order={2}>Шаблоны</Title>
          <Text c="dimmed" size="sm">
            Печатные формы в docx с метками вида ${'{client_name}'}
          </Text>
        </div>
        <Group>
          <Select
            placeholder="Все организации"
            data={organizationOptions}
            value={organizationFilter}
            onChange={setOrganizationFilter}
            clearable
            w={220}
          />
          <Button leftSection={<IconPlus size={16} />} onClick={open}>
            Загрузить шаблон
          </Button>
        </Group>
      </Group>

      <div style={{ position: 'relative', minHeight: 120 }}>
        <LoadingOverlay visible={isLoading} />

        {templates?.length === 0 ? (
          <Alert icon={<IconFileTypeDocx size={18} />} title="Шаблонов пока нет">
            Загрузите docx-файл с метками — сервис сам определит поля для заполнения.
          </Alert>
        ) : (
          <SimpleGrid cols={{ base: 1, sm: 2, lg: 3 }}>
            {templates?.map((template) => (
              <Card key={template.id} padding="md">
                <Stack gap="xs" h="100%">
                  <Group justify="space-between" wrap="nowrap" align="flex-start">
                    <Text fw={600} lineClamp={2}>
                      {template.name}
                    </Text>
                    <Badge variant="light" size="sm">
                      v{template.current_version?.version ?? 1}
                    </Badge>
                  </Group>

                  <Text size="sm" c="dimmed" lineClamp={2}>
                    {template.description || 'Без описания'}
                  </Text>

                  <Group gap="xs">
                    <Badge variant="outline" color="gray" size="sm">
                      {template.organization?.name ?? 'Общий'}
                    </Badge>
                    <Badge variant="outline" color="gray" size="sm">
                      меток: {template.current_version?.placeholders.length ?? 0}
                    </Badge>
                    {Boolean(template.documents_count) && (
                      <Badge variant="outline" color="gray" size="sm">
                        документов: {template.documents_count}
                      </Badge>
                    )}
                  </Group>

                  <Group justify="space-between" mt="auto" pt="sm">
                    <Button
                      variant="light"
                      size="xs"
                      leftSection={<IconSettings size={14} />}
                      onClick={() => navigate(`/templates/${template.id}`)}
                    >
                      Настроить
                    </Button>
                    <Tooltip label="Удалить шаблон">
                      <ActionIcon variant="subtle" color="red" onClick={() => handleDelete(template)}>
                        <IconTrash size={18} />
                      </ActionIcon>
                    </Tooltip>
                  </Group>
                </Stack>
              </Card>
            ))}
          </SimpleGrid>
        )}
      </div>

      <Modal opened={opened} onClose={close} title="Загрузка шаблона" size="lg">
        <form onSubmit={handleUpload}>
          <Stack>
            <Dropzone
              onDrop={(files) => setFile(files[0])}
              onReject={() =>
                notifications.show({ color: 'red', message: 'Подойдёт только файл docx до 20 МБ' })
              }
              maxSize={20 * 1024 ** 2}
              accept={[
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
              ]}
              multiple={false}
            >
              <Group justify="center" gap="md" mih={120} style={{ pointerEvents: 'none' }}>
                <Dropzone.Accept>
                  <IconFileUpload size={42} stroke={1.4} />
                </Dropzone.Accept>
                <Dropzone.Reject>
                  <IconX size={42} stroke={1.4} />
                </Dropzone.Reject>
                <Dropzone.Idle>
                  <IconFileTypeDocx size={42} stroke={1.4} />
                </Dropzone.Idle>

                <div>
                  <Text size="sm" fw={500}>
                    {file ? file.name : 'Перетащите docx сюда или нажмите для выбора'}
                  </Text>
                  <Text size="xs" c="dimmed">
                    Метки в файле пишутся как ${'{client_name}'}, реквизиты — ${'{org.inn}'}
                  </Text>
                </div>
              </Group>
            </Dropzone>

            <TextInput
              label="Название шаблона"
              placeholder="Счёт на оплату"
              withAsterisk
              {...form.getInputProps('name')}
            />
            <Textarea
              label="Описание"
              placeholder="Для чего используется эта форма"
              autosize
              minRows={2}
              {...form.getInputProps('description')}
            />
            <Select
              label="Организация"
              description="Оставьте пустым, чтобы шаблон был доступен всем организациям"
              placeholder="Общий шаблон"
              data={organizationOptions}
              clearable
              {...form.getInputProps('organization_id')}
            />

            <Group justify="flex-end" mt="md">
              <Button variant="default" onClick={close}>
                Отмена
              </Button>
              <Button type="submit" loading={upload.isPending}>
                Загрузить
              </Button>
            </Group>
          </Stack>
        </form>
      </Modal>
    </Stack>
  );
}
