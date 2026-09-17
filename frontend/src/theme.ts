import { createTheme } from '@mantine/core';

/** Оформление приложения: спокойная деловая палитра и единые скругления. */
export const theme = createTheme({
  primaryColor: 'indigo',
  defaultRadius: 'md',
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
  },
});
