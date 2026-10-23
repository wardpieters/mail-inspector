import { expect, it } from 'vitest';
import { decodeHeaderValue } from './decodeHeaderValue';
it.each([
    ['=?UTF-8?B?SGVsbG8=?= =?UTF-8?Q?_world?=', 'Hello world'],
    ['=?UTF-8?Q?caf=C3=A9?=', 'café'],
    ['=?windows-1252?Q?caf=E9?=', 'café'],
    ['plain <script>text</script>', 'plain <script>text</script>'],
])('decodes supported display encoding %s', (input, value) => {
    expect(decodeHeaderValue(input)).toEqual({ value, warnings: [] });
});
it.each(['=?UTF-8?B?!!!?=', '=?UTF-8?Q?bad=XX?=', '=?unknown-charset?Q?hi?=', '=?UTF-8?B?/w==?=', '=?UTF-8?Q?unfinished'])('preserves invalid words and warns: %s', input => {
    const result = decodeHeaderValue(input);
    expect(result.value).toBe(input);
    expect(result.warnings.length).toBeGreaterThan(0);
});
