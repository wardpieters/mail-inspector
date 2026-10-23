import { expect, it } from 'vitest';
import { EXAMPLE_HEADERS } from './example';
import { analyzeHeaders } from './analyzeHeaders';
it('provides a useful synthetic multi-provider example', () => {
    const result = analyzeHeaders(EXAMPLE_HEADERS).analysis!;
    expect(result.delivery.hops.length).toBeGreaterThanOrEqual(2);
    expect(result.authentication.observations.length).toBeGreaterThan(0);
    expect(result.filtering.observations.map(o => o.family)).toEqual(expect.arrayContaining(['rspamd', 'spamassassin-compatible']));
    expect(EXAMPLE_HEADERS).toContain('example.org');
    expect(EXAMPLE_HEADERS).toContain('192.0.2.');
});
