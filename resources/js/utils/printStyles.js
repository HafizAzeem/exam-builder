const STYLE_ID = 'exam-builder-print-page-style';

export function applyPrintStyles() {
    let styleEl = document.getElementById(STYLE_ID);
    if (!styleEl) {
        styleEl = document.createElement('style');
        styleEl.id = STYLE_ID;
        document.head.appendChild(styleEl);
    }

    // Do NOT set `size` in @page — any size value causes Chrome to hide the
    // Layout (Portrait/Landscape) dropdown in the print dialog.
    // Paper dimensions are already defined by .paper-preview CSS width.
    // Only apply the hardware margin so the template border sits near the edge.
    styleEl.textContent = `
        @media print {
            @page {
                margin: 5mm;
            }
        }
    `;
}

export function clearPrintStyles() {
    document.getElementById(STYLE_ID)?.remove();
}
