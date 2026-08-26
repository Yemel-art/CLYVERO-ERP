/**
 * The standard API response envelope from the Laravel backend.
 * Source: API Blueprint §5.
 */
export interface ApiSuccess<T = unknown> {
  success: true;
  message: string;
  data?: T;
  meta?: ApiMeta;
}

export interface ApiError {
  success: false;
  message: string;
  error_code?: string;
  errors?: Record<string, string[] | string>;
}

export type ApiResponse<T = unknown> = ApiSuccess<T> | ApiError;

export interface ApiMeta {
  page?: number;
  per_page?: number;
  total?: number;
  last_page?: number;
  [key: string]: unknown;
}

export interface Paginated<T> {
  data: T[];
  meta: Required<Pick<ApiMeta, 'page' | 'per_page' | 'total' | 'last_page'>>;
}
