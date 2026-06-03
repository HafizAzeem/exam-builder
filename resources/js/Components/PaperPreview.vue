<script setup>
import { computed } from 'vue';

const props = defineProps({
    title: String,
    questions: { type: Array, default: () => [] },
    dualMedium: Boolean,
    settings: Object,
    examMeta: Object,
    layout: Object,
    institution: Object,
    sections: Array,
    omrRows: Array,
    answerKey: Array,
});

const ROMAN = ['i', 'ii', 'iii', 'iv', 'v', 'vi', 'vii', 'viii', 'ix', 'x', 'xi', 'xii', 'xiii', 'xiv', 'xv'];

const SECTION_COPY = {
    mcq: {
        en: 'Choose the correct option.',
        ur: 'درست جواب کا انتخاب کریں۔',
        marksPer: 1,
    },
    short: {
        en: 'Write short answers of following questions.',
        ur: 'مختصر سوالات کے جوابات لکھیں۔',
        marksPer: 2,
        note: '(Answer any 5)',
    },
    long: {
        en: 'Write detailed answers of the following questions.',
        ur: 'تفصیلی سوالات کے جوابات لکھیں۔',
        marksPer: 5,
    },
    fill: {
        en: 'Fill in the blanks.',
        ur: 'خالی جگہ پُر کریں۔',
        marksPer: 1,
    },
    truefalse: {
        en: 'Mark True or False.',
        ur: 'درست یا غلط نشان لگائیں۔',
        marksPer: 1,
    },
};

const headerTemplate = () => Number(props.layout?.header_template ?? 1);
const isTemplate1 = computed(() => headerTemplate() === 1);

const logoSrc = computed(() => {
    const path = props.institution?.logo_path;
    if (!path) return null;
    return path.startsWith('http') ? path : `/storage/${path}`;
});

const institutionAddressLine = computed(() => {
    const inst = props.institution;
    if (!inst) return '';
    const address = (inst.address || '').trim();
    const city = (inst.city || '').trim();
    let location = address;
    if (city && !address.toLowerCase().includes(city.toLowerCase())) {
        location = location ? `${location}, ${city}` : city;
    }
    const phone = inst.phone ? ` Ph:${inst.phone}` : '';
    return (location || city) + phone;
});

const getMcqOptions = (q) => q?.mcq_options ?? q?.mcqOptions ?? null;

const getPastPaperTag = (q) => q?.past_paper_tag ?? q?.pastPaperTag ?? null;

const getParts = (q) => q?.parts ?? [];

const watermarkLines = computed(() => {
    const text = props.layout?.watermark_text ?? '';
    return text.split('\n').map((l) => l.trim()).filter(Boolean);
});

const showWatermark = computed(
    () => props.layout?.enable_watermark && watermarkLines.value.length > 0,
);

const groupQuestionsByType = (list) => {
    const order = ['mcq', 'short', 'long', 'fill', 'truefalse'];
    const grouped = {};
    for (const q of list) {
        (grouped[q.type] ||= []).push(q);
    }
    let num = 1;
    return order
        .filter((t) => grouped[t]?.length)
        .map((type) => ({
            type,
            number: num++,
            questions: grouped[type],
            question_count: grouped[type].length,
        }));
};

const displaySections = computed(() => {
    const raw = props.sections?.length ? props.sections : groupQuestionsByType(props.questions);
    return raw.map((section, idx) => ({
        ...section,
        number: section.number ?? idx + 1,
        question_count: section.question_count ?? section.questions?.length ?? 0,
    }));
});

const toRoman = (idx) => ROMAN[idx] ?? String(idx + 1);

const sectionMarks = (section) => {
    const per = SECTION_COPY[section.type]?.marksPer ?? 1;
    const n = section.question_count ?? section.questions?.length ?? 0;
    return `(${n}X${per}=${n * per})`;
};

const sectionHeadingEn = (section) => SECTION_COPY[section.type]?.en ?? section.title ?? '';

const sectionHeadingUr = (section) => SECTION_COPY[section.type]?.ur ?? '';

const sectionNote = (section) => SECTION_COPY[section.type]?.note ?? '';

const pastPaperRef = (q) => {
    const tag = getPastPaperTag(q);
    if (!props.layout?.show_past_paper_tags || q.source !== 'past_paper' || !tag) {
        return '';
    }
    return `[${tag.board_name} ${tag.year}]`;
};

const mcqOptionCells = (q) => {
    const o = getMcqOptions(q);
    if (!o) return [];
    return [
        { key: 'A', en: o.option_a_en, ur: o.option_a_ur },
        { key: 'B', en: o.option_b_en, ur: o.option_b_ur },
        { key: 'C', en: o.option_c_en, ur: o.option_c_ur },
        { key: 'D', en: o.option_d_en, ur: o.option_d_ur },
    ];
};

const meta = computed(() => ({
    class: props.examMeta?.class ?? '',
    subject: props.examMeta?.subject ?? '',
    time: props.examMeta?.time ?? '',
    marks: props.examMeta?.marks ?? '',
    paperType: props.title ?? '',
}));
</script>

<template>
    <div
        class="paper-preview"
        :class="{ 'paper-preview--tpl1': isTemplate1 }"
        :style="{
            fontFamily: layout?.font_family || 'Arial',
            fontSize: (layout?.font_size || 12) + 'pt',
            color: layout?.font_color || '#000',
            lineHeight: layout?.line_height || 1.5,
            transform: layout?.scale ? `scale(${layout.scale / 100})` : undefined,
            transformOrigin: layout?.scale ? 'top left' : undefined,
            '--print-margin-top': (layout?.margins?.top ?? 15) + 'mm',
            '--print-margin-right': (layout?.margins?.right ?? 15) + 'mm',
            '--print-margin-bottom': (layout?.margins?.bottom ?? 15) + 'mm',
            '--print-margin-left': (layout?.margins?.left ?? 15) + 'mm',
            '--wm-opacity': layout?.watermark_opacity ?? 0.18,
            '--wm-angle': (layout?.watermark_angle ?? 45) + 'deg',
        }"
    >
        <div v-if="showWatermark" class="watermark-layer" aria-hidden="true">
            <div v-for="(line, i) in watermarkLines" :key="i" class="watermark-line">{{ line }}</div>
        </div>

        <header class="paper-header" :class="{ 'mb-6 border-b pb-4': !isTemplate1 }">
            <!-- Template 1: Creative Test Maker style -->
            <template v-if="isTemplate1">
                <div class="tpl1-header-dashed">
                    <h1 class="tpl1-institute-name">{{ institution?.name || 'Institution Name' }}</h1>
                    <p v-if="institutionAddressLine" class="tpl1-institute-address">{{ institutionAddressLine }}</p>
                </div>
                <table class="tpl1-info-table">
                    <tbody>
                        <tr>
                            <td class="tpl1-info-cell">
                                <div class="tpl1-info-row"><span>Student Name:</span><span class="tpl1-blank" /></div>
                                <div class="tpl1-info-row"><span>Paper Type:</span><span>{{ meta.paperType }}</span></div>
                                <div class="tpl1-info-row"><span>Paper Time:</span><span>{{ meta.time }}</span></div>
                            </td>
                            <td class="tpl1-logo-cell">
                                <img v-if="logoSrc" :src="logoSrc" alt="Logo" class="tpl1-logo" />
                            </td>
                            <td class="tpl1-info-cell tpl1-info-cell--right">
                                <div class="tpl1-info-row"><span>Class:</span><span>{{ meta.class }}</span></div>
                                <div class="tpl1-info-row"><span>Subject:</span><span>{{ meta.subject }}</span></div>
                                <div class="tpl1-info-row"><span>Maximum Marks:</span><span>{{ meta.marks }}</span></div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </template>

            <template v-else-if="headerTemplate() === 2">
                <div class="flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <img v-if="logoSrc" :src="logoSrc" alt="Logo" class="h-16 w-16 object-contain" />
                        <div>
                            <h1 class="text-lg font-bold leading-tight">{{ institution?.name || 'Institution Name' }}</h1>
                            <p class="text-xs text-gray-600">{{ institution?.city || '' }}</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <h2 class="text-base font-semibold leading-tight">{{ title }}</h2>
                        <p class="text-xs text-gray-600">Generated: {{ new Date().toLocaleDateString() }}</p>
                    </div>
                </div>
            </template>

            <template v-else-if="headerTemplate() === 3">
                <div class="grid grid-cols-3 items-center gap-4">
                    <div>
                        <p class="text-xs text-gray-600">Institute</p>
                        <p class="font-bold">{{ institution?.name || 'Institution Name' }}</p>
                        <p class="text-xs text-gray-600">{{ institution?.phone || '' }}</p>
                    </div>
                    <div class="text-center">
                        <img v-if="logoSrc" :src="logoSrc" alt="Logo" class="mx-auto h-14 object-contain" />
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-gray-600">Exam</p>
                        <p class="text-base font-semibold">{{ title }}</p>
                    </div>
                </div>
            </template>

            <template v-else-if="headerTemplate() === 4">
                <div class="text-center">
                    <h1 class="text-lg font-extrabold tracking-wide uppercase">{{ institution?.name || 'Institution Name' }}</h1>
                    <div class="mt-2 flex items-center justify-center gap-3">
                        <span class="h-px w-16 bg-gray-300" />
                        <h2 class="text-base font-semibold">{{ title }}</h2>
                        <span class="h-px w-16 bg-gray-300" />
                    </div>
                </div>
            </template>

            <template v-else-if="headerTemplate() === 5">
                <div class="flex items-center gap-4">
                    <img v-if="logoSrc" :src="logoSrc" alt="Logo" class="h-20 w-20 object-contain" />
                    <div class="flex-1">
                        <h1 class="text-lg font-bold">{{ institution?.name || 'Institution Name' }}</h1>
                        <h2 class="mt-1 text-base font-semibold">{{ title }}</h2>
                        <p class="mt-1 text-xs text-gray-600">Address: {{ institution?.city || '' }}</p>
                    </div>
                </div>
            </template>

            <template v-else-if="headerTemplate() === 6">
                <div class="flex items-center justify-between">
                    <div class="text-left">
                        <h1 class="text-base font-bold">{{ institution?.name || 'Institution Name' }}</h1>
                        <p class="text-xs text-gray-600">{{ institution?.city || '' }}</p>
                    </div>
                    <div class="text-center">
                        <h2 class="text-lg font-semibold">{{ title }}</h2>
                    </div>
                    <div class="text-right text-xs text-gray-600">
                        <div>Phone: {{ institution?.phone || '-' }}</div>
                        <div>Date: {{ new Date().toLocaleDateString() }}</div>
                    </div>
                </div>
            </template>

            <template v-else>
                <div class="text-center">
                    <h1 class="text-lg font-bold">{{ institution?.name || 'Institution Name' }}</h1>
                    <h2 class="mt-2 text-base font-semibold">{{ title }}</h2>
                    <p class="mt-1 text-xs text-gray-600">Official Exam Paper</p>
                </div>
            </template>
        </header>

        <!-- Template 1 body -->
        <template v-if="isTemplate1 && displaySections.length">
            <section v-for="section in displaySections" :key="section.type" class="tpl1-section">
                <div class="tpl1-section-heading">
                    <div class="tpl1-section-heading-en">
                        <strong>Q{{ section.number }}.</strong>
                        {{ sectionHeadingEn(section) }}
                        <span v-if="sectionNote(section)" class="tpl1-section-note">{{ sectionNote(section) }}</span>
                        <span class="tpl1-section-marks">{{ sectionMarks(section) }}</span>
                    </div>
                    <div v-if="dualMedium && sectionHeadingUr(section)" class="tpl1-section-heading-ur question-ur">
                        {{ sectionHeadingUr(section) }}
                    </div>
                </div>

                <div
                    v-for="(q, idx) in section.questions"
                    :key="q.id"
                    class="tpl1-question"
                    :class="layout?.dual_column ? 'questions-container dual-col' : ''"
                >
                    <div class="tpl1-question-row">
                        <div class="tpl1-question-en">
                            <span class="tpl1-q-num">{{ toRoman(idx) }}.</span>
                            <span>{{ q.text_en }}</span>
                            <p v-if="pastPaperRef(q)" class="tpl1-past-ref">{{ pastPaperRef(q) }}</p>
                        </div>
                        <div v-if="dualMedium && q.text_ur" class="tpl1-question-ur question-ur">{{ q.text_ur }}</div>
                    </div>

                    <div v-if="q.type === 'mcq' && getMcqOptions(q)" class="tpl1-mcq-options">
                        <div v-for="opt in mcqOptionCells(q)" :key="opt.key" class="tpl1-mcq-cell">
                            <span class="tpl1-mcq-key">({{ opt.key }})</span>
                            <span>{{ opt.en }}</span>
                            <span v-if="dualMedium && opt.ur" class="question-ur tpl1-mcq-ur">{{ opt.ur }}</span>
                        </div>
                    </div>

                    <div v-else-if="q.type === 'long' && q.has_parts && getParts(q).length" class="tpl1-parts">
                        <div v-for="(p, pIdx) in getParts(q)" :key="p.id" class="tpl1-question-row tpl1-part-row">
                            <div class="tpl1-question-en">
                                <span class="tpl1-q-num">({{ String.fromCharCode(97 + pIdx) }})</span>
                                <span>{{ p.text_en }}</span>
                            </div>
                            <div v-if="dualMedium && p.text_ur" class="tpl1-question-ur question-ur">{{ p.text_ur }}</div>
                        </div>
                    </div>

                    <div v-else-if="q.type === 'truefalse'" class="tpl1-truefalse">
                        <label><span class="tpl1-tf-box" /> True</label>
                        <label><span class="tpl1-tf-box" /> False</label>
                    </div>

                    <div
                        v-else-if="settings?.blank_lines"
                        class="tpl1-blank-area"
                        :style="{ minHeight: settings.blank_lines * 24 + 'px' }"
                    />
                </div>
            </section>
        </template>

        <!-- Legacy body (templates 2–7 or flat questions) -->
        <template v-else-if="displaySections.length">
            <div
                v-for="section in displaySections"
                :key="section.type"
                class="mb-6"
                :class="layout?.dual_column ? 'questions-container dual-col' : ''"
            >
                <h3 class="mb-3 font-bold">{{ section.title || sectionHeadingEn(section) }}</h3>
                <div v-for="(q, idx) in section.questions" :key="q.id" class="question-block mb-4">
                    <p>
                        <strong>Q{{ idx + 1 }}.</strong> {{ q.text_en }}
                        <span
                            v-if="layout?.show_past_paper_tags && q.source === 'past_paper' && getPastPaperTag(q)"
                            class="ms-2 rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-700"
                        >
                            {{ getPastPaperTag(q).board_name }} {{ getPastPaperTag(q).year }}
                        </span>
                    </p>
                    <p v-if="dualMedium && q.text_ur" class="question-ur">{{ q.text_ur }}</p>

                    <div v-if="q.type === 'long' && q.has_parts && getParts(q).length" class="ms-6 mt-2 space-y-2">
                        <div v-for="(p, pIdx) in getParts(q)" :key="p.id">
                            <p>
                                <strong>({{ String.fromCharCode(97 + pIdx) }})</strong>
                                {{ p.text_en }}
                            </p>
                            <p v-if="dualMedium && p.text_ur" class="question-ur">{{ p.text_ur }}</p>
                        </div>
                    </div>
                    <div v-if="q.type === 'mcq' && getMcqOptions(q)" class="ms-6 mt-1 grid grid-cols-2 gap-1">
                        <span>A) {{ getMcqOptions(q).option_a_en }}</span>
                        <span>B) {{ getMcqOptions(q).option_b_en }}</span>
                        <span>C) {{ getMcqOptions(q).option_c_en }}</span>
                        <span>D) {{ getMcqOptions(q).option_d_en }}</span>
                    </div>
                    <div v-else-if="q.type === 'truefalse'" class="ms-6 mt-2 flex items-center gap-6">
                        <label class="inline-flex items-center gap-2">
                            <span class="inline-block h-4 w-4 rounded border border-gray-900" />
                            True
                        </label>
                        <label class="inline-flex items-center gap-2">
                            <span class="inline-block h-4 w-4 rounded border border-gray-900" />
                            False
                        </label>
                    </div>
                    <div v-else-if="settings?.blank_lines" :style="{ height: settings.blank_lines * 24 + 'px' }" />
                </div>
            </div>
        </template>

        <template v-else>
            <div v-for="(q, idx) in questions" :key="q.id" class="question-block mb-4">
                <p><strong>Q{{ idx + 1 }}.</strong> {{ q.text_en }}</p>
                <p v-if="dualMedium && q.text_ur" class="question-ur">{{ q.text_ur }}</p>
            </div>
        </template>

        <div v-if="omrRows?.length" class="omr-sheet mt-8">
            <h3 class="mb-4 font-bold">OMR Answer Sheet</h3>
            <div v-for="row in omrRows" :key="row.number" class="mb-2 flex items-center gap-4">
                <span class="w-8">{{ row.number }}.</span>
                <span
                    v-for="opt in row.options"
                    :key="opt"
                    class="inline-flex h-6 w-6 items-center justify-center rounded-full border border-gray-800"
                    >{{ opt }}</span
                >
            </div>
        </div>

        <div v-if="answerKey?.length" class="section-break mt-8">
            <h3 class="mb-4 font-bold">Teacher Answer Key</h3>
            <div class="grid grid-cols-5 gap-2">
                <span v-for="a in answerKey" :key="a.number">{{ a.number }}: {{ a.answer }}</span>
            </div>
        </div>
    </div>
</template>

<style scoped>
.paper-preview {
    position: relative;
    overflow: hidden;
    border-radius: 0.25rem;
    border: 1px solid #e5e7eb;
    background: #fff;
    padding: 1.5rem;
    font-size: 0.875rem;
    box-shadow: inset 0 2px 4px 0 rgb(0 0 0 / 0.05);
}

.paper-preview--tpl1 {
    border: 2px solid #000;
    padding: 12mm 10mm;
    width: 100%;
    max-width: 210mm;
    margin-left: auto;
    margin-right: auto;
    box-sizing: border-box;
}

.watermark-layer {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    transform: rotate(var(--wm-angle, 45deg));
    opacity: var(--wm-opacity, 0.18);
    pointer-events: none;
    z-index: 0;
    user-select: none;
}

.watermark-line {
    font-size: 1.35rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    color: #9ca3af;
    text-align: center;
    white-space: nowrap;
    line-height: 1.2;
}

.paper-header,
.tpl1-section,
.omr-sheet,
.section-break {
    position: relative;
    z-index: 1;
}

/* Template 1 header */
.tpl1-header-dashed {
    border: 2px dashed #000;
    padding: 10px 12px;
    text-align: center;
    margin-bottom: 0;
}

.tpl1-institute-name {
    font-size: 1.25rem;
    font-weight: 700;
    margin: 0;
    line-height: 1.3;
}

.tpl1-institute-address {
    margin: 4px 0 0;
    font-size: 0.85rem;
}

.tpl1-info-table {
    width: 100%;
    border-collapse: collapse;
    border: 2px solid #000;
    border-top: none;
    table-layout: fixed;
}

.tpl1-info-cell {
    width: 38%;
    vertical-align: middle;
    padding: 8px 10px;
    border-right: 1px solid #000;
    font-size: 0.85rem;
}

.tpl1-info-cell--right {
    border-right: none;
    border-left: 1px solid #000;
    text-align: left;
}

.tpl1-logo-cell {
    width: 24%;
    text-align: center;
    vertical-align: middle;
    padding: 8px;
    border-right: 1px solid #000;
}

.tpl1-logo {
    height: 72px;
    width: 72px;
    object-fit: contain;
    margin: 0 auto;
}

.tpl1-info-row {
    display: flex;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 6px;
    min-height: 1.25rem;
}

.tpl1-info-row:last-child {
    margin-bottom: 0;
}

.tpl1-blank {
    flex: 1;
    border-bottom: 1px solid #000;
    min-width: 40px;
}

/* Template 1 sections */
.tpl1-section {
    margin-top: 14px;
    padding-top: 8px;
    border-top: 1px solid #000;
}

.tpl1-section:first-of-type {
    border-top: none;
    margin-top: 10px;
    padding-top: 0;
}

.tpl1-section-heading {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 10px;
    font-weight: 700;
}

.tpl1-section-heading-en {
    flex: 1;
}

.tpl1-section-heading-ur {
    flex-shrink: 0;
    max-width: 42%;
    font-weight: 700;
}

.tpl1-section-note {
    font-weight: 600;
    margin-left: 4px;
}

.tpl1-section-marks {
    margin-left: 6px;
}

.tpl1-question {
    margin-bottom: 10px;
    padding-bottom: 8px;
    border-bottom: 1px solid #d1d5db;
}

.tpl1-question:last-child {
    border-bottom: none;
}

.tpl1-question-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
}

.tpl1-question-en {
    flex: 1;
    min-width: 0;
}

.tpl1-question-ur {
    flex-shrink: 0;
    max-width: 42%;
    text-align: right;
}

.tpl1-q-num {
    font-weight: 700;
    margin-right: 4px;
}

.tpl1-past-ref {
    margin: 2px 0 0 1.2rem;
    font-size: 0.75rem;
    color: #374151;
}

.tpl1-mcq-options {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 6px;
    margin-top: 6px;
    margin-left: 1rem;
}

.tpl1-mcq-cell {
    border: 1px solid #000;
    padding: 4px 6px;
    font-size: 0.8rem;
    min-height: 2rem;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 4px;
}

.tpl1-mcq-key {
    font-weight: 700;
}

.tpl1-mcq-ur {
    font-size: 0.75rem;
    width: 100%;
}

.tpl1-parts {
    margin-left: 1.2rem;
    margin-top: 6px;
}

.tpl1-part-row {
    margin-bottom: 6px;
}

.tpl1-truefalse {
    margin-left: 1.2rem;
    margin-top: 6px;
    display: flex;
    gap: 24px;
}

.tpl1-tf-box {
    display: inline-block;
    width: 14px;
    height: 14px;
    border: 1px solid #000;
    margin-right: 6px;
    vertical-align: middle;
}

.question-ur {
    direction: rtl;
    text-align: right;
    font-family: 'Noto Nastaliq Urdu', 'Jameel Noori Nastaleeq', serif;
}
</style>
