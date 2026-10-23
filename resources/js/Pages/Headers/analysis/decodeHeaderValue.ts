export function decodeHeaderValue(value: string): { value: string; warnings: string[] } {
    const words = /=\?([^?\s]+)\?([bq])\?([^?]*)\?=/gi;
    let output = '';
    let cursor = 0;
    let previousDecoded = false;
    const warnings: string[] = [];
    for (const match of value.matchAll(words)) {
        let decoded: string | null = null;
        try {
            let bytes: Uint8Array;
            if (match[2].toLowerCase() === 'b') {
                if (!/^(?:[A-Za-z0-9+/]{4})*(?:[A-Za-z0-9+/]{2}==|[A-Za-z0-9+/]{3}=)?$/.test(match[3])) {
                    throw new Error('encoding');
                }
                bytes = Uint8Array.from(atob(match[3]), c => c.charCodeAt(0));
            } else {
                const text = match[3].replace(/_/g, ' ');
                const numbers: number[] = [];
                for (let i = 0; i < text.length; i++) {
                    if (text[i] === '=') {
                        const hex = text.slice(i + 1, i + 3);
                        if (!/^[a-f0-9]{2}$/i.test(hex)) { throw new Error('encoding'); }
                        numbers.push(parseInt(hex, 16));
                        i += 2;
                    } else {
                        if (text.charCodeAt(i) > 127) { throw new Error('encoding'); }
                        numbers.push(text.charCodeAt(i));
                    }
                }
                bytes = new Uint8Array(numbers);
            }
            decoded = new TextDecoder(match[1], { fatal: true }).decode(bytes);
        } catch {
            warnings.push('encoded_word');
        }
        const gap = value.slice(cursor, match.index);
        output += previousDecoded && decoded !== null && /^\s*$/.test(gap) ? '' : gap;
        output += decoded ?? match[0];
        previousDecoded = decoded !== null;
        cursor = match.index + match[0].length;
    }
    const remaining = value.slice(cursor);
    if (remaining.includes('=?')) { warnings.push('encoded_word'); }
    return { value: output + remaining, warnings: [...new Set(warnings)] };
}
