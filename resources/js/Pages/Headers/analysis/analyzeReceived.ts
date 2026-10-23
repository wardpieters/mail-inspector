import type { HeaderDocument, HopAnalysis, ReceivedHop } from '../../../types/headers';
import { parseHeaderTimestamp } from './parseHeaderTimestamp';

function maskComments(value: string): string {
    let depth = 0;
    let quoted = false;
    let escaped = false;
    let masked = '';
    for (const character of value) {
        if (escaped) {
            masked += ' ';
            escaped = false;
            continue;
        }
        if (character === '\\') {
            escaped = true;
            masked += ' ';
            continue;
        }
        if (character === '"' && depth === 0) { quoted = !quoted; }
        if (!quoted && character === '(') { depth++; }
        masked += depth || quoted || character === '"' || character === ')' ? ' ' : character;
        if (!quoted && character === ')' && depth > 0) { depth--; }
    }
    return masked;
}

export function analyzeReceived(document: HeaderDocument): HopAnalysis {
    const result: HopAnalysis = { hops: [], observedTransitMs: null, warnings: [] };
    const fields = document.fields.filter(field => field.lookupName === 'received').reverse();
    for (const field of fields) {
        const masked = maskComments(field.value);
        const dateSeparator = masked.lastIndexOf(';');
        const transport = dateSeparator < 0 ? masked : masked.slice(0, dateSeparator);
        const timestamp = parseHeaderTimestamp(dateSeparator < 0 ? '' : field.value.slice(dateSeparator + 1).trim());
        const hop: ReceivedHop = { fieldId: field.id, hop: result.hops.length + 1, from: null, by: null, protocol: null, id: null, for: null, ips: [], timestamp, delayMs: null, delayPercent: null };
        const markers = [...transport.matchAll(/\b(from|by|with|id|for)\s+/gi)];
        for (let i = 0; i < markers.length; i++) {
            const marker = markers[i];
            const key = marker[1].toLowerCase();
            const value = field.value.slice(marker.index + marker[0].length, markers[i + 1]?.index ?? transport.length).trim();
            const property = key === 'with' ? 'protocol' : key as 'from' | 'by' | 'id' | 'for';
            if (hop[property] === null) { hop[property] = value || null; }
        }
        for (const ip of field.value.matchAll(/\b(?:\d{1,3}\.){3}\d{1,3}\b/g)) {
            if (ip[0].split('.').every(part => Number(part) <= 255)) { hop.ips.push(ip[0]); }
        }
        for (const ip of field.value.matchAll(/\[(?:IPv6:)?([a-f0-9:]+)\]/gi)) {
            if (ip[1].includes(':')) { hop.ips.push(ip[1]); }
        }
        hop.ips = [...new Set(hop.ips)];
        if (timestamp.epochMs === null) {
            result.warnings.push({ code: 'hop_date', fieldIds: [field.id], params: { hop: hop.hop } });
        }
        const previous = result.hops.at(-1);
        if (previous && previous.timestamp.epochMs !== null && timestamp.epochMs !== null) {
            hop.delayMs = timestamp.epochMs - previous.timestamp.epochMs;
            if (hop.delayMs < 0) { result.warnings.push({ code: 'negative_delay', fieldIds: [previous.fieldId, field.id], params: { hop: hop.hop } }); }
        }
        result.hops.push(hop);
    }
    const positiveTotal = result.hops.reduce((sum, hop) => sum + Math.max(0, hop.delayMs ?? 0), 0);
    for (const hop of result.hops) {
        if (positiveTotal > 0 && (hop.delayMs ?? 0) > 0) { hop.delayPercent = hop.delayMs! / positiveTotal * 100; }
    }
    const first = result.hops[0]?.timestamp.epochMs;
    const last = result.hops.at(-1)?.timestamp.epochMs;
    if (result.hops.length > 1 && first != null && last != null) { result.observedTransitMs = last - first; }
    return result;
}
