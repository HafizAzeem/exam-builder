const ROMAN = ['i', 'ii', 'iii', 'iv', 'v', 'vi', 'vii', 'viii', 'ix', 'x', 'xi', 'xii', 'xiii', 'xiv', 'xv'];

const SECTION_COPY = {
    mcq: { en: 'Choose the correct option.', ur: 'درست جواب کا انتخاب کریں۔', marksPer: 1 },
    short: { en: 'Write short answers of following questions.', ur: 'مختصر سوالات کے جوابات لکھیں۔', marksPer: 2, note: '(Answer any 5)' },
    long: { en: 'Write detailed answers of the following questions.', ur: 'تفصیلی سوالات کے جوابات لکھیں۔', marksPer: 5 },
    fill: { en: 'Fill in the blanks.', ur: 'خالی جگہ پُر کریں۔', marksPer: 1 },
    truefalse: { en: 'Mark True or False.', ur: 'درست یا غلط نشان لگائیں۔', marksPer: 1 },
};

export const toRoman = (idx) => ROMAN[idx] ?? String(idx + 1);

const getMcqOptions = (q) => q?.mcq_options ?? q?.mcqOptions ?? null;

const institutionAddressLine = (inst) => {
    if (!inst) return '';
    const address = (inst.address || '').trim();
    const city = (inst.city || '').trim();
    let location = address;
    if (city && !address.toLowerCase().includes(city.toLowerCase())) {
        location = location ? `${location}, ${city}` : city;
    }
    const phone = inst.phone ? ` Ph:${inst.phone}` : '';
    return (location || city) + phone;
};

const pastPaperRef = (q, layout) => {
    const tag = q?.past_paper_tag ?? q?.pastPaperTag ?? null;
    if (!layout?.show_past_paper_tags || q?.source !== 'past_paper' || !tag) return '';
    return `[${tag.board_name} ${tag.year}]`;
};

const sectionMarks = (section) => {
    const per = SECTION_COPY[section.type]?.marksPer ?? 1;
    const n = section.question_count ?? section.questions?.length ?? 0;
    return `(${n}X${per}=${n * per})`;
};

const mcqOptionsContent = (q) => {
    const o = getMcqOptions(q);
    if (!o) return null;
    return {
        A: { en: o.option_a_en ?? '', ur: o.option_a_ur ?? '' },
        B: { en: o.option_b_en ?? '', ur: o.option_b_ur ?? '' },
        C: { en: o.option_c_en ?? '', ur: o.option_c_ur ?? '' },
        D: { en: o.option_d_en ?? '', ur: o.option_d_ur ?? '' },
    };
};

/**
 * Build editable paper JSON from server preview payload.
 */
export function buildPaperContentFromPreview(preview, title = '') {
    const layout = preview?.layout ?? {};
    const sections = (preview?.sections ?? []).map((section) => ({
        type: section.type,
        number: section.number,
        heading_en: SECTION_COPY[section.type]?.en ?? section.title ?? '',
        heading_ur: SECTION_COPY[section.type]?.ur ?? '',
        note: SECTION_COPY[section.type]?.note ?? '',
        marks: sectionMarks(section),
        questions: (section.questions ?? []).map((q, idx) => ({
            id: q.id,
            type: q.type,
            roman: toRoman(idx),
            text_en: q.text_en ?? '',
            text_ur: q.text_ur ?? '',
            past_ref: pastPaperRef(q, layout),
            options: q.type === 'mcq' ? mcqOptionsContent(q) : null,
            parts: (q.parts ?? []).map((p, pIdx) => ({
                id: p.id,
                label: String.fromCharCode(97 + pIdx),
                text_en: p.text_en ?? '',
                text_ur: p.text_ur ?? '',
            })),
        })),
    }));

    const inst = preview?.institution ?? preview?.institutionOverride ?? null;

    return {
        header: {
            institute_name: inst?.name ?? '',
            institute_address: institutionAddressLine(inst),
            paper_type: title || preview?.paper?.title || '',
            paper_time: preview?.exam_meta?.time ?? '',
            class: preview?.exam_meta?.class ?? '',
            subject: preview?.exam_meta?.subject ?? '',
            marks: preview?.exam_meta?.marks ?? '',
        },
        sections,
    };
}

export function clonePaperContent(content) {
    return JSON.parse(JSON.stringify(content ?? { header: {}, sections: [] }));
}
