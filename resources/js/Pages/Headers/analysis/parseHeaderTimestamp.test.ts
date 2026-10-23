import { expect, it } from 'vitest';
import { parseHeaderTimestamp } from './parseHeaderTimestamp';
it.each([
    ['Fri, 23 Oct 2026 12:00:00 +0200', '2026-10-23T10:00:00.000Z'],
    ['23 Oct 2026 05:00:00 -0500', '2026-10-23T10:00:00.000Z'],
    ['23 Oct 2026 10:00:00 GMT', '2026-10-23T10:00:00.000Z'],
    ['23 Oct 2026 10:00:00 +0000 (UTC)', '2026-10-23T10:00:00.000Z'],
    ['31 Feb 2026 12:00:00 +0000', null],
    ['23 Oct 2026 12:00:00 CET', null],
    ['23 Oct 2026 25:00:00 +0000', null],
    ['23 Oct 2026 12:00:00 +2460', null],
    ['invalid', null],
])('interprets an explicit timezone without browser date guessing: %s', (input, expected) => {
    expect(parseHeaderTimestamp(input).iso).toBe(expected);
    expect(parseHeaderTimestamp(input).raw).toBe(input);
});
