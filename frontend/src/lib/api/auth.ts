import { apiClient, unwrap } from './client';
import type { ApiResponse } from '@/types/api';
import type { AuthenticatedLoginResponse, LoginResponse, User } from '@/types/user';

/**
 * Auth API client. Each function returns the unwrapped `data` payload
 * (or throws if `success: false`).
 */
export const authApi = {
  async login(email: string, password: string, rememberMe = false, schoolSlug?: string): Promise<LoginResponse> {
    const { data } = await apiClient.post<ApiResponse<LoginResponse>>('/login', {
      email,
      password,
      remember_me: rememberMe,
      school_slug: schoolSlug || undefined,
    });
    return unwrap(data);
  },

  async verifyLoginOtp(challengeId: string, code: string): Promise<AuthenticatedLoginResponse> {
    const { data } = await apiClient.post<ApiResponse<AuthenticatedLoginResponse>>('/login/verify-otp', {
      challenge_id: challengeId,
      code,
    });
    return unwrap(data);
  },

  async logout(): Promise<void> {
    await apiClient.post<ApiResponse<null>>('/logout');
  },

  async me(): Promise<User> {
    const { data } = await apiClient.get<ApiResponse<User>>('/me');
    return unwrap(data);
  },

  async updateProfile(input: {
    first_name: string;
    last_name: string;
    email: string;
    phone: string | null;
    current_password?: string;
  }): Promise<User> {
    const { data } = await apiClient.patch<ApiResponse<User>>('/me', input);
    return unwrap(data);
  },

  async changePassword(input: {
    currentPassword: string;
    password: string;
    passwordConfirmation: string;
  }): Promise<void> {
    await apiClient.put<ApiResponse<null>>('/me/password', {
      current_password: input.currentPassword,
      password: input.password,
      password_confirmation: input.passwordConfirmation,
    });
  },

  async forgotPassword(email: string, schoolSlug?: string): Promise<void> {
    await apiClient.post<ApiResponse<null>>('/forgot-password', { email, school_slug: schoolSlug || undefined });
  },

  async resetPassword(input: {
    token: string;
    email: string;
    password: string;
    passwordConfirmation: string;
    schoolSlug?: string;
  }): Promise<void> {
    await apiClient.post<ApiResponse<null>>('/reset-password', {
      token: input.token,
      email: input.email,
      password: input.password,
      password_confirmation: input.passwordConfirmation,
      school_slug: input.schoolSlug || undefined,
    });
  },

  async refreshToken(): Promise<AuthenticatedLoginResponse> {
    const { data } = await apiClient.post<ApiResponse<AuthenticatedLoginResponse>>('/refresh-token');
    return unwrap(data);
  },
};
