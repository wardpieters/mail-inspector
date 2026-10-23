import type { HeaderField, SpamObservation } from '../../../../types/headers';
import { scanHeaderTokens } from '../splitHeaderTokens';
import { spamNumber, spamObservation } from './observation';

export function recognizeRspamd(field: HeaderField): SpamObservation | null {
    if (!['x-spamd-result', 'x-spamd-bar', 'x-rspamd-action', 'x-rspamd-pre-result', 'x-rspamd-server', 'x-rspamd-queue-id'].includes(field.lookupName)) { return null; }
    const result = spamObservation(field, 'rspamd');
    if (field.lookupName === 'x-rspamd-action') { result.action = field.value; return result; }
    if (field.lookupName === 'x-rspamd-pre-result') {
        for (const part of scanHeaderTokens(field.value, ';').parts.filter(Boolean)) {
            const match = /^([\w-]+)=(.*)$/.exec(part);
            if (match) {
                result.values.push({ name: match[1], value: match[2], explanationKey: null });
                if (match[1].toLowerCase() === 'action') { result.action = match[2]; }
            } else { result.unknownFragments.push(part); }
        }
        return result;
    }
    if (field.lookupName !== 'x-spamd-result') { return result; }
    const tokens = scanHeaderTokens(field.value, ';');
    if (!tokens.balanced) { result.unknownFragments.push(field.value); return result; }
    const [metric = '', ...symbols] = tokens.parts;
    const match = /^([^:\s]+):\s*(True|False)\s*\[\s*([+-]?[\d.]+)\s*\/\s*([+-]?[\d.]+)\s*\]$/i.exec(metric);
    if (match && spamNumber(match[3]) !== null && spamNumber(match[4]) !== null) {
        result.metric = match[1];
        result.verdict = match[2];
        result.score = spamNumber(match[3]);
        result.threshold = spamNumber(match[4]);
    } else { result.unknownFragments.push(metric); }
    for (const symbol of symbols.filter(Boolean)) {
        const match = /^([\w-]+)\(([+-]?[\d.]+)\)(?:\[([\s\S]*)\])?$/.exec(symbol);
        if (match && spamNumber(match[2]) !== null) {
            result.rules.push({ name: match[1], score: spamNumber(match[2]), options: match[3] ?? null });
        } else { result.unknownFragments.push(symbol); }
    }
    return result;
}
