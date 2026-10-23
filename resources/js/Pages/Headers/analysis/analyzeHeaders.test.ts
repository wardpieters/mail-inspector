import { expect, it } from 'vitest';
import { analyzeHeaders } from './analyzeHeaders';
it('composes evidence and warns on duplicate singleton fields without dropping values', () => {
    const result = analyzeHeaders('Subject: Example\nSubject: Other\nFrom: a@example.org\nDate: today\nDate: tomorrow\nMessage-ID: one\nMessage-ID: two\n\nSECRET_BODY');
    expect(result.analysis?.summary.subject.map(f => f.displayValue)).toEqual(['Example', 'Other']);
    expect(result.analysis?.warnings.filter(w => w.code === 'duplicate_field')).toHaveLength(3);
    expect(result.analysis?.document.rawHeaders).not.toContain('SECRET_BODY');
    expect(result.analysis?.delivery.hops).toEqual([]);
    expect(result.analysis?.authentication.observations).toEqual([]);
});
it('returns a readable validation code rather than an analysis of unusable input', () => {
    expect(analyzeHeaders('')).toEqual({ analysis: null, error: { code: 'empty' } });
});
