import { AppShell, Group, NavLink, Stack, Text } from '@mantine/core';
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
  { to: '/generate', label: 'Генерация', icon: IconWand },
  { to: '/templates', label: 'Шаблоны', icon: IconFileStack },
  { to: '/organizations', label: 'Организации', icon: IconBuildingSkyscraper },
  { to: '/documents', label: 'Документы', icon: IconFileText },
];

export function App() {
  const location = useLocation();
  const navigate = useNavigate();

  return (
    <AppShell header={{ height: 60 }} navbar={{ width: 260, breakpoint: 'sm' }} padding="lg">
      <AppShell.Header>
        <Group h="100%" px="md">
          <Text fw={600} size="lg">
            Генератор документов
          </Text>
        </Group>
      </AppShell.Header>

      <AppShell.Navbar p="sm">
        <Stack gap={4}>
          {navigation.map((item) => (
            <NavLink
              key={item.to}
              label={item.label}
              leftSection={<item.icon size={18} stroke={1.6} />}
              active={location.pathname.startsWith(item.to)}
              onClick={() => navigate(item.to)}
            />
          ))}
        </Stack>
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
