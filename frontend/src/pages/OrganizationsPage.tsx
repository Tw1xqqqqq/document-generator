import {
  ActionIcon,
  Alert,
  Avatar,
  Button,
  Divider,
  FileInput,
  Group,
  LoadingOverlay,
  Modal,
  SimpleGrid,
  Stack,
  Table,
  Text,
  TextInput,
  Textarea,
  Title,
  Tooltip,
} from '@mantine/core';
import { useForm } from '@mantine/form';
import { useDisclosure } from '@mantine/hooks';
import { notifications } from '@mantine/notifications';
import { IconBuildingSkyscraper, IconPencil, IconPhoto, IconPlus, IconTrash } from '@tabler/icons-react';
import { useState } from 'react';

import { ApiError } from '../api/client';
import { useDeleteOrganization, useOrganizations, useSaveOrganization } from '../api/queries';
import type { Organization } from '../api/types';

const emptyValues = {
  name: '',
  full_name: '',
  inn: '',
  kpp: '',
  ogrn: '',
  legal_address: '',
  actual_address: '',
  phone: '',
  email: '',
  bank_name: '',
  bank_account: '',
  bank_corr_account: '',
  bank_bik: '',
  director_name: '',
  director_position: 'Генеральный директор',
};

export function OrganizationsPage() {
  const { data: organizations, isLoading } = useOrganizations();
  const save = useSaveOrganization();
  const remove = useDeleteOrganization();

  const [opened, { open, close }] = useDisclosure(false);
  const [editing, setEditing] = useState<Organization | null>(null);
  const [logo, setLogo] = useState<File | null>(null);

  const form = useForm({ initialValues: emptyValues });

  const openCreate = () => {
    setEditing(null);
    setLogo(null);
    form.setValues(emptyValues);
    open();
  };

  const openEdit = (organization: Organization) => {
    setEditing(organization);
    setLogo(null);
    // null из базы превращаем в пустые строки: управляемые поля ввода
    // не должны получать null, иначе React ругается
    const values = { ...emptyValues };

    (Object.keys(emptyValues) as (keyof typeof emptyValues)[]).forEach((key) => {
      values[key] = (organization[key] as string | null) ?? '';
    });

    form.setValues(values);
    open();
  };

  const handleSubmit = form.onSubmit((values) => {
    save.mutate(
      { id: editing?.id, values, logo },
      {
        onSuccess: () => {
          notifications.show({
            color: 'teal',
            message: editing ? 'Организация обновлена' : 'Организация добавлена',
          });
          close();
        },
        onError: (error) => {
          // Ошибки валидации раскладываем по полям формы
          if (error instanceof ApiError && error.status === 422) {
            form.setErrors(error.fieldErrors);
            return;
          }
          notifications.show({ color: 'red', message: (error as Error).message });
        },
      },
    );
  });

  const handleDelete = (organization: Organization) => {
    const warning = organization.documents_count
      ? `Вместе с организацией удалятся её документы (${organization.documents_count}). Продолжить?`
      : `Удалить организацию «${organization.name}»?`;

    if (!window.confirm(warning)) return;

    remove.mutate(organization.id, {
      onSuccess: () => notifications.show({ color: 'teal', message: 'Организация удалена' }),
    });
  };

  return (
    <Stack>
      <Group justify="space-between">
        <div>
          <Title order={2}>Организации</Title>
          <Text c="dimmed" size="sm">
            Реквизиты для подстановки в документы
          </Text>
        </div>
        <Button leftSection={<IconPlus size={16} />} onClick={openCreate}>
          Добавить организацию
        </Button>
      </Group>

      <div style={{ position: 'relative' }}>
        <LoadingOverlay visible={isLoading} />

        {organizations?.length === 0 ? (
          <Alert icon={<IconBuildingSkyscraper size={18} />} title="Организаций пока нет">
            Добавьте организацию, чтобы выпускать документы от её имени.
          </Alert>
        ) : (
          <Table.ScrollContainer minWidth={800}>
            <Table verticalSpacing="sm" highlightOnHover>
              <Table.Thead>
                <Table.Tr>
                  <Table.Th>Организация</Table.Th>
                  <Table.Th>ИНН / КПП</Table.Th>
                  <Table.Th>Руководитель</Table.Th>
                  <Table.Th>Шаблоны</Table.Th>
                  <Table.Th>Документы</Table.Th>
                  <Table.Th />
                </Table.Tr>
              </Table.Thead>
              <Table.Tbody>
                {organizations?.map((organization) => (
                  <Table.Tr key={organization.id}>
                    <Table.Td>
                      <Group gap="sm">
                        <Avatar src={organization.logo_url} radius="sm" size={36}>
                          {organization.name.slice(0, 1)}
                        </Avatar>
                        <div>
                          <Text fw={500}>{organization.name}</Text>
                          <Text size="xs" c="dimmed" lineClamp={1}>
                            {organization.legal_address || '-'}
                          </Text>
                        </div>
                      </Group>
                    </Table.Td>
                    <Table.Td>
                      <Text size="sm">{organization.inn || '-'}</Text>
                      <Text size="xs" c="dimmed">
                        {organization.kpp || ''}
                      </Text>
                    </Table.Td>
                    <Table.Td>
                      <Text size="sm">{organization.director_name || '-'}</Text>
                      <Text size="xs" c="dimmed">
                        {organization.director_position || ''}
                      </Text>
                    </Table.Td>
                    <Table.Td>{organization.templates_count ?? 0}</Table.Td>
                    <Table.Td>{organization.documents_count ?? 0}</Table.Td>
                    <Table.Td>
                      <Group gap="xs" justify="flex-end">
                        <Tooltip label="Редактировать">
                          <ActionIcon variant="subtle" onClick={() => openEdit(organization)}>
                            <IconPencil size={18} />
                          </ActionIcon>
                        </Tooltip>
                        <Tooltip label="Удалить">
                          <ActionIcon
                            variant="subtle"
                            color="red"
                            onClick={() => handleDelete(organization)}
                          >
                            <IconTrash size={18} />
                          </ActionIcon>
                        </Tooltip>
                      </Group>
                    </Table.Td>
                  </Table.Tr>
                ))}
              </Table.Tbody>
            </Table>
          </Table.ScrollContainer>
        )}
      </div>

      <Modal
        opened={opened}
        onClose={close}
        title={editing ? 'Редактирование организации' : 'Новая организация'}
        size="lg"
      >
        <form onSubmit={handleSubmit}>
          <Stack gap="sm">
            <TextInput
              label="Краткое название"
              placeholder="ООО «Ромашка»"
              withAsterisk
              {...form.getInputProps('name')}
            />
            <TextInput
              label="Полное наименование"
              placeholder="Общество с ограниченной ответственностью «Ромашка»"
              {...form.getInputProps('full_name')}
            />

            <SimpleGrid cols={{ base: 1, sm: 3 }}>
              <TextInput label="ИНН" {...form.getInputProps('inn')} />
              <TextInput label="КПП" {...form.getInputProps('kpp')} />
              <TextInput label="ОГРН" {...form.getInputProps('ogrn')} />
            </SimpleGrid>

            <Divider label="Адреса и связь" labelPosition="left" />

            <Textarea
              label="Юридический адрес"
              autosize
              minRows={2}
              {...form.getInputProps('legal_address')}
            />
            <Textarea
              label="Фактический адрес"
              autosize
              minRows={2}
              {...form.getInputProps('actual_address')}
            />
            <SimpleGrid cols={{ base: 1, sm: 2 }}>
              <TextInput label="Телефон" {...form.getInputProps('phone')} />
              <TextInput label="Email" {...form.getInputProps('email')} />
            </SimpleGrid>

            <Divider label="Банковские реквизиты" labelPosition="left" />

            <TextInput label="Банк" {...form.getInputProps('bank_name')} />
            <SimpleGrid cols={{ base: 1, sm: 3 }}>
              <TextInput label="Расчётный счёт" {...form.getInputProps('bank_account')} />
              <TextInput label="Корр. счёт" {...form.getInputProps('bank_corr_account')} />
              <TextInput label="БИК" {...form.getInputProps('bank_bik')} />
            </SimpleGrid>

            <Divider label="Подписант и логотип" labelPosition="left" />

            <SimpleGrid cols={{ base: 1, sm: 2 }}>
              <TextInput label="ФИО руководителя" {...form.getInputProps('director_name')} />
              <TextInput label="Должность" {...form.getInputProps('director_position')} />
            </SimpleGrid>

            <FileInput
              label="Логотип"
              description="PNG или JPG до 2 МБ"
              placeholder="Выберите файл"
              accept="image/png,image/jpeg"
              leftSection={<IconPhoto size={16} />}
              value={logo}
              onChange={setLogo}
              clearable
            />

            <Group justify="flex-end" mt="md">
              <Button variant="default" onClick={close}>
                Отмена
              </Button>
              <Button type="submit" loading={save.isPending}>
                Сохранить
              </Button>
            </Group>
          </Stack>
        </form>
      </Modal>
    </Stack>
  );
}
