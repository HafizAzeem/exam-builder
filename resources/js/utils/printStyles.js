const STYLE_ID = 'exam-builder-print-page-style';

/**
 * @param {{ dual?: boolean }} [options]
 */
export function applyPrintStyles(options = {}) {
    let styleEl = document.getElementById(STYLE_ID);
    if (!styleEl) {
        styleEl = document.createElement('style');
        styleEl.id = STYLE_ID;
        document.head.appendChild(styleEl);
    }

    const dual = Boolean(options.dual);

    // Never set @page `size` here — Chrome/Edge hide the Layout
    // (Portrait/Landscape) dropdown whenever size is specified.
    // Double-page users must pick Landscape in the print dialog.
    styleEl.textContent = `
        @media print {
            @page {
                margin: 5mm;
            }
        }
    `;

    document.body.classList.toggle('print-dual', dual);
    document.body.classList.toggle('print-single', !dual);
}

export function clearPrintStyles() {
    document.getElementById(STYLE_ID)?.remove();
    document.body.classList.remove('print-dual', 'print-single');
}
