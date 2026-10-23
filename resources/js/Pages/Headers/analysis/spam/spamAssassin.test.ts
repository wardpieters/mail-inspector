import { expect, it } from 'vitest';
import { parseHeaders } from '../parseHeaders';
import { recognizeSpamAssassin } from './spamAssassin';
const read = (name: string, value: string) => recognizeSpamAssassin(parseHeaders(name + ': ' + value).document!.fields[0]);
it('interprets compatible status without asserting the generating product', () => {
    const result = read('X-Spam-Status', 'Yes, score=6.2 required=5.0 tests=RULE_A,RULE_B autolearn=no version=4.0.0')!;
    expect(result).toMatchObject({ family: 'spamassassin-compatible', verdict: 'Yes', score: 6.2, threshold: 5 });
    expect(result.rules.map(r => r.name)).toEqual(['RULE_A', 'RULE_B']);
    expect(result.values).toContainEqual({ name: 'autolearn', value: 'no', explanationKey: null });
});
it('handles legacy hits, zero/negative scores, flags and invalid scores', () => {
    expect(read('X-Spam-Status', 'No, hits=-1.2 required=5')?.score).toBe(-1.2);
    expect(read('X-Spam-Score', '0')?.score).toBe(0);
    expect(read('X-Spam-Flag', 'YES')?.verdict).toBe('YES');
    expect(read('X-Spam-Score', 'NaN')?.score).toBeNull();
    expect(read('X-Spam-Score', '1abc')?.unknownFragments).toEqual(['1abc']);
});
it.each(['X-Spam-Level', 'X-Spam-Report', 'X-Spam-Checker-Version'])('retains raw %s without inferred numerical meaning', name => {
    const result = read(name, 'raw text')!;
    expect(result.score).toBeNull();
    expect(result.values[0].value).toBe('raw text');
});

it('bounds work on status fields with large trailing whitespace', () => {
    const started = performance.now();
    expect(read('X-Spam-Status', 'No, score=1' + ' '.repeat(100000))?.score).toBe(1);
    expect(performance.now() - started).toBeLessThan(2000);
});

it('does not promote quoted or commented properties into top-level scores', () => {
    const result = read('X-Spam-Status', 'No, score=1 required=5 reason="old score=99 required=100" (score=88) tests=OK')!;
    expect(result.score).toBe(1);
    expect(result.threshold).toBe(5);
    expect(result.rules.map(r => r.name)).toEqual(['OK']);
    expect(result.values).toContainEqual({ name: 'reason', value: 'old score=99 required=100', explanationKey: null });
});
