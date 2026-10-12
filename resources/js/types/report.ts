export type ScanStatus = 'queued' | 'running' | 'completed' | 'partial' | 'failed';
export type Outcome = 'pass' | 'warning' | 'fail' | 'info' | 'skipped' | 'inconclusive' | 'not_applicable';
export interface Finding { key: string; scope: string; outcome: Outcome; severity: string; title: string; explanation: string; recommendation: string | null; evidence: Record<string, unknown>; duration_ms: number; }
export interface Endpoint { mx: string; preference: number; ip: string; family: 4 | 6; status: string; discovery_secure: boolean; findings: Finding[]; }
export interface Coverage { planned: number; completed: number; pending: number; failed: number; omitted: number; incomplete: boolean; ipv4: number; ipv6: number; not_run: number; }
export interface Report { scan: { public_id: string; domain: string; selectors: string[]; status: ScanStatus; created_at: string; started_at: string | null; finished_at: string | null; deadline: string; expires_at: string; scanner_version: string; error: string | null; null_mx: boolean; }; assessment: { overall: string; pending: boolean; dimensions: Record<string, {label: string; pending: boolean; outcomes: Record<string, number>}>; coverage: Coverage; }; domain_findings: Finding[]; endpoints: Endpoint[]; capabilities: Record<string, unknown>; export_url: string; home_url: string; limitations: string[]; }
export const checkLabels: Record<string,string> = { dnssec: 'DNSSEC', caa: 'CAA', mx: 'MX', answer: 'Answer', connect: 'Connect', helo: 'HELO', tls: 'TLS', secure: 'Secure', cert: 'Cert', from: 'From', mtasts: 'MTA-STS', mtasts_duplicates: 'MTA-STS duplicates', dane: 'DANE', fcrdns: 'FCRDNS', open_relay: 'Open relay', spf: 'SPF', dkim: 'DKIM', dmarc: 'DMARC', authenticated_transport: 'Transport authentication', smtp_transcript: 'Transcript', tlsrpt: 'TLSRPT', dmarc_reporting: 'DMARC reporting', dmarc_legacy: 'Legacy DMARC metadata', smtp_dialogue: 'SMTP dialogue', coverage: 'Address coverage', mx_no_address: 'MX address lookup', mx_alias: 'MX alias', mx_invalid_null: 'Invalid null MX', mx_target_blocked: 'MX', transport_policies: 'Transport policies', endpoint_budget: 'Endpoint budget', worker_failure: 'Worker failure', deadline: 'Deadline' };

export function checkLabel(key: string): string {
    if (key.startsWith('mx_address_')) return 'MX address lookup';
    if (key.startsWith('mx_alias_')) return 'MX alias';
    return checkLabels[key] ?? 'Unknown check';
}
