import type { HeaderField, SpamObservation } from '../../../../types/headers';
import { scanHeaderTokens, stripHeaderComments } from '../splitHeaderTokens';
import { spamNumber, spamObservation } from './observation';

function statusProperties(input: string): { name: string; value: string; raw: string }[] {
    const source = stripHeaderComments(input);
    const markers: { start: number; valueStart: number; name: string }[] = [];
    let quoted = false;
    let escaped = false;
    let brackets = 0;
    for (let index = 0; index < source.length; index++) {
        const character = source[index];
        if (escaped) { escaped = false; continue; }
        if (character === '\\') { escaped = true; continue; }
        if (character === '"') { quoted = !quoted; continue; }
        if (quoted) { continue; }
        if (character === '[') { brackets++; continue; }
        if (character === ']') { brackets--; continue; }
        if (brackets || !/[a-z]/i.test(character) || (index > 0 && !/[\s,]/.test(source[index - 1]))) { continue; }
        const match = /^([a-z][\w-]*)\s*=\s*/i.exec(source.slice(index));
        if (match) {
            markers.push({ start: index, valueStart: index + match[0].length, name: match[1].toLowerCase() });
            index += match[0].length - 1;
        }
    }
    return markers.map((marker, index) => {
        const end = markers[index + 1]?.start ?? source.length;
        let value = source.slice(marker.valueStart, end).trim().replace(/,$/, '').trim();
        if (value.startsWith('"') && value.endsWith('"')) { value = value.slice(1, -1).replace(/\\(.)/g, '$1'); }
        return { name: marker.name, value, raw: source.slice(marker.start, end).trim() };
    });
}

export function recognizeSpamAssassin(field: HeaderField): SpamObservation | null {
    if (!['x-spam-status', 'x-spam-flag', 'x-spam-level', 'x-spam-score', 'x-spam-report', 'x-spam-checker-version'].includes(field.lookupName)) { return null; }
    const result = spamObservation(field, 'spamassassin-compatible');
    if (field.lookupName === 'x-spam-score') {
        result.score = spamNumber(field.value);
        if (result.score === null) { result.unknownFragments.push(field.value); }
    } else if (field.lookupName === 'x-spam-flag') {
        result.verdict = field.value;
    } else if (field.lookupName === 'x-spam-status') {
        const verdict = /^(Yes|No)(?=[\s,;]|$)/i.exec(field.value);
        result.verdict = verdict?.[1] ?? null;
        if (!scanHeaderTokens(field.value, ';').balanced) { result.unknownFragments.push(field.value); return result; }
        const values = statusProperties(field.value);
        if (!verdict || values.length === 0) { result.unknownFragments.push(field.value); }
        for (const { name, value, raw } of values) {
            result.values.push({ name, value, explanationKey: null });
            if (['score', 'hits'].includes(name)) { result.score = spamNumber(value); }
            else if (name === 'required') { result.threshold = spamNumber(value); }
            else if (name === 'tests') {
                result.rules.push(...value.split(',').map(test => ({ name: test.trim(), score: null, options: null })).filter(rule => rule.name));
            } else if (!['autolearn', 'version'].includes(name)) { result.unknownFragments.push(raw); }
            if (['score', 'hits', 'required'].includes(name) && spamNumber(value) === null) { result.unknownFragments.push(raw); }
        }
    }
    return result;
}
