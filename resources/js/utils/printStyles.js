const STYLE_ID = 'exam-builder-print-page-style';

/** Minimum zoom used to force a single page. Below this, allow 2+ pages. */
export const MIN_PRINT_FIT_SCALE = 0.72;

/**
 * Convert mm → CSS pixels using a live probe (accurate across DPI/zoom).
 * @param {number} mm
 */
function mmToPx(mm) {
    const probe = document.createElement('div');
    probe.style.cssText = `position:absolute;left:-99999px;top:0;height:${mm}mm;width:1px;visibility:hidden;pointer-events:none;`;
    document.body.appendChild(probe);
    const px = probe.offsetHeight;
    probe.remove();
    return px || (mm / 25.4) * 96;
}

function resetPaperMeasureStyles(paper, saved) {
    if (!paper || !saved) return;
    paper.style.transform = saved.transform;
    paper.style.width = saved.width;
    paper.style.maxWidth = saved.maxWidth;
    paper.style.zoom = saved.zoom;
    paper.style.minHeight = saved.minHeight;
}

/**
 * Measure paper content and return a fit zoom for single-page print.
 * Uses CSS `zoom` (not transform:scale) so Chrome pagination matches visual size.
 *
 * @param {{ dual?: boolean, minScale?: number, safety?: number }} [options]
 * @returns {number} scale in (0, 1]
 */
export function measurePrintFitScale(options = {}) {
    if (options.dual) return 1;

    const root = document.getElementById('exam-print-root');
    if (!root) return 1;

    const paper = root.querySelector('.paper-preview');
    if (!paper) return 1;

    const minScale = options.minScale ?? MIN_PRINT_FIT_SCALE;
    const safety = options.safety ?? 0.98;

    const prevRoot = {
        display: root.style.display,
        visibility: root.style.visibility,
        position: root.style.position,
        left: root.style.left,
        top: root.style.top,
        width: root.style.width,
        zIndex: root.style.zIndex,
        pointerEvents: root.style.pointerEvents,
        zoom: root.style.zoom,
    };

    // Lay out off-screen at real A4 width so scrollHeight matches print.
    root.style.display = 'block';
    root.style.visibility = 'hidden';
    root.style.position = 'absolute';
    root.style.left = '-99999px';
    root.style.top = '0';
    root.style.width = '210mm';
    root.style.zIndex = '-1';
    root.style.pointerEvents = 'none';
    root.style.zoom = '1';

    const prevPaper = {
        transform: paper.style.transform,
        width: paper.style.width,
        maxWidth: paper.style.maxWidth,
        zoom: paper.style.zoom,
        minHeight: paper.style.minHeight,
    };
    paper.style.transform = 'none';
    paper.style.zoom = '1';
    paper.style.width = '210mm';
    paper.style.maxWidth = '210mm';
    // Measure real content height, not the A4 frame min-height.
    paper.style.minHeight = '0';

    void paper.offsetHeight;
    const contentHeight = Math.max(paper.scrollHeight, paper.offsetHeight);

    // @page margin 5mm each side → usable ≈ 287mm
    const pageHeightPx = mmToPx(287);

    Object.keys(prevRoot).forEach((key) => {
        root.style[key] = prevRoot[key];
    });
    resetPaperMeasureStyles(paper, prevPaper);

    if (!contentHeight || contentHeight <= pageHeightPx) {
        return 1;
    }

    let scale = (pageHeightPx / contentHeight) * safety;
    scale = Math.min(1, Math.max(0.5, scale));

    // Too much content → keep 100% and allow natural 2+ page flow.
    if (scale < minScale) {
        return 1;
    }

    return Math.round(scale * 1000) / 1000;
}

/**
 * @param {{ dual?: boolean, fitScale?: number }} [options]
 */
export function applyPrintStyles(options = {}) {
    let styleEl = document.getElementById(STYLE_ID);
    if (!styleEl) {
        styleEl = document.createElement('style');
        styleEl.id = STYLE_ID;
        document.head.appendChild(styleEl);
    }

    const dual = Boolean(options.dual);
    const fitScale = dual ? 1 : Number(options.fitScale ?? 1);
    const useFit = !dual && fitScale > 0 && fitScale < 0.999;

    // Never set @page `size` — Chrome/Edge hide the Layout dropdown when size is set.
    // Use CSS `zoom` (not transform:scale): zoom changes layout size so pagination
    // matches the visual shrink (Chrome bug with transform leaves a blank 2nd page).
    const fitCss = useFit
        ? `
        body.print-active > #exam-print-root:not(.exam-print-dual) {
            zoom: ${fitScale} !important;
            width: 210mm !important;
            max-width: 210mm !important;
            overflow: visible !important;
            --print-fit-scale: ${fitScale};
        }
        body.print-active > #exam-print-root:not(.exam-print-dual) > .paper-preview,
        body.print-active > #exam-print-root:not(.exam-print-dual) > .paper-preview--tpl1 {
            transform: none !important;
            width: 210mm !important;
            max-width: 210mm !important;
            margin: 0 !important;
            border: none !important;
            min-height: 0 !important;
        }
        `
        : `
        body.print-active > #exam-print-root:not(.exam-print-dual) {
            zoom: 1 !important;
        }
        `;

    styleEl.textContent = `
        @media print {
            @page {
                margin: 5mm;
            }
            ${fitCss}
        }
    `;

    document.body.classList.toggle('print-dual', dual);
    document.body.classList.toggle('print-single', !dual);
    document.body.classList.toggle('print-fit', useFit);

    const root = document.getElementById('exam-print-root');
    if (root) {
        root.classList.toggle('exam-print-fit', useFit);
        if (useFit) {
            root.style.setProperty('--print-fit-scale', String(fitScale));
            root.style.zoom = String(fitScale);
        } else {
            root.style.removeProperty('--print-fit-scale');
            root.style.zoom = '';
        }
    }
}

export function clearPrintStyles() {
    document.getElementById(STYLE_ID)?.remove();
    document.body.classList.remove('print-dual', 'print-single', 'print-fit');
    const root = document.getElementById('exam-print-root');
    if (root) {
        root.classList.remove('exam-print-fit');
        root.style.removeProperty('--print-fit-scale');
        root.style.zoom = '';
    }
}
