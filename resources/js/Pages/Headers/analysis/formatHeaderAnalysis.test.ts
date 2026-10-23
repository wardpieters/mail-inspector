import { expect, it } from 'vitest';
import { analyzeHeaders } from './analyzeHeaders';
import { formatHeaderAnalysis, serializeHeaderAnalysis } from './formatHeaderAnalysis';
const analysis = () => analyzeHeaders('Subject: Example\nAuthentication-Results: mx.example; spf=pass\nX-Spam-Status: No, score=0 required=5\n\nSECRET_BODY').analysis!;
it('exports versioned JSON with evidence and limitations but no message body', () => {
    const text = serializeHeaderAnalysis(analysis());
    expect(JSON.parse(text)).toMatchObject({ schemaVersion: 1, analysis: { summary: { subject: [{ value: 'Example' }] } }, limitations: expect.any(Array) });
    expect(text).not.toContain('SECRET_BODY');
    expect(text).toContain('Authentication-Results');
});
it('copies a localized human-readable report including evidence and limitations', () => {
    const text = formatHeaderAnalysis(analysis(), key => ({ 'Message summary': 'Berichtoverzicht', 'Reported pass': 'Gerapporteerd geslaagd' })[key] ?? key);
    expect(text).toContain('Berichtoverzicht');
    expect(text).toContain('Gerapporteerd geslaagd');
    expect(text).toContain('Authentication-Results: mx.example; spf=pass');
    expect(text).not.toContain('SECRET_BODY');
});
