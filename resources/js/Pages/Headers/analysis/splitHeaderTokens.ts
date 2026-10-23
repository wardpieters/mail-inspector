export function scanHeaderTokens(value: string, delimiter: ';' | ','): { parts: string[]; balanced: boolean } {
    const parts: string[] = [];
    const stack: string[] = [];
    let commentDepth = 0;
    let quoted = false;
    let escaped = false;
    let start = 0;
    let valid = true;
    for (let i = 0; i < value.length; i++) {
        const c = value[i];
        if (escaped) { escaped = false; continue; }
        if (c === '\\') { escaped = true; continue; }
        if (c === '"' && commentDepth === 0) { quoted = !quoted; continue; }
        if (quoted) { continue; }
        if (c === '(' || (c === '[' && commentDepth === 0)) { stack.push(c); if (c === '(') { commentDepth++; } }
        else if (c === ')' || (c === ']' && commentDepth === 0)) {
            if (stack.pop() !== (c === ')' ? '(' : '[')) { valid = false; }
            if (c === ')' && commentDepth > 0) { commentDepth--; }
        } else if (c === delimiter && stack.length === 0) {
            parts.push(value.slice(start, i).trim());
            start = i + 1;
        }
    }
    const balanced = valid && !quoted && !escaped && stack.length === 0;
    parts.push(value.slice(start).trim());
    return { parts: balanced ? parts : [value.trim()], balanced };
}

export function splitHeaderTokens(value: string, delimiter: ';' | ','): string[] {
    return scanHeaderTokens(value, delimiter).parts;
}

export function stripHeaderComments(value: string): string {
    let depth = 0;
    let quoted = false;
    let escaped = false;
    let result = '';
    for (const c of value) {
        if (escaped) { if (!depth) { result += c; } escaped = false; continue; }
        if (c === '\\') { escaped = true; if (!depth) { result += c; } continue; }
        if (c === '"' && !depth) { quoted = !quoted; }
        if (!quoted && c === '(') { depth++; result += ' '; continue; }
        if (!quoted && c === ')' && depth) { depth--; continue; }
        if (!depth) { result += c; }
    }
    return result;
}
