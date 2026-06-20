const STYLE_ID = 'exam-builder-print-page-style';

export function applyPrintStyles({
    paperSize = 'A4',
    orientation = 'portrait',
}) {
    let styleEl = document.getElementById(STYLE_ID);
    if (!styleEl) {
        styleEl = document.createElement('style');
        styleEl.id = STYLE_ID;
        document.head.appendChild(styleEl);
    }

    // Use a small fixed hardware margin so the paper's outer border sits close to
    // the physical edge (matching real exam paper appearance). The template's own
    // internal padding (--paper-padding-* from Page margins settings) handles
    // the spacing between the border and the text.
    styleEl.textContent = `
        @media print {
            @page {
                size: ${paperSize} ${orientation};
                margin: 5mm;
            }
        }
    `;
}

export function clearPrintStyles() {
    document.getElementById(STYLE_ID)?.remove();
}
