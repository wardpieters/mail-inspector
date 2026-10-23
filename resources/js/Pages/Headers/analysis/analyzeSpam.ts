import type { HeaderDocument, SpamAnalysis } from '../../../types/headers';
import { recognizeRspamd } from './spam/rspamd';
import { recognizeSpamAssassin } from './spam/spamAssassin';
import { recognizeMicrosoft } from './spam/microsoft';
import { recognizeGeneric } from './spam/generic';

export function analyzeSpam(document: HeaderDocument): SpamAnalysis {
    const result: SpamAnalysis = { observations: [], warnings: [] };
    for (const field of document.fields) {
        for (const recognize of [recognizeRspamd, recognizeSpamAssassin, recognizeMicrosoft, recognizeGeneric]) {
            const observation = recognize(field);
            if (!observation) { continue; }
            result.observations.push(observation);
            if (observation.unknownFragments.length) { result.warnings.push({ code: 'spam_partial', fieldIds: [field.id], params: {} }); }
            break;
        }
    }
    const explicit = result.observations.filter(o => o.verdict && /^(true|false|yes|no)$/i.test(o.verdict));
    if (explicit.some(o => /^(true|yes)$/i.test(o.verdict!)) && explicit.some(o => /^(false|no)$/i.test(o.verdict!))) {
        result.warnings.push({ code: 'spam_conflict', fieldIds: explicit.flatMap(o => o.sourceFieldIds), params: {} });
    }
    return result;
}
