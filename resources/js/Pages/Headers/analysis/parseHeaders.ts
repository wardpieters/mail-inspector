import type { HeaderDocument, HeaderField, ParseResult } from '../../../types/headers';
import { decodeHeaderValue } from './decodeHeaderValue';

export const MAX_HEADER_BYTES = 1048576;
export const MAX_HEADER_LINES = 10000;
export const MAX_HEADER_FIELDS = 2000;

export function parseHeaders(input: string): ParseResult {
    if (!input.trim()) { return { document: null, error: { code: 'empty' } }; }
    if (input.length > MAX_HEADER_BYTES || new TextEncoder().encode(input).length > MAX_HEADER_BYTES) {
        return { document: null, error: { code: 'byte_limit' } };
    }
    const lines = input.split(/\r\n|\r|\n/);
    if (lines.length > MAX_HEADER_LINES) { return { document: null, error: { code: 'line_limit' } }; }
    const document: HeaderDocument = { rawHeaders: input, fields: [], malformedLines: [], warnings: [] };
    const lineStarts: number[] = [];
    let offset = 0;
    for (const match of input.matchAll(/([^\r\n]*)(\r\n|\r|\n|$)/g)) {
        lineStarts.push(offset);
        offset += match[0].length;
        if (!match[2]) { break; }
    }
    let previous: HeaderField | null = null;
    let started = false;
    for (let index = 0; index < lines.length; index++) {
        const line = lines[index];
        if (line === '') {
            if (!started) { continue; }
            const preceding = input.slice(0, lineStarts[index]);
            document.rawHeaders = preceding.replace(/(?:\r\n|\r|\n)$/, '');
            break;
        }
        started = true;
        if (/^[ \t]/.test(line) && previous) {
            previous.value += ' ' + line.trim();
            previous.endLine = index + 1;
            previous.raw = input.slice(lineStarts[previous.startLine - 1], lineStarts[index] + line.length);
            continue;
        }
        const match = /^([!-9;-~]+):[ \t]*(.*)$/.exec(line);
        if (match) {
            if (document.fields.length >= MAX_HEADER_FIELDS) { return { document: null, error: { code: 'field_limit' } }; }
            previous = { id: document.fields.length, name: match[1], lookupName: match[1].toLowerCase(), raw: line, value: match[2], displayValue: match[2], startLine: index + 1, endLine: index + 1 };
            document.fields.push(previous);
        } else {
            document.malformedLines.push({ line: index + 1, raw: line });
            document.warnings.push({ code: 'malformed_line', fieldIds: [], params: { line: index + 1 } });
            previous = null;
        }
    }
    if (!document.fields.length) { return { document: null, error: { code: 'no_fields' } }; }
    for (const field of document.fields) {
        field.displayValue = field.value;
        if (['subject', 'from', 'to', 'cc', 'reply-to'].includes(field.lookupName)) {
            const decoded = decodeHeaderValue(field.value);
            field.displayValue = decoded.value;
            document.warnings.push(...decoded.warnings.map(code => ({ code, fieldIds: [field.id], params: {} })));
        }
    }
    return { document, error: null };
}
