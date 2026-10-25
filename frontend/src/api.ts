import type {
  AccountsResponse, AuthResponse, Generation, Media, Post, PostInput, PostStatus,
  SaveAccountInput, SocialAccount, User,
} from './types';

const BASE = (import.meta.env.VITE_API_URL || 'http://localhost:8080').replace(/\/$/, '');
const KEY = 'postpickr.token';

let onUnauthorized: () => void = () => {};
export const setOnUnauthorized = (fn: () => void): void => {
  onUnauthorized = fn;
};

export const getToken = (): string | null => {
  try { return localStorage.getItem(KEY); } catch { return null; }
};
export const setToken = (t: string | null): void => {
  try { if (t) localStorage.setItem(KEY, t); else localStorage.removeItem(KEY); } catch { /* storage non disponibile */ }
};

export class ApiError extends Error {
  readonly status: number;
  constructor(status: number, message: string) {
    super(message);
    this.status = status;
  }
}

async function request<T>(method: string, path: string, body?: unknown, isForm = false): Promise<T> {
  const headers: Record<string, string> = {};
  const token = getToken();
  if (token) headers.Authorization = `Bearer ${token}`;
  if (body && !isForm) headers['Content-Type'] = 'application/json';

  const res = await fetch(BASE + path, {
    method,
    headers,
    body: body ? (isForm ? (body as FormData) : JSON.stringify(body)) : undefined,
  });
  if (res.status === 204) return null as T;
  const data = (await res.json().catch(() => null)) as (T & { error?: string }) | null;
  if (res.status === 401 && token) onUnauthorized();
  if (!res.ok) throw new ApiError(res.status, data?.error || `Errore ${res.status}`);
  return data as T;
}

export const api = {
  register: (b: { email: string; password: string; timezone: string }) => request<AuthResponse>('POST', '/api/auth/register', b),
  login: (b: { email: string; password: string }) => request<AuthResponse>('POST', '/api/auth/login', b),
  logout: () => request<null>('POST', '/api/auth/logout'),
  me: () => request<User>('GET', '/api/me'),

  accounts: () => request<AccountsResponse>('GET', '/api/social-accounts'),
  saveAccount: (platform: string, b: SaveAccountInput) => request<SocialAccount>('PUT', `/api/social-accounts/${platform}`, b),

  uploadMedia: (file: File) => {
    const fd = new FormData();
    fd.append('file', file);
    return request<Media>('POST', '/api/media', fd, true);
  },

  generate: (b: { text: string; maxTokens?: number }) => request<Generation>('POST', '/api/generations', b),
  generation: (id: number) => request<Generation>('GET', `/api/generations/${id}`),

  posts: (status?: PostStatus | '') => request<{ items: Post[] }>('GET', '/api/posts' + (status ? `?status=${status}` : '')),
  createPost: (b: PostInput) => request<Post>('POST', '/api/posts', b),
  updatePost: (id: number, b: PostInput) => request<Post>('PATCH', `/api/posts/${id}`, b),
  deletePost: (id: number) => request<null>('DELETE', `/api/posts/${id}`),
  approve: (id: number) => request<Post>('POST', `/api/posts/${id}/approve`),
  unschedule: (id: number) => request<Post>('POST', `/api/posts/${id}/unschedule`),
  retry: (id: number) => request<Post>('POST', `/api/posts/${id}/retry`),
};
