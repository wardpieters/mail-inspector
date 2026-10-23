import { expect, it } from 'vitest';
import { parseHeaders } from '../parseHeaders';
import { recognizeRspamd } from './rspamd';
const read = (name: string, value: string) => recognizeRspamd(parseHeaders(name + ': ' + value).document!.fields[0]);
it('extracts metric, signed scores and balanced symbol options', () => {
    expect(read('X-Spamd-Result', 'default: False [-2.50 / 15.00]; RULE_A(-1.50)[foo;bar]; RULE_B(-1.00)[]')).toMatchObject({
        family: 'rspamd', metric: 'default', verdict: 'False', score: -2.5, threshold: 15, sourceFieldIds: [0],
        rules: [{ name: 'RULE_A', score: -1.5, options: 'foo;bar' }, { name: 'RULE_B', score: -1, options: '' }],
    });
});
it.each(['X-Spamd-Bar', 'X-Rspamd-Server', 'X-Rspamd-Queue-Id'])('recognizes diagnostic %s without guessing a score', name => {
    expect(read(name, 'value')).toMatchObject({ family: 'rspamd', score: null, threshold: null });
});
it('extracts reported action/pre-result and preserves malformed fragments', () => {
    expect(read('x-rspamd-action', 'add header')?.action).toBe('add header');
    expect(read('X-Rspamd-Pre-Result', 'action=reject; module=dmarc; details')?.action).toBe('reject');
    expect(read('X-Spamd-Result', 'broken; UNKNOWN(value)')?.unknownFragments).toEqual(['broken', 'UNKNOWN(value)']);
    expect(read('X-Spamd-Result', 'default: False [0 / 15]; RULE(0)[[nested;thing]]')?.rules[0].options).toBe('[nested;thing]');
    expect(read('X-Other', 'a')).toBeNull();
});
