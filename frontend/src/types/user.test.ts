import { describe, expect, it } from 'vitest';
import '../test/setup';
import { dashboardRouteFor, type UserRoleName } from './user';

describe('dashboardRouteFor', () => {
  it.each<[UserRoleName, string]>([
    ['super_administrator', '/platform/dashboard'],
    ['administrator', '/admin/dashboard'],
    ['secretary', '/secretary/dashboard'],
    ['teacher', '/teacher/dashboard'],
    ['parent', '/parent/dashboard'],
  ])('routes %s to its isolated portal', (role, expected) => {
    expect(dashboardRouteFor(role)).toBe(expected);
  });
});
