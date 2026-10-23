import type { HeaderField, SpamObservation } from '../../../../types/headers';
import { scanHeaderTokens } from '../splitHeaderTokens';
import { spamObservation } from './observation';

const explanations: Record<string, string> = {
    SCL: 'Reported spam confidence level; this alone does not establish delivery disposition.',
    BCL: 'Reported bulk complaint level.',
    CAT: 'Reported filtering category.',
    SFV: 'Reported filtering verdict code.',
    CIP: 'Reported connecting IP address.',
    H: 'Reported HELO or EHLO name.',
    PTR: 'Reported reverse DNS name.',
};

export function recognizeMicrosoft(field: HeaderField): SpamObservation | null {
    if (!['x-forefront-antispam-report', 'x-microsoft-antispam', 'x-ms-exchange-organization-scl'].includes(field.lookupName)) { return null; }
    const result = spamObservation(field, 'microsoft');
    const parts = field.lookupName === 'x-ms-exchange-organization-scl' ? ['SCL:' + field.value] : scanHeaderTokens(field.value, ';').parts;
    for (const part of parts.filter(Boolean)) {
        const index = part.indexOf(':');
        if (index < 1) { result.unknownFragments.push(part); continue; }
        const name = part.slice(0, index).toUpperCase();
        const value = part.slice(index + 1);
        result.values.push({ name, value, explanationKey: Object.hasOwn(explanations, name) ? explanations[name] : null });
        if (!Object.hasOwn(explanations, name)) { result.unknownFragments.push(part); }
    }
    return result;
}
