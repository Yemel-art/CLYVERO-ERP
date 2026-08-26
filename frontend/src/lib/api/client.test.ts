import { describe, expect, it } from 'vitest';
import '../../test/setup';
import { tokenStorage, unwrap } from './client';

describe('tokenStorage', () => {
  it('keeps the active identity isolated in the current tab', () => {
    tokenStorage.set('teacher-token');

    expect(window.sessionStorage.getItem('clyvero.auth.tab-token')).toBe('teacher-token');
    expect(window.localStorage.getItem('clyvero.auth.remembered-token')).toBeNull();
    expect(tokenStorage.get()).toBe('teacher-token');
  });

  it('stores the optional remembered token without replacing tab isolation', () => {
    tokenStorage.set('admin-token', true);

    expect(window.sessionStorage.getItem('clyvero.auth.tab-token')).toBe('admin-token');
    expect(window.localStorage.getItem('clyvero.auth.remembered-token')).toBe('admin-token');
  });

  it('clears only the remembered token belonging to the active tab', () => {
    tokenStorage.set('teacher-token');
    window.localStorage.setItem('clyvero.auth.remembered-token', 'admin-token');

    tokenStorage.clear();

    expect(window.sessionStorage.getItem('clyvero.auth.tab-token')).toBeNull();
    expect(window.localStorage.getItem('clyvero.auth.remembered-token')).toBe('admin-token');
  });

  it('does not silently restore a remembered account on the login screen', () => {
    window.localStorage.setItem('clyvero.auth.remembered-token', 'remembered-token');
    window.history.replaceState({}, '', '/login');

    expect(tokenStorage.get()).toBeNull();
    expect(window.sessionStorage.getItem('clyvero.auth.tab-token')).toBeNull();
  });
});

describe('unwrap', () => {
  it('returns successful API data', () => {
    expect(unwrap({ success: true, message: 'ok', data: { id: 7 } })).toEqual({ id: 7 });
  });

  it('preserves backend error details', () => {
    expect(() => unwrap({
      success: false,
      message: 'Invalid request',
      error_code: 'validation_failed',
      errors: { email: ['Required'] },
    })).toThrow('Invalid request');
  });
});
