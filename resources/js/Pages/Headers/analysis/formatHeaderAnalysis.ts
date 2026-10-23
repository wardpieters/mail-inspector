import type { HeaderAnalysis, HeaderIssue, HeaderText } from '../../../types/headers';

export const HEADER_LIMITATIONS = [
    'Authentication and filtering results are reported by the pasted headers; they have not been independently verified.',
    'Header content can be forged. No trusted reporting server is selected.',
    'Transit times cover only the observed Received headers and can be affected by clock differences.',
    'Provider scores are not comparable. Reported actions do not prove final delivery.',
];
export const SUMMARY_LABELS: Record<string, string> = { subject: 'Subject', from: 'From', to: 'To', cc: 'Cc', 'reply-to': 'Reply-To', date: 'Date', 'message-id': 'Message-ID', 'return-path': 'Return-Path' };
export const ERROR_LABELS: Record<string, string> = {
    empty: 'Paste message headers to start.',
    no_fields: 'No message header fields were found. Paste the original internet headers.',
    byte_limit: 'The input exceeds 1 MiB. Paste only the message headers.',
    line_limit: 'The input exceeds 10,000 lines.',
    field_limit: 'The input exceeds 2,000 header fields.',
};
const ISSUE_LABELS: Record<string, string> = {
    malformed_line: 'Line :line could not be parsed and was preserved as raw evidence.',
    encoded_word: 'An encoded word could not be decoded. The original value is preserved.',
    duplicate_field: 'The :name field appears more than once. All values are preserved.',
    negative_transit: 'Observed transit time is negative. Server clocks or header order may differ.',
    hop_date: 'Hop :hop has an unknown or unsupported timestamp.',
    negative_delay: 'Hop :hop has a negative delay. Server clocks or header order may differ.',
    auth_malformed: 'Some authentication evidence could not be interpreted. Inspect the original header.',
    auth_unknown: 'An authentication method or result is unfamiliar. Its original value is preserved.',
    auth_conflict: 'Reported :method results conflict. No reporting server is assumed to be trusted.',
    spam_partial: 'Some filtering details are unfamiliar or incomplete. Their original values are preserved.',
    spam_conflict: 'Spam-filter verdicts conflict. Results from different systems are kept separate.',
};
export function headerIssueText(issue: HeaderIssue, t: HeaderText): string {
    return t(ISSUE_LABELS[issue.code] ?? 'Some evidence could not be interpreted.', issue.params);
}
export function reportedResult(result: string | null, t: HeaderText): string {
    const labels: Record<string, string> = { pass: 'Reported pass', fail: 'Reported fail', softfail: 'Reported softfail', neutral: 'Reported neutral', none: 'Reported none', temperror: 'Reported temporary error', permerror: 'Reported permanent error', policy: 'Reported policy result' };
    return result === null ? t('No reported result') : Object.hasOwn(labels, result) ? t(labels[result]) : t('Reported result') + ': ' + result;
}
export function headerDuration(value: number | null, t: HeaderText): string {
    return value === null ? t('Unknown') : t(':seconds s', { seconds: Number((value / 1000).toFixed(3)) });
}
export function formatHeaderAnalysis(analysis: HeaderAnalysis, t: HeaderText): string {
    const output = [t('Message Header Analyzer'), '', t('Message summary')];
    for (const [name, fields] of Object.entries(analysis.summary)) {
        for (const field of fields) { output.push(t(SUMMARY_LABELS[name] ?? name) + ': ' + field.displayValue); }
    }
    output.push(t('Observed transit time') + ': ' + headerDuration(analysis.delivery.observedTransitMs, t), '', t('Delivery journey'));
    for (const hop of analysis.delivery.hops) {
        output.push(t('Hop') + ' ' + hop.hop + ': ' + (hop.from ?? t('Unknown')) + ' → ' + (hop.by ?? t('Unknown')) + '; ' + (hop.timestamp.iso ?? t('Unknown')) + '; ' + headerDuration(hop.delayMs, t));
    }
    output.push('', t('Authentication'));
    for (const observation of analysis.authentication.observations) {
        output.push((observation.reporter ?? t('Unspecified reporter')) + ': ' + observation.method + ' — ' + (observation.kind === 'result' ? reportedResult(observation.result, t) : t('Signature metadata only')) + '; ' + observation.raw);
    }
    output.push('', t('Filtering details'));
    for (const observation of analysis.filtering.observations) {
        output.push(observation.family + ': ' + observation.values.map(v => v.name + '=' + v.value).join('; '));
    }
    output.push('', t('Analysis notes'), ...analysis.warnings.map(issue => headerIssueText(issue, t)), '', t('Limitations'), ...HEADER_LIMITATIONS.map(value => t(value)), '', t('All headers'), analysis.document.rawHeaders);
    return output.join('\n');
}
export function serializeHeaderAnalysis(analysis: HeaderAnalysis): string {
    return JSON.stringify({ schemaVersion: 1, analysis, limitations: HEADER_LIMITATIONS }, null, 2);
}
