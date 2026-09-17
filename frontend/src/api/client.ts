/**
 * Тонкая обёртка над fetch.
 *
 * Задачи: единый базовый адрес, заголовки JSON и — главное — разбор
 * ошибок валидации Laravel (код 422), чтобы формы могли показать
 * сообщение под конкретным полем.
 */

const BASE_URL = '/api';

export class ApiError extends Error {
  readonly status: number;
  readonly errors: Record<string, string[]>;

  constructor(message: string, status: number, errors: Record<string, string[]> = {}) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.errors = errors;
  }

  /** Ошибки в виде { поле: 'сообщение' } — формат, который понимает Mantine. */
  get fieldErrors(): Record<string, string> {
    return Object.fromEntries(
      Object.entries(this.errors).map(([field, messages]) => [field, messages[0]]),
    );
  }
}

async function handle<T>(response: Response): Promise<T> {
  if (response.status === 204) {
    return undefined as T;
  }

  const isJson = response.headers.get('content-type')?.includes('application/json');
  const payload = isJson ? await response.json() : await response.text();

  if (!response.ok) {
    const message =
      (typeof payload === 'object' && payload?.message) || 'Не удалось выполнить запрос';

    throw new ApiError(message, response.status, payload?.errors ?? {});
  }

  return payload as T;
}

export const api = {
  get<T>(path: string, params?: Record<string, string | number | undefined>): Promise<T> {
    const query = params
      ? '?' +
        new URLSearchParams(
          Object.entries(params)
            .filter(([, value]) => value !== undefined && value !== '')
            .map(([key, value]) => [key, String(value)]),
        )
      : '';

    return fetch(`${BASE_URL}${path}${query}`, {
      headers: { Accept: 'application/json' },
    }).then(handle<T>);
  },

  post<T>(path: string, body?: unknown): Promise<T> {
    return fetch(`${BASE_URL}${path}`, {
      method: 'POST',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify(body ?? {}),
    }).then(handle<T>);
  },

  put<T>(path: string, body?: unknown): Promise<T> {
    return fetch(`${BASE_URL}${path}`, {
      method: 'PUT',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify(body ?? {}),
    }).then(handle<T>);
  },

  delete<T>(path: string): Promise<T> {
    return fetch(`${BASE_URL}${path}`, {
      method: 'DELETE',
      headers: { Accept: 'application/json' },
    }).then(handle<T>);
  },

  /**
   * Отправка формы с файлом.
   * Content-Type здесь не указываем: браузер сам подставит его
   * вместе с границей multipart.
   */
  postForm<T>(path: string, formData: FormData): Promise<T> {
    return fetch(`${BASE_URL}${path}`, {
      method: 'POST',
      headers: { Accept: 'application/json' },
      body: formData,
    }).then(handle<T>);
  },

  /** Запрос, возвращающий файл (предпросмотр PDF). */
  async postBlob(path: string, body: unknown): Promise<Blob> {
    const response = await fetch(`${BASE_URL}${path}`, {
      method: 'POST',
      headers: { Accept: 'application/pdf, application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    });

    if (!response.ok) {
      return handle(response);
    }

    return response.blob();
  },
};
