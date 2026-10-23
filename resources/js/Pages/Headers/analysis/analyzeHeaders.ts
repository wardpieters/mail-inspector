import type { AnalysisResult, HeaderField, HeaderIssue } from '../../../types/headers';
import { parseHeaders } from './parseHeaders';
import { analyzeReceived } from './analyzeReceived';
import { analyzeAuthentication } from './analyzeAuthentication';
import { analyzeSpam } from './analyzeSpam';

export function analyzeHeaders(input: string): AnalysisResult {
    const parsed = parseHeaders(input);
    if (parsed.error) { return { analysis: null, error: parsed.error }; }
    const document = parsed.document;
    const summary: Record<string, HeaderField[]> = {};
    const warnings: HeaderIssue[] = [...document.warnings];
    for (const name of ['subject', 'from', 'to', 'cc', 'reply-to', 'date', 'message-id', 'return-path']) {
        summary[name] = document.fields.filter(f => f.lookupName === name);
        if (summary[name].length > 1) {
            warnings.push({ code: 'duplicate_field', fieldIds: summary[name].map(f => f.id), params: { name: summary[name][0].name } });
        }
    }
    const delivery = analyzeReceived(document);
    const authentication = analyzeAuthentication(document);
    const filtering = analyzeSpam(document);
    warnings.push(...delivery.warnings, ...authentication.warnings, ...filtering.warnings);
    return { analysis: { document, summary, delivery, authentication, filtering, warnings }, error: null };
}
