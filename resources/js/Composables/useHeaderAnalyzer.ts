import { onMounted, onUnmounted, ref, shallowRef, watch } from 'vue';
import type { HeaderAnalysis, HeaderText, ParseError } from '../types/headers';
import { analyzeHeaders } from '../Pages/Headers/analysis/analyzeHeaders';
import { formatHeaderAnalysis, serializeHeaderAnalysis } from '../Pages/Headers/analysis/formatHeaderAnalysis';
import { EXAMPLE_HEADERS } from '../Pages/Headers/analysis/example';

export function useHeaderAnalyzer() {
    const input = ref('');
    const analysis = shallowRef<HeaderAnalysis | null>(null);
    const error = ref<ParseError | null>(null);
    const status = ref<string | null>(null);
    const manualCopy = ref<string | null>(null);
    const urls = new Set<string>();
    let generation = 0;
    function invalidate(): void {
        generation++;
        analysis.value = null;
        error.value = null;
        status.value = null;
        manualCopy.value = null;
    }
    watch(input, invalidate, { flush: 'sync' });
    function clear(): void {
        input.value = '';
        invalidate();
        for (const url of urls) { URL.revokeObjectURL(url); }
        urls.clear();
    }
    function analyze(): void {
        invalidate();
        const result = analyzeHeaders(input.value);
        analysis.value = result.analysis;
        error.value = result.error;
        if (result.analysis) { status.value = 'Headers analyzed.'; }
    }
    function loadExample(): void { clear(); input.value = EXAMPLE_HEADERS; }
    async function copyAnalysis(t: HeaderText): Promise<void> {
        if (!analysis.value) { return; }
        const current = generation;
        const text = formatHeaderAnalysis(analysis.value, t);
        try {
            if (!navigator.clipboard?.writeText) { throw new Error('unavailable'); }
            await navigator.clipboard.writeText(text);
            if (current === generation) { manualCopy.value = null; status.value = 'Analysis copied.'; }
        } catch {
            if (current === generation) { manualCopy.value = text; status.value = 'Clipboard unavailable. Select and copy the report below.'; }
        }
    }
    function downloadJson(): void {
        if (!analysis.value) { return; }
        let url: string | null = null;
        try {
            url = URL.createObjectURL(new Blob([serializeHeaderAnalysis(analysis.value)], { type: 'application/json' }));
            urls.add(url);
            const anchor = document.createElement('a');
            anchor.href = url;
            anchor.download = 'message-header-analysis.json';
            document.body.append(anchor);
            anchor.click();
            anchor.remove();
            status.value = 'Analysis downloaded.';
        } catch {
            status.value = 'Download unavailable in this browser.';
        } finally {
            if (url) {
                const downloadUrl = url;
                setTimeout(() => { if (urls.delete(downloadUrl)) { URL.revokeObjectURL(downloadUrl); } }, 0);
            }
        }
    }
    function restored(event: PageTransitionEvent): void { if (event.persisted) { clear(); } }
    onMounted(() => { window.addEventListener('pageshow', restored); window.addEventListener('pagehide', clear); });
    onUnmounted(() => { clear(); window.removeEventListener('pageshow', restored); window.removeEventListener('pagehide', clear); });
    return { input, analysis, error, status, manualCopy, analyze, clear, loadExample, copyAnalysis, downloadJson };
}
