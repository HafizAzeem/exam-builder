const STYLE_ID = 'exam-builder-print-page-style';

export function applyPrintStyles({
    paperSize = 'A4',
    orientation = 'portrait',
    margins = { top: 15, right: 15, bottom: 15, left: 15 },
}) {
    let styleEl = document.getElementById(STYLE_ID);
    if (!styleEl) {
        styleEl = document.createElement('style');
        styleEl.id = STYLE_ID;
        document.head.appendChild(styleEl);
    }

    styleEl.textContent = `
        @media print {
            @page {
                size: ${paperSize} ${orientation};
                margin: ${margins.top ?? 15}mm ${margins.right ?? 15}mm
                        ${margins.bottom ?? 15}mm ${margins.left ?? 15}mm;
            }
        }
    `;
}

export function clearPrintStyles() {
    document.getElementById(STYLE_ID)?.remove();
}
