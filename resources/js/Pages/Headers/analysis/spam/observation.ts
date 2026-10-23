import type { HeaderField, SpamFamily, SpamObservation } from '../../../../types/headers';
export function spamObservation(field: HeaderField, family: SpamFamily): SpamObservation {
    return { family, sourceFieldIds: [field.id], values: [{ name: field.name, value: field.value, explanationKey: null }], score: null, threshold: null, action: null, verdict: null, metric: null, rules: [], unknownFragments: [] };
}
export function spamNumber(value: string): number | null {
    if (!/^[+-]?(?:\d+(?:\.\d+)?|\.\d+)$/.test(value.trim())) { return null; }
    const number = Number(value);
    return Number.isFinite(number) ? number : null;
}
