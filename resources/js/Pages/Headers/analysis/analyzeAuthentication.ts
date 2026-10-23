import type { AuthAnalysis, AuthObservation, HeaderDocument } from '../../../types/headers';
import { scanHeaderTokens, stripHeaderComments } from './splitHeaderTokens';

function properties(value: string): { name: string; value: string }[] {
    const clean = stripHeaderComments(value);
    return [...clean.matchAll(/(?:^|[\s;])([a-z][\w.-]*)\s*=\s*("(?:[^"\\]|\\.)*"|[^\s;()]+)/gi)].map(match => ({
        name: match[1].toLowerCase(),
        value: match[2].startsWith('"') ? match[2].slice(1, -1).replace(/\\(.)/g, '$1') : match[2],
    }));
}

export function analyzeAuthentication(document: HeaderDocument): AuthAnalysis {
    const analysis: AuthAnalysis = { observations: [], warnings: [] };
    const names = ['authentication-results', 'arc-authentication-results', 'received-spf', 'dkim-signature', 'arc-seal'];
    for (const field of document.fields) {
        if (!names.includes(field.lookupName)) { continue; }
        const tokens = scanHeaderTokens(field.value, ';');
        const base: AuthObservation = { fieldId: field.id, reporter: null, instance: null, kind: 'result', method: 'unknown', result: null, properties: [], raw: field.value };
        if (!tokens.balanced) {
            analysis.warnings.push({ code: 'auth_malformed', fieldIds: [field.id], params: {} });
            analysis.observations.push(base);
            continue;
        }
        const parts = [...tokens.parts];
        if (field.lookupName === 'dkim-signature' || field.lookupName === 'arc-seal') {
            const props = properties(field.value);
            const instance = props.find(p => p.name === 'i')?.value;
            analysis.observations.push({ ...base, kind: field.lookupName === 'arc-seal' ? 'seal' : 'signature', method: field.lookupName === 'arc-seal' ? 'arc' : 'dkim', properties: props, instance: instance && /^\d+$/.test(instance) ? Number(instance) : null });
            continue;
        }
        if (field.lookupName === 'received-spf') {
            const match = /^([a-z]+)/i.exec(field.value);
            analysis.observations.push({ ...base, method: 'spf', result: match?.[1].toLowerCase() ?? null, properties: properties(field.value) });
            continue;
        }
        if (field.lookupName === 'arc-authentication-results') {
            const match = /^i\s*=\s*(\d+)$/i.exec(parts.shift() ?? '');
            base.instance = match ? Number(match[1]) : null;
            if (!match) { analysis.warnings.push({ code: 'auth_malformed', fieldIds: [field.id], params: {} }); }
        }
        base.reporter = stripHeaderComments(parts.shift() ?? '').trim() || null;
        if (!parts.some(Boolean)) {
            analysis.warnings.push({ code: 'auth_malformed', fieldIds: [field.id], params: {} });
            analysis.observations.push(base);
        }
        for (const part of parts.filter(Boolean)) {
            const clean = stripHeaderComments(part).trim();
            if (clean.toLowerCase() === 'none') { analysis.observations.push({ ...base, method: 'none', result: 'none', raw: part }); continue; }
            const match = /^([\w-]+)(?:\/\d+)?\s*=\s*([\w-]+)/.exec(clean);
            if (!match) {
                analysis.warnings.push({ code: 'auth_malformed', fieldIds: [field.id], params: {} });
                analysis.observations.push({ ...base, raw: part });
                continue;
            }
            const method = match[1].toLowerCase();
            const result = match[2].toLowerCase();
            analysis.observations.push({ ...base, method, result, properties: properties(clean.slice(match[0].length)), raw: part });
            if (!['spf', 'dkim', 'dmarc', 'arc', 'iprev', 'auth', 'compauth'].includes(method) || !['pass', 'fail', 'softfail', 'neutral', 'none', 'temperror', 'permerror', 'policy'].includes(result)) {
                analysis.warnings.push({ code: 'auth_unknown', fieldIds: [field.id], params: {} });
            }
        }
    }
    for (const method of new Set(analysis.observations.map(o => o.method))) {
        const observations = analysis.observations.filter(o => o.kind === 'result' && o.method === method && o.result);
        if (observations.some(o => o.result === 'pass') && observations.some(o => ['fail', 'softfail'].includes(o.result!))) {
            analysis.warnings.push({ code: 'auth_conflict', fieldIds: [...new Set(observations.map(o => o.fieldId))], params: { method } });
        }
    }
    return analysis;
}
