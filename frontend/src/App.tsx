import { AppShell, Badge, Group, NavLink, Stack, Text, Title } from '@mantine/core';
import {
  IconBuildingSkyscraper,
  IconFileStack,
  IconFileText,
  IconWand,
} from '@tabler/icons-react';
import { Navigate, Route, Routes, useLocation, useNavigate } from 'react-router';

import { DocumentsPage } from './pages/DocumentsPage';
import { GeneratePage } from './pages/GeneratePage';
import { OrganizationsPage } from './pages/OrganizationsPage';
import { TemplateDetailPage } from './pages/TemplateDetailPage';
import { TemplatesPage } from './pages/TemplatesPage';

const navigation = [
  { to: '/generate', label: 'Генерация', icon: IconWand, description: 'Создать документ' },
  { to: '/templates', label: 'Шаблоны', icon: IconFileStack, description: 'Печатные формы' },
  { to: '/organizations', label: 'Организации', icon: IconBuildingSkyscraper, description: 'Реквизиты' },
  { to: '/documents', label: 'Документы', icon: IconFileText, description: 'Журнал выпущенных' },
];

export function App() {
  const location = useLocation();
  const navigate = useNavigate();

  return (
    <AppShell header={{ height: 60 }} navbar={{ width: 260, breakpoint: 'sm' }} padding="lg">
      <AppShell.Header>
        <Group h="100%" px="md" justify="space-between">
          <Group gap="xs">
            <Title order={4}>Генератор документов</Title>
            <Badge variant="light" size="sm">
              docx → pdf
            </Badge>
          </Group>
        </Group>
      </AppShell.Header>

      <AppShell.Navbar p="sm">
        <Stack gap={4}>
          {navigation.map((item) => (
            <NavLink
              key={item.to}
              label={item.label}
              description={item.description}
              leftSection={<item.icon size={18} stroke={1.6} />}
              active={location.pathname.startsWith(item.to)}
              onClick={() => navigate(item.to)}
            />
          ))}
        </Stack>

        <Text size="xs" c="dimmed" mt="auto" p="xs">
          Метки в шаблоне: <Text span ff="monospace">{'${поле}'}</Text>
          <br />
          Реквизиты организации подставляются автоматически.
        </Text>
      </AppShell.Navbar>

      <AppShell.Main>
        <Routes>
          <Route path="/" element={<Navigate to="/generate" replace />} />
          <Route path="/generate" element={<GeneratePage />} />
          <Route path="/templates" element={<TemplatesPage />} />
          <Route path="/templates/:id" element={<TemplateDetailPage />} />
          <Route path="/organizations" element={<OrganizationsPage />} />
          <Route path="/documents" element={<DocumentsPage />} />
        </Routes>
      </AppShell.Main>
    </AppShell>
  );
}
