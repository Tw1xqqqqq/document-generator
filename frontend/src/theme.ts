import { createTheme } from '@mantine/core';

/** Оформление приложения: деловая палитра, спокойные акценты. */
export const theme = createTheme({
  primaryColor: 'indigo',
  defaultRadius: 'sm',
  fontFamily:
    '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif',
  headings: {
    fontWeight: '600',
  },
  components: {
    Card: {
      defaultProps: {
        withBorder: true,
        shadow: 'none',
      },
    },
    Badge: {
      defaultProps: {
        // По умолчанию Mantine печатает содержимое плашек капсом,
        // из-за чего интерфейс выглядит крикливо
        tt: 'none',
        fw: 500,
        variant: 'default',
      },
    },
  },
});
