// Стили Mantine подключаются один раз и до собственных стилей приложения
import '@mantine/core/styles.css';
import '@mantine/notifications/styles.css';
import '@mantine/dropzone/styles.css';
import '@mantine/dates/styles.css';
import './index.css';

import { MantineProvider } from '@mantine/core';
import { DatesProvider } from '@mantine/dates';
import { Notifications } from '@mantine/notifications';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router';
import 'dayjs/locale/ru';

import { App } from './App';
import { theme } from './theme';

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      // Данных немного, поэтому лишние перезапросы при возврате на вкладку не нужны
      refetchOnWindowFocus: false,
      retry: 1,
    },
  },
});

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <MantineProvider theme={theme}>
      <DatesProvider settings={{ locale: 'ru', firstDayOfWeek: 1 }}>
        <QueryClientProvider client={queryClient}>
          <BrowserRouter>
            <Notifications position="top-right" />
            <App />
          </BrowserRouter>
        </QueryClientProvider>
      </DatesProvider>
    </MantineProvider>
  </StrictMode>,
);
