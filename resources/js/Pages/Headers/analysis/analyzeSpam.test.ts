import { expect, it } from 'vitest';
import { analyzeSpam } from './analyzeSpam';
import { parseHeaders } from './parseHeaders';
it('preserves separate occurrences and contradictory provider verdicts without merging scores', () => {
    const document = parseHeaders('X-Spamd-Result: default: False [-2 / 15]; RULE(-2)[]\nX-Spam-Status: Yes, score=6 required=5 tests=OTHER\nX-Spam-Status: No, score=0 required=5\nX-Microsoft-Antispam: BCL:0;\nX-Other: untouched').document!;
    const result = analyzeSpam(document);
    expect(result.observations.map(o => o.family)).toEqual(['rspamd', 'spamassassin-compatible', 'spamassassin-compatible', 'microsoft']);
    expect(result.observations.map(o => o.sourceFieldIds)).toEqual([[0], [1], [2], [3]]);
    expect(result.observations.map(o => o.score)).toEqual([-2, 6, 0, null]);
    expect(result.warnings.map(w => w.code)).toContain('spam_conflict');
    expect(document.fields[4].value).toBe('untouched');
});
it('warns and preserves malformed recognized fragments', () => {
    const result = analyzeSpam(parseHeaders('X-Spamd-Result: broken "fragment').document!);
    expect(result.observations[0].unknownFragments).toEqual(['broken "fragment']);
    expect(result.warnings.map(w => w.code)).toContain('spam_partial');
});
