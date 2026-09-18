import {
  ActionIcon,
  Alert,
  Badge,
  Button,
  Group,
  LoadingOverlay,
  Select,
  Stack,
  Table,
  Text,
  TextInput,
  Title,
  Tooltip,
} from '@mantine/core';
import { notifications } from '@mantine/notifications';
import { IconFileText, IconSearch, IconTrash } from '@tabler/icons-react';
import { useState } from 'react';

import { useDeleteDocument, useDocuments, useOrganizations, useTemplates } from '../api/queries';
import type { Document } from '../api/types';

export function DocumentsPage() {
  const [organizationId, setOrganizationId] = useState<string | null>(null);
  const [templateId, setTemplateId] = useState<string | null>(null);
  const [search, setSearch] = useState('');

  const { data: organizations } = useOrganizations();
  const { data: templates } = useTemplates(organizationId ? Number(organizationId) : null);
  const { data: documents, isLoading } = useDocuments({
    organization_id: organizationId ? Number(organizationId) : undefined,
    template_id: templateId ? Number(templateId) : undefined,
    search: search || undefined,
  });

  const remove = useDeleteDocument();

  const handleDelete = (document: Document) => {
    if (!window.confirm(`Удалить документ «${document.name}» вместе с файлами?`)) return;

    remove.mutate(document.id, {
      onSuccess: () => notifications.show({ color: 'teal', message: 'Документ удалён' }),
    });
  };

  return (
    <Stack>
      <div>
        <Title order={2}>Документы</Title>
        <Text c="dimmed" size="sm">
          Журнал выпущенных печатных форм
        </Text>
      </div>

      <Group>
        <Select
          placeholder="Все организации"
          data={organizations?.map((o) => ({ value: String(o.id), label: o.name })) ?? []}
          value={organizationId}
          onChange={setOrganizationId}
          clearable
          w={220}
        />
        <Select
          placeholder="Все шаблоны"
          data={templates?.map((t) => ({ value: String(t.id), label: t.name })) ?? []}
          value={templateId}
          onChange={setTemplateId}
          clearable
          w={240}
        />
        <TextInput
          placeholder="Поиск по названию"
          leftSection={<IconSearch size={16} />}
          value={search}
          onChange={(event) => setSearch(event.currentTarget.value)}
          w={260}
        />
      </Group>

      <div style={{ position: 'relative', minHeight: 120 }}>
        <LoadingOverlay visible={isLoading} />

        {documents?.data.length === 0 ? (
          <Alert icon={<IconFileText size={18} />} title="Документов пока нет">
            Создайте первый документ на вкладке «Генерация».
          </Alert>
        ) : (
          <Table.ScrollContainer minWidth={900}>
            <Table verticalSpacing="sm" highlightOnHover>
              <Table.Thead>
                <Table.Tr>
                  <Table.Th>Документ</Table.Th>
                  <Table.Th>Шаблон</Table.Th>
                  <Table.Th>Организация</Table.Th>
                  <Table.Th w={170}>Создан</Table.Th>
                  <Table.Th w={230} />
                </Table.Tr>
              </Table.Thead>
              <Table.Tbody>
                {documents?.data.map((document) => (
                  <Table.Tr key={document.id}>
                    <Table.Td>
                      <Text fw={500}>{document.name}</Text>
                    </Table.Td>
                    <Table.Td>
                      <Group gap="xs">
                        <Text size="sm">{document.template?.name}</Text>
                        {document.template_version && (
                          <Badge size="xs" variant="light">
                            v{document.template_version.version}
                          </Badge>
                        )}
                      </Group>
                    </Table.Td>
                    <Table.Td>
                      <Text size="sm">{document.organization?.name}</Text>
                    </Table.Td>
                    <Table.Td>
                      <Text size="sm">{new Date(document.created_at).toLocaleString('ru-RU')}</Text>
                    </Table.Td>
                    <Table.Td>
                      <Group gap="xs" justify="flex-end">
                        <Button
                          size="xs"
                          variant="light"
                          component="a"
                          href={document.pdf_url ?? '#'}
                        >
                          PDF
                        </Button>
                        <Button
                          size="xs"
                          variant="subtle"
                          component="a"
                          href={document.docx_url ?? '#'}
                        >
                          DOCX
                        </Button>
                        <Tooltip label="Удалить">
                          <ActionIcon
                            variant="subtle"
                            color="red"
                            onClick={() => handleDelete(document)}
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
    </Stack>
  );
}
