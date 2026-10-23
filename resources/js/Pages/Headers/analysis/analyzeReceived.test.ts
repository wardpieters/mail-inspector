import { expect, it } from 'vitest';
import { analyzeReceived } from './analyzeReceived';
import { parseHeaders } from './parseHeaders';
const analyze = (input: string) => analyzeReceived(parseHeaders(input).document!);
it('preserves source order reversed, never bridging a missing intermediate date', () => {
    const result = analyze('Received: from b by c; 23 Oct 2026 10:01:00 +0000\nReceived: from a by b; invalid\nReceived: by a; 23 Oct 2026 10:00:00 +0000');
    expect(result.hops.map(h => h.fieldId)).toEqual([2, 1, 0]);
    expect(result.hops.map(h => h.delayMs)).toEqual([null, null, null]);
    expect(result.observedTransitMs).toBe(60000);
    expect(result.warnings.map(w => w.code)).toContain('hop_date');
});
it('calculates adjacent delays and proportional positive bars', () => {
    const result = analyze('Received: by c; 23 Oct 2026 10:00:40 +0000\nReceived: by b; 23 Oct 2026 10:00:10 +0000\nReceived: by a; 23 Oct 2026 10:00:00 +0000');
    expect(result.hops.map(h => h.delayMs)).toEqual([null, 10000, 30000]);
    expect(result.hops.map(h => h.delayPercent)).toEqual([null, 25, 75]);
    expect(result.observedTransitMs).toBe(40000);
});
it('keeps negative and zero delays rather than sorting clocks', () => {
    const result = analyze('Received: by b; 23 Oct 2026 10:00:00 +0000\nReceived: by a; 23 Oct 2026 10:00:10 +0000');
    expect(result.hops.map(h => h.by)).toEqual(['a', 'b']);
    expect(result.hops[1].delayMs).toBe(-10000);
    expect(result.hops[1].delayPercent).toBeNull();
    expect(result.observedTransitMs).toBe(-10000);
    expect(result.warnings.map(w => w.code)).toContain('negative_delay');
    const equal = analyze('Received: by b; 23 Oct 2026 10:00:00 +0000\nReceived: by a; 23 Oct 2026 10:00:00 +0000');
    expect(equal.observedTransitMs).toBe(0);
    expect(equal.hops[1].delayPercent).toBeNull();
});
it('extracts transport evidence without treating comments as fields', () => {
    const result = analyze('Received: from sender.example (by fake.example [192.0.2.1]) by mx.example with ESMTPS id abc for <user@example.org>; 23 Oct 2026 10:00:00 +0000\nReceived: from edge.example ([IPv6:2001:db8::1]) by sender.example; 23 Oct 2026 09:59:00 +0000');
    expect(result.hops[1]).toMatchObject({ by: 'mx.example', protocol: 'ESMTPS', id: 'abc', for: '<user@example.org>', ips: ['192.0.2.1'] });
    expect(result.hops[0].ips).toEqual(['2001:db8::1']);
});
it('leaves totals unknown for zero/one hop or an invalid endpoint', () => {
    expect(analyze('Subject: hi').hops).toEqual([]);
    expect(analyze('Received: by a; 23 Oct 2026 10:00:00 +0000').observedTransitMs).toBeNull();
    expect(analyze('Received: by b; invalid\nReceived: by a; 23 Oct 2026 10:00:00 +0000').observedTransitMs).toBeNull();
});
