import { describe, expect, it } from 'vitest';
import { adsAllowedOn } from './AdSlot';

describe('ad placement (D-66)', () => {
  it('shows only on public tool pages', () => {
    for (const p of ['/', '/parser', '/hunting', '/hunting/cobra-bastion', '/yasir', '/drops', '/hunt-analyser/public', '/hunt-analyser/ranking']) expect(adsAllowedOn(p)).toBe(true);
    for (const p of ['/admin', '/admin/finance', '/account', '/pricing', '/login', '/register', '/forgot', '/reset', '/verify', '/hunt-analyser', '/hunt-analyser/mine', '/hunt-analyser/payments', '/support', '/privacy'])
      expect(adsAllowedOn(p)).toBe(false);
  });
});
