export type Platform = 'instagram' | 'linkedin' | 'tiktok' | 'x';
export type PostStatus = 'draft' | 'pending' | 'queued' | 'publishing' | 'published' | 'failed';
export type AccountStatus = 'active' | 'needs_reauth';
export type GenerationStatus = 'pending' | 'running' | 'done' | 'failed';

export interface User {
  id: number;
  email: string;
  timezone: string;
}

export interface AuthResponse {
  token: string;
  expiresAt: string | null;
  user: User;
}

export interface SocialAccount {
  id: number;
  platform: Platform;
  externalAccountId: string;
  status: AccountStatus;
  tokenExpiresAt: string | null;
}

export interface AccountsResponse {
  items: SocialAccount[];
  supportedPlatforms: Platform[];
}

export interface SaveAccountInput {
  externalAccountId: string;
  accessToken: string;
  tokenExpiresAt: string | null;
}

export interface Media {
  id: number;
  type: string;
  size: number;
  publicUrl: string;
}

export interface Generation {
  id: number;
  status: GenerationStatus;
  model: string;
  resultText: string | null;
  error: string | null;
  createdAt: string;
}

export interface Post {
  id: number;
  socialAccountId: number;
  platform: Platform;
  text: string;
  media: Media | null;
  status: PostStatus;
  scheduledAt: string | null;
  externalId: string | null;
  lastError: string | null;
  attempts: number;
  createdAt: string;
  updatedAt: string;
}

export interface PostInput {
  socialAccountId: number;
  text: string;
  mediaAssetId: number | null;
  scheduledAt: string | null;
  generationRequestId?: number;
}
