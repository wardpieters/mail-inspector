import { expect, it } from 'vitest';
import { parseHeaders } from '../parseHeaders';
import { recognizeMicrosoft } from './microsoft';
const read = (name: string, value: string) => recognizeMicrosoft(parseHeaders(name + ': ' + value).document!.fields[0]);
it('explains known keys while retaining unknown values and not inferring disposition', () => {
    const result = read('X-Forefront-Antispam-Report', 'SCL:5;CAT:SPM;CIP:192.0.2.1;UNKNOWN:value')!;
    expect(result).toMatchObject({ family: 'microsoft', score: null, action: null });
    expect(result.values.find(v => v.name === 'CAT')).toMatchObject({ value: 'SPM', explanationKey: expect.any(String) });
    expect(result.unknownFragments).toContain('UNKNOWN:value');
});
it('recognizes Microsoft bulk confidence and standalone SCL', () => {
    expect(read('X-Microsoft-Antispam', 'BCL:2;')?.values).toEqual(expect.arrayContaining([expect.objectContaining({ name: 'BCL', value: '2' })]));
    expect(read('X-MS-Exchange-Organization-SCL', '-1')?.values).toEqual(expect.arrayContaining([expect.objectContaining({ name: 'SCL', value: '-1' })]));
});
