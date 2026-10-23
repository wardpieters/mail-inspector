import { expect, it } from 'vitest';
import { parseHeaders } from '../parseHeaders';
import { recognizeGeneric } from './generic';
it.each([['X-Barracuda-Spam-Score', 'barracuda'], ['X-Proofpoint-Spam-Details', 'proofpoint'], ['X-IronPort-Anti-Spam-Result', 'ironport'], ['X-Spam-Custom', 'generic'], ['X-Rspamd-Custom', 'generic'], ['X-Spamd-Custom', 'generic'], ['X-Other-Antispam', 'generic']])('recognizes %s as indicative metadata only', (name, family) => {
    expect(recognizeGeneric(parseHeaders(name + ': unknown').document!.fields[0])).toMatchObject({ family, score: null, threshold: null, values: [{ name, value: 'unknown', explanationKey: null }] });
});
