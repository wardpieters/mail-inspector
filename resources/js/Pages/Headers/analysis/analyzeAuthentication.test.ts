import { expect, it } from 'vitest';
import { parseHeaders } from './parseHeaders';
import { analyzeAuthentication } from './analyzeAuthentication';
const analyze = (value: string) => analyzeAuthentication(parseHeaders(value).document!);
it('attributes results to their reporter without validating signature presence', () => {
    const result = analyze('Authentication-Results: mx.example; spf=pass smtp.mailfrom=example.org\nDKIM-Signature: v=1; d=example.org; s=mail; a=rsa-sha256');
    expect(result.observations).toEqual(expect.arrayContaining([
        expect.objectContaining({ fieldId: 0, reporter: 'mx.example', method: 'spf', result: 'pass', kind: 'result', properties: [{ name: 'smtp.mailfrom', value: 'example.org' }] }),
        expect.objectContaining({ fieldId: 1, kind: 'signature', method: 'dkim', result: null }),
    ]));
});
it('preserves conflicting and repeated observations plus quoted property values', () => {
    const result = analyze('Authentication-Results: first.example; dkim=pass header.d=example.org; dkim=fail reason="bad; signature"\nAuthentication-Results: second.example; spf=temperror; dmarc=permerror; odd=custom thing=yes');
    expect(result.observations.map(o => o.result)).toEqual(['pass', 'fail', 'temperror', 'permerror', 'custom']);
    expect(result.observations[1].properties).toContainEqual({ name: 'reason', value: 'bad; signature' });
    expect(result.observations[2].reporter).toBe('second.example');
    expect(result.warnings.map(w => w.code)).toContain('auth_conflict');
    expect(result.warnings.map(w => w.code)).toContain('auth_unknown');
});
it('keeps ARC instance and seal metadata distinct from local validation', () => {
    const result = analyze('ARC-Authentication-Results: i=2; mx.example; dmarc=pass header.from=example.org\nARC-Seal: i=2; cv=pass; d=example.org; s=mail');
    expect(result.observations[0]).toMatchObject({ instance: 2, reporter: 'mx.example', method: 'dmarc', result: 'pass' });
    expect(result.observations[1]).toMatchObject({ kind: 'seal', method: 'arc', result: null, instance: 2 });
    expect(result.observations[1].properties).toContainEqual({ name: 'cv', value: 'pass' });
});
it('distinguishes explicit none, absent evidence and Received-SPF', () => {
    expect(analyze('Authentication-Results: mx.example; none').observations[0]).toMatchObject({ method: 'none', result: 'none' });
    expect(analyze('Subject: hi').observations).toEqual([]);
    expect(analyze('Received-SPF: softfail (sender not allowed) client-ip=192.0.2.1; envelope-from=user@example.org').observations[0]).toMatchObject({ method: 'spf', result: 'softfail', reporter: null });
});
it('retains malformed source and emits an interpretation warning', () => {
    const result = analyze('Authentication-Results: mx.example; spf=pass reason="broken; dkim=pass');
    expect(result.warnings.map(w => w.code)).toContain('auth_malformed');
    expect(result.observations[0].raw).toContain('reason="broken');
    expect(result.observations[0].result).toBeNull();
});

it('bounds work on a large malformed signature property', () => {
    const started = performance.now();
    const result = analyze('DKIM-Signature: ' + 'a'.repeat(100000));
    expect(result.observations[0].properties).toEqual([]);
    expect(performance.now() - started).toBeLessThan(2000);
});
