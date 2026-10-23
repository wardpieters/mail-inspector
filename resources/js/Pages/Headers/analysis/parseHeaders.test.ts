import { expect, it } from 'vitest';
import { parseHeaders } from './parseHeaders';

it.each(['\n', '\r\n', '\r'])('preserves order, duplicates, folds and raw evidence with %j', newline => {
    const raw = ['Subject: Hello', '\tthere', 'X-Test: a', 'x-test: b', '', 'X-Body: SECRET'].join(newline);
    const result = parseHeaders(raw).document!;
    expect(result.fields.map(f => f.lookupName)).toEqual(['subject', 'x-test', 'x-test']);
    expect(result.fields[0]).toMatchObject({ id: 0, name: 'Subject', value: 'Hello there', startLine: 1, endLine: 2, raw: 'Subject: Hello' + newline + '\tthere' });
    expect(result.rawHeaders).toBe(raw.split(newline + newline)[0]);
    expect(result.rawHeaders).not.toContain('SECRET');
});
it('preserves malformed lines separately without swallowing subsequent fields', () => {
    const result = parseHeaders('\n orphan\nSubject: hi\nbad line\nX!Test: <img src=x>').document!;
    expect(result.fields.map(f => f.name)).toEqual(['Subject', 'X!Test']);
    expect(result.malformedLines).toEqual([{ line: 2, raw: ' orphan' }, { line: 4, raw: 'bad line' }]);
    expect(result.warnings).toHaveLength(2);
});
it.each([['  ', 'empty'], ['not headers', 'no_fields']])('rejects unusable input %j', (input, code) => {
    expect(parseHeaders(input).error?.code).toBe(code);
});
it('enforces UTF-8 byte limits on the whole pasted message', () => {
    expect(parseHeaders('X: ' + 'a'.repeat(1048573)).error).toBeNull();
    expect(parseHeaders('X: ' + 'a'.repeat(1048574)).error?.code).toBe('byte_limit');
    expect(parseHeaders('X: ' + 'é'.repeat(524286) + 'a').error).toBeNull();
    expect(parseHeaders('X: ' + 'é'.repeat(524287)).error?.code).toBe('byte_limit');
    expect(parseHeaders('X: a\n\n' + 'x'.repeat(1048576)).error?.code).toBe('byte_limit');
});
it('enforces physical-line and parsed-field boundaries', () => {
    expect(parseHeaders('X: a' + '\n a'.repeat(9999)).error).toBeNull();
    expect(parseHeaders('X: a' + '\n a'.repeat(10000)).error?.code).toBe('line_limit');
    expect(parseHeaders(Array(2000).fill('X: a').join('\n')).document?.fields).toHaveLength(2000);
    expect(parseHeaders(Array(2001).fill('X: a').join('\n')).error?.code).toBe('field_limit');
});
it('decodes display words separately from structured protocol values', () => {
    const result = parseHeaders('Subject: =?UTF-8?B?SGVsbG8=?=\nAuthentication-Results: =?UTF-8?B?SGVsbG8=?=').document!;
    expect(result.fields[0]).toMatchObject({ value: '=?UTF-8?B?SGVsbG8=?=', displayValue: 'Hello' });
    expect(result.fields[1].displayValue).toBe('=?UTF-8?B?SGVsbG8=?=');
});
