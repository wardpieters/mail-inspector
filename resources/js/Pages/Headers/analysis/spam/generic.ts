import type { HeaderField, SpamFamily, SpamObservation } from '../../../../types/headers';
import { spamObservation } from './observation';

export function recognizeGeneric(field: HeaderField): SpamObservation | null {
    const families: [string, SpamFamily][] = [['x-barracuda-', 'barracuda'], ['x-proofpoint-', 'proofpoint'], ['x-ironport-', 'ironport']];
    for (const [prefix, family] of families) {
        if (field.lookupName.startsWith(prefix)) { return spamObservation(field, family); }
    }
    if (/^x-(?:spam|spamd|rspamd)-/.test(field.lookupName) || field.lookupName.includes('antispam')) {
        return spamObservation(field, 'generic');
    }
    return null;
}
