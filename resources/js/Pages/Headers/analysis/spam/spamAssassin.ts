import type { HeaderField, SpamObservation } from '../../../../types/headers';
import { spamNumber, spamObservation } from './observation';

export function recognizeSpamAssassin(field: HeaderField): SpamObservation | null {
    if (!['x-spam-status', 'x-spam-flag', 'x-spam-level', 'x-spam-score', 'x-spam-report', 'x-spam-checker-version'].includes(field.lookupName)) { return null; }
    const result = spamObservation(field, 'spamassassin-compatible');
    if (field.lookupName === 'x-spam-score') {
        result.score = spamNumber(field.value);
        if (result.score === null) { result.unknownFragments.push(field.value); }
    } else if (field.lookupName === 'x-spam-flag') {
        result.verdict = field.value;
    } else if (field.lookupName === 'x-spam-status') {
        const verdict = /^(Yes|No)\b/i.exec(field.value);
        result.verdict = verdict?.[1] ?? null;
        const values = [...field.value.matchAll(/(?:^|[\s,])([a-z][\w-]*)=([\s\S]*?)(?=[\s,][a-z][\w-]*=|$)/gi)];
        if (!verdict || values.length === 0) { result.unknownFragments.push(field.value); }
        for (const match of values) {
            const name = match[1].toLowerCase();
            const value = match[2].trim();
            result.values.push({ name, value, explanationKey: null });
            if (['score', 'hits'].includes(name)) { result.score = spamNumber(value); }
            else if (name === 'required') { result.threshold = spamNumber(value); }
            else if (name === 'tests') {
                result.rules.push(...value.split(',').map(test => ({ name: test.trim(), score: null, options: null })).filter(rule => rule.name));
            } else if (!['autolearn', 'version'].includes(name)) { result.unknownFragments.push(match[0].trim()); }
            if (['score', 'hits', 'required'].includes(name) && spamNumber(value) === null) { result.unknownFragments.push(match[0].trim()); }
        }
    }
    return result;
}
