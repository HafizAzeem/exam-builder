<script setup>
import { computed } from 'vue';
import {
    buildPaperContentFromPreview,
    clonePaperContent,
    hydratePaperContentUrdu,
    toRoman,
} from '@/utils/paperContent';

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
    /** When set, renders from this JSON instead of raw question models */
    paperContent: Object,
    editable: Boolean,
    /** Stretch template to full container width (editor preview) */
    fillWidth: Boolean,
});

const emit = defineEmits(['update:paperContent']);

const SECTION_COPY = {
    mcq: { en: 'Choose the correct option.', ur: 'درست جواب کا انتخاب کریں۔', marksPer: 1 },
    short: { en: 'Write short answers of following questions.', ur: 'مختصر سوالات کے جوابات لکھیں۔', marksPer: 2, note: '(Answer any 5)' },
    long: { en: 'Write detailed answers of the following questions.', ur: 'تفصیلی سوالات کے جوابات لکھیں۔', marksPer: 5 },
    fill: { en: 'Fill in the blanks.', ur: 'خالی جگہ پُر کریں۔', marksPer: 1 },
    truefalse: { en: 'Mark True or False.', ur: 'درست یا غلط نشان لگائیں۔', marksPer: 1 },
};

const headerTemplate = () => Number(props.layout?.header_template ?? 1);
const isTemplate1 = computed(() => headerTemplate() === 1);

const logoSrc = computed(() => {
    const path = props.institution?.logo_path;
    if (!path) return null;
    return path.startsWith('http') ? path : `/storage/${path}`;
});

const getMcqOptions = (q) => q?.mcq_options ?? q?.mcqOptions ?? null;
const getPastPaperTag = (q) => q?.past_paper_tag ?? q?.pastPaperTag ?? null;
const getParts = (q) => q?.parts ?? [];

const watermarkLines = computed(() => {
    const text = (props.layout?.watermark_text ?? '').trim();
    if (!text) return [];
    return text.split('\n').map((l) => l.trim()).filter(Boolean);
});

const watermarkImageSrc = computed(() => {
    const path = props.layout?.watermark_image_path;
    if (!path) return null;
    if (path.startsWith('data:') || path.startsWith('http') || path.startsWith('/')) return path;
    return `/storage/${path}`;
});

const showTextWatermark = computed(() => {
    return props.layout?.watermark_type === 'text' && watermarkLines.value.length > 0;
});

const showImageWatermark = computed(() => {
    return props.layout?.watermark_type === 'image' && !!watermarkImageSrc.value;
});

const showSectionNote = computed(() => props.layout?.show_note !== false);

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

const legacySections = computed(() => {
    const raw = props.sections?.length ? props.sections : groupQuestionsByType(props.questions);
    return raw.map((section, idx) => ({
        ...section,
        number: section.number ?? idx + 1,
        question_count: section.question_count ?? section.questions?.length ?? 0,
    }));
});

const content = computed(() => {
    const previewPayload = {
        sections: legacySections.value,
        layout: props.layout,
        institution: props.institution,
        exam_meta: props.examMeta,
        paper: { title: props.title },
    };

    let base = props.paperContent?.sections
        ? clonePaperContent(props.paperContent)
        : buildPaperContentFromPreview(previewPayload, props.title);

    if (props.dualMedium) {
        base = hydratePaperContentUrdu(base, previewPayload);
    }

    return base;
});

const displaySections = computed(() => content.value.sections ?? []);

const header = computed(() => content.value.header ?? {});

const patchContent = (mutator) => {
    const next = clonePaperContent(content.value);
    mutator(next);
    emit('update:paperContent', next);
};

const onEditableBlur = (event, mutator) => {
    if (!props.editable) return;
    const text = (event.target.innerText ?? '').trim();
    patchContent((draft) => mutator(draft, text));
};

const meta = computed(() => ({
    class: header.value.class || props.examMeta?.class || '',
    subject: header.value.subject || props.examMeta?.subject || '',
    time: header.value.paper_time || props.examMeta?.time || '',
    marks: header.value.marks || props.examMeta?.marks || '',
    paperType: header.value.paper_type || props.title || '',
}));

const instituteName = computed(() => header.value.institute_name || props.institution?.name || 'Institution Name');
const instituteAddress = computed(() => header.value.institute_address || institutionAddressFromInst(props.institution));

function institutionAddressFromInst(inst) {
    if (!inst) return '';
    const address = (inst.address || '').trim();
    const city = (inst.city || '').trim();
    let location = address;
    if (city && !address.toLowerCase().includes(city.toLowerCase())) {
        location = location ? `${location}, ${city}` : city;
    }
    const phone = inst.phone ? ` Ph:${inst.phone}` : '';
    return (location || city) + phone;
}

const mcqOptionCells = (q) => {
    if (q.options) {
        return ['A', 'B', 'C', 'D'].map((key) => ({
            key,
            en: q.options[key]?.en ?? '',
            ur: q.options[key]?.ur ?? '',
        }));
    }
    const o = getMcqOptions(q);
    if (!o) return [];
    return [
        { key: 'A', en: o.option_a_en, ur: o.option_a_ur },
        { key: 'B', en: o.option_b_en, ur: o.option_b_ur },
        { key: 'C', en: o.option_c_en, ur: o.option_c_ur },
        { key: 'D', en: o.option_d_en, ur: o.option_d_ur },
    ];
};

const pastPaperRefLegacy = (q) => {
    const tag = getPastPaperTag(q);
    if (!props.layout?.show_past_paper_tags || q.source !== 'past_paper' || !tag) return '';
    return `[${tag.board_name} ${tag.year}]`;
};

const omrColumnCount = computed(() => {
    const n = Number(props.layout?.omr_columns ?? 2);
    return Math.min(5, Math.max(1, n));
});

const omrColumnChunks = computed(() => {
    const rows = props.omrRows ?? [];
    if (!rows.length) return [];

    const cols = omrColumnCount.value;
    const perCol = Math.ceil(rows.length / cols);

    return Array.from({ length: cols }, (_, i) =>
        rows.slice(i * perCol, (i + 1) * perCol),
    ).filter((chunk) => chunk.length > 0);
});
</script>

<template>
    <div
        class="paper-preview"
        :class="{
            'paper-preview--tpl1': isTemplate1,
            'paper-preview--editing': editable && isTemplate1,
            'paper-preview--fill': fillWidth,
        }"
        :style="{
            fontFamily: layout?.font_family || 'Arial',
            fontSize: (layout?.font_size || 11) + 'pt',
            fontWeight: layout?.font_weight === 'bold' ? '700' : '400',
            color: layout?.font_color || '#000',
            lineHeight: layout?.line_height || 1.5,
            '--heading-font-size': (layout?.heading_font_size || 12) + 'pt',
            transform: layout?.scale ? `scale(${layout.scale / 100})` : undefined,
            transformOrigin: layout?.scale ? 'top left' : undefined,
            '--print-margin-top': (layout?.margins?.top ?? 15) + 'mm',
            '--print-margin-right': (layout?.margins?.right ?? 15) + 'mm',
            '--print-margin-bottom': (layout?.margins?.bottom ?? 15) + 'mm',
            '--print-margin-left': (layout?.margins?.left ?? 15) + 'mm',
            '--paper-padding-top': (layout?.margins?.top ?? 12) + 'mm',
            '--paper-padding-right': (layout?.margins?.right ?? 10) + 'mm',
            '--paper-padding-bottom': (layout?.margins?.bottom ?? 12) + 'mm',
            '--paper-padding-left': (layout?.margins?.left ?? 10) + 'mm',
            '--wm-opacity': layout?.watermark_opacity ?? 0.18,
            '--wm-angle': (layout?.watermark_angle ?? 45) + 'deg',
            '--wm-font-size': (layout?.watermark_size ?? 22) + 'pt',
            '--wm-image-size': (layout?.watermark_image_size ?? 50) + '%',
        }"
    >
        <header class="paper-header" :class="{ 'mb-6 border-b pb-4': !isTemplate1 }">
            <template v-if="isTemplate1">
                <div class="tpl1-header-dashed" :class="{ 'tpl1-zone--edit': editable }">
                    <h1
                        class="tpl1-institute-name"
                        :contenteditable="editable"
                        suppresscontenteditablewarning
                        @blur="onEditableBlur($event, (d, t) => { d.header.institute_name = t; })"
                    >
                        {{ instituteName }}
                    </h1>
                    <p
                        v-if="instituteAddress || editable"
                        class="tpl1-institute-address"
                        :contenteditable="editable"
                        suppresscontenteditablewarning
                        @blur="onEditableBlur($event, (d, t) => { d.header.institute_address = t; })"
                    >
                        {{ instituteAddress }}
                    </p>
                </div>
                <table class="tpl1-info-table" :class="{ 'tpl1-zone--edit': editable }">
                    <tbody>
                        <tr>
                            <td class="tpl1-info-cell">
                                <div class="tpl1-info-row"><span>Student Name:</span><span class="tpl1-blank" /></div>
                                <div class="tpl1-info-row">
                                    <span>Paper Type:</span>
                                    <span
                                        :contenteditable="editable"
                                        suppresscontenteditablewarning
                                        @blur="onEditableBlur($event, (d, t) => { d.header.paper_type = t; })"
                                    >{{ meta.paperType }}</span>
                                </div>
                                <div class="tpl1-info-row">
                                    <span>Paper Time:</span>
                                    <span
                                        :contenteditable="editable"
                                        suppresscontenteditablewarning
                                        @blur="onEditableBlur($event, (d, t) => { d.header.paper_time = t; })"
                                    >{{ meta.time }}</span>
                                </div>
                            </td>
                            <td class="tpl1-logo-cell">
                                <img v-if="logoSrc" :src="logoSrc" alt="Logo" class="tpl1-logo" />
                            </td>
                            <td class="tpl1-info-cell tpl1-info-cell--right">
                                <div class="tpl1-info-row">
                                    <span>Class:</span>
                                    <span
                                        :contenteditable="editable"
                                        suppresscontenteditablewarning
                                        @blur="onEditableBlur($event, (d, t) => { d.header.class = t; })"
                                    >{{ meta.class }}</span>
                                </div>
                                <div class="tpl1-info-row">
                                    <span>Subject:</span>
                                    <span
                                        :contenteditable="editable"
                                        suppresscontenteditablewarning
                                        @blur="onEditableBlur($event, (d, t) => { d.header.subject = t; })"
                                    >{{ meta.subject }}</span>
                                </div>
                                <div class="tpl1-info-row">
                                    <span>Maximum Marks:</span>
                                    <span
                                        :contenteditable="editable"
                                        suppresscontenteditablewarning
                                        @blur="onEditableBlur($event, (d, t) => { d.header.marks = t; })"
                                    >{{ meta.marks }}</span>
                                </div>
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

            <div
                v-if="showTextWatermark"
                class="watermark-below-header watermark-below-header--text"
                aria-hidden="true"
            >
                <div v-for="(line, i) in watermarkLines" :key="i" class="watermark-line">{{ line }}</div>
            </div>
            <div
                v-if="showImageWatermark"
                class="watermark-below-header watermark-below-header--image"
                aria-hidden="true"
            >
                <img :src="watermarkImageSrc" alt="" class="watermark-image" />
            </div>
        </header>

        <!-- Template 1 body -->
        <template v-if="isTemplate1 && displaySections.length">
            <div class="tpl1-body" :class="{ 'tpl1-body--edit': editable }">
                <section v-for="section in displaySections" :key="section.type" class="tpl1-section">
                    <div class="tpl1-section-heading">
                        <div class="tpl1-section-heading-en">
                            <strong>Q{{ section.number }}.</strong>
                            <span
                                :contenteditable="editable"
                                suppresscontenteditablewarning
                                @blur="onEditableBlur($event, (d, t) => {
                                    const s = d.sections.find((x) => x.type === section.type);
                                    if (s) s.heading_en = t.replace(/^Q\d+\.\s*/i, '').trim();
                                })"
                            >{{ section.heading_en }}</span>
                            <span v-if="section.note && showSectionNote" class="tpl1-section-note">{{ section.note }}</span>
                            <span class="tpl1-section-marks">{{ section.marks }}</span>
                        </div>
                        <div
                            v-if="dualMedium"
                            class="tpl1-section-heading-ur question-ur"
                            :contenteditable="editable"
                            suppresscontenteditablewarning
                            @blur="onEditableBlur($event, (d, t) => {
                                const s = d.sections.find((x) => x.type === section.type);
                                if (s) s.heading_ur = t;
                            })"
                        >
                            {{ section.heading_ur }}
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
                                <span class="tpl1-q-num">{{ q.roman ?? toRoman(idx) }}.</span>
                                <span
                                    :contenteditable="editable"
                                    suppresscontenteditablewarning
                                    @blur="onEditableBlur($event, (d, t) => {
                                        const s = d.sections.find((x) => x.type === section.type);
                                        const question = s?.questions.find((x) => x.id === q.id);
                                        if (question) question.text_en = t;
                                    })"
                                >{{ q.text_en }}</span>
                                <p
                                    v-if="q.past_ref"
                                    class="tpl1-past-ref"
                                    :contenteditable="editable && !!q.past_ref"
                                    suppresscontenteditablewarning
                                    @blur="onEditableBlur($event, (d, t) => {
                                        const s = d.sections.find((x) => x.type === section.type);
                                        const question = s?.questions.find((x) => x.id === q.id);
                                        if (question) question.past_ref = t;
                                    })"
                                >
                                    {{ q.past_ref || pastPaperRefLegacy(q) }}
                                </p>
                            </div>
                            <div
                                v-if="dualMedium"
                                class="tpl1-question-ur question-ur"
                            >
                                <span
                                    :contenteditable="editable"
                                    suppresscontenteditablewarning
                                    @blur="onEditableBlur($event, (d, t) => {
                                        const s = d.sections.find((x) => x.type === section.type);
                                        const question = s?.questions.find((x) => x.id === q.id);
                                        if (question) question.text_ur = t.replace(/\s*[ivxlcdm]+\.\s*$/i, '').trim();
                                    })"
                                >{{ q.text_ur }}</span>
                                <span class="tpl1-q-num tpl1-q-num--ur">{{ q.roman ?? toRoman(idx) }}.</span>
                            </div>
                        </div>

                        <div v-if="q.type === 'mcq' && mcqOptionCells(q).length" class="tpl1-mcq-options">
                            <div v-for="opt in mcqOptionCells(q)" :key="opt.key" class="tpl1-mcq-cell">
                                <span class="tpl1-mcq-left">
                                    <span class="tpl1-mcq-key">({{ opt.key }})</span>
                                    <span
                                        :contenteditable="editable"
                                        suppresscontenteditablewarning
                                        @blur="onEditableBlur($event, (d, t) => {
                                            const s = d.sections.find((x) => x.type === section.type);
                                            const question = s?.questions.find((x) => x.id === q.id);
                                            if (question?.options?.[opt.key]) question.options[opt.key].en = t;
                                        })"
                                    >{{ opt.en }}</span>
                                </span>
                                <span
                                    v-if="dualMedium"
                                    class="question-ur tpl1-mcq-ur"
                                    :contenteditable="editable"
                                    suppresscontenteditablewarning
                                    @blur="onEditableBlur($event, (d, t) => {
                                        const s = d.sections.find((x) => x.type === section.type);
                                        const question = s?.questions.find((x) => x.id === q.id);
                                        if (question?.options?.[opt.key]) question.options[opt.key].ur = t;
                                    })"
                                >{{ opt.ur }}</span>
                            </div>
                        </div>

                        <div v-else-if="q.type === 'long' && q.parts?.length" class="tpl1-parts">
                            <div v-for="(p, pIdx) in q.parts" :key="p.id ?? pIdx" class="tpl1-question-row tpl1-part-row">
                                <div class="tpl1-question-en">
                                    <span class="tpl1-q-num">({{ p.label ?? String.fromCharCode(97 + pIdx) }})</span>
                                    <span
                                        :contenteditable="editable"
                                        suppresscontenteditablewarning
                                        @blur="onEditableBlur($event, (d, t) => {
                                            const s = d.sections.find((x) => x.type === section.type);
                                            const question = s?.questions.find((x) => x.id === q.id);
                                            const part = question?.parts?.[pIdx];
                                            if (part) part.text_en = t;
                                        })"
                                    >{{ p.text_en }}</span>
                                </div>
                                <div
                                    v-if="dualMedium"
                                    class="tpl1-question-ur question-ur"
                                    :contenteditable="editable"
                                    suppresscontenteditablewarning
                                    @blur="onEditableBlur($event, (d, t) => {
                                        const s = d.sections.find((x) => x.type === section.type);
                                        const question = s?.questions.find((x) => x.id === q.id);
                                        const part = question?.parts?.[pIdx];
                                        if (part) part.text_ur = t;
                                    })"
                                >
                                    {{ p.text_ur }}
                                </div>
                            </div>
                        </div>

                        <div v-else-if="q.type === 'long' && getParts(q).length" class="tpl1-parts">
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
            </div>
        </template>

        <!-- Legacy body -->
        <template v-else-if="displaySections.length">
            <div
                v-for="section in displaySections"
                :key="section.type"
                class="mb-6"
                :class="layout?.dual_column ? 'questions-container dual-col' : ''"
            >
                <h3 class="mb-3 font-bold">{{ section.title || section.heading_en }}</h3>
                <div v-for="(q, idx) in section.questions" :key="q.id" class="question-block mb-4">
                    <p>
                        <strong>Q{{ idx + 1 }}.</strong> {{ q.text_en }}
                    </p>
                    <p v-if="dualMedium && q.text_ur" class="question-ur">{{ q.text_ur }}</p>
                    <div v-if="q.type === 'mcq' && mcqOptionCells(q).length" class="ms-6 mt-1 grid grid-cols-2 gap-1">
                        <span v-for="opt in mcqOptionCells(q)" :key="opt.key">{{ opt.key }}) {{ opt.en }}</span>
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
            <div
                class="omr-sheet-grid"
                :class="`omr-sheet-grid--cols-${omrColumnCount}`"
            >
                <div
                    v-for="(column, colIdx) in omrColumnChunks"
                    :key="colIdx"
                    class="omr-sheet-col"
                >
                    <div v-for="row in column" :key="row.number" class="omr-sheet-row">
                        <span class="omr-sheet-num">{{ row.number }}.</span>
                        <span class="omr-sheet-bubbles">
                            <span
                                v-for="opt in row.options"
                                :key="opt"
                                class="omr-bubble"
                            >{{ opt }}</span>
                        </span>
                    </div>
                </div>
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
    padding: var(--paper-padding-top, 12mm) var(--paper-padding-right, 10mm)
        var(--paper-padding-bottom, 12mm) var(--paper-padding-left, 10mm);
    width: 100%;
    max-width: 210mm;
    margin-left: auto;
    margin-right: auto;
    box-sizing: border-box;
}

.paper-preview--fill.paper-preview--tpl1,
.paper-preview--fill {
    max-width: none;
    width: 100%;
    margin-left: 0;
    margin-right: 0;
}

.paper-preview--editing [contenteditable='true'] {
    outline: 1px dashed #3b82f6;
    outline-offset: 2px;
    cursor: text;
    min-width: 0.5rem;
}

.paper-preview--editing [contenteditable='true']:focus {
    outline-color: #16a34a;
    background: rgb(240 253 244 / 0.5);
}

.watermark-line {
    font-size: var(--wm-font-size, 1.35rem);
    font-weight: 700;
    letter-spacing: 0.06em;
    color: #9ca3af;
    text-align: center;
    white-space: nowrap;
    line-height: 1.2;
}

.watermark-below-header {
    position: absolute;
    left: 50%;
    top: 100%;
    z-index: 20;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    gap: 0.35rem;
    transform: translateX(-50%) rotate(var(--wm-angle, 45deg));
    transform-origin: center top;
    opacity: var(--wm-opacity, 0.18);
    pointer-events: none;
    user-select: none;
}

.watermark-below-header--text {
    margin-top: 1.75rem;
}

.watermark-below-header--image {
    margin-top: 3.5rem;
}

.watermark-image {
    display: block;
    max-width: var(--wm-image-size, 50%);
    max-height: var(--wm-image-size, 50%);
    width: auto;
    height: auto;
    object-fit: contain;
    pointer-events: none;
    user-select: none;
}

.paper-header {
    position: relative;
    z-index: 1;
    overflow: visible;
}

.tpl1-section,
.omr-sheet,
.section-break {
    position: relative;
    z-index: 1;
}

.tpl1-header-dashed {
    border: 2px dashed #000;
    padding: 10px 12px;
    text-align: center;
    margin-bottom: 0;
}

.tpl1-zone--edit {
    border-color: #000;
}

.tpl1-institute-name {
    font-size: var(--heading-font-size, 1.25rem);
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

.tpl1-body--edit {
    border: 2px dashed #16a34a;
    padding: 10px 8px;
    margin-top: 10px;
}

.tpl1-section {
    margin-top: 14px;
    padding-top: 8px;
    border-top: 1px solid #000;
}

.tpl1-body--edit .tpl1-section:first-of-type {
    border-top: none;
    margin-top: 0;
    padding-top: 0;
}

.tpl1-section:first-of-type:not(.tpl1-body--edit *) {
    border-top: none;
    margin-top: 10px;
    padding-top: 0;
}

.tpl1-body .tpl1-section:first-of-type {
    border-top: none;
    margin-top: 0;
    padding-top: 0;
}

.tpl1-section-heading {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 10px;
    font-weight: 700;
    font-size: var(--heading-font-size, 1rem);
}

.tpl1-section-heading-en {
    flex: 1;
}

.tpl1-section-heading-ur {
    flex-shrink: 0;
    width: 45%;
    min-width: 8rem;
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
    margin-bottom: 8px;
    padding-bottom: 6px;
}

.tpl1-question-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
}

.tpl1-question-en {
    flex: 1;
    min-width: 0;
}

.tpl1-question-ur {
    flex-shrink: 0;
    width: 45%;
    min-width: 8rem;
    text-align: right;
    display: flex;
    align-items: flex-start;
    gap: 6px;
    justify-content: flex-end;
}

.tpl1-q-num {
    font-weight: 700;
    margin-right: 4px;
    white-space: nowrap;
}

.tpl1-q-num--ur {
    margin-right: 0;
    margin-left: 4px;
    flex-shrink: 0;
}

.tpl1-past-ref {
    margin: 2px 0 0 1.2rem;
    font-size: 0.72rem;
    color: #374151;
}

.tpl1-mcq-options {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    border: 1px solid #000;
    margin-top: 6px;
}

.tpl1-mcq-cell {
    border-right: 1px solid #000;
    padding: 5px 6px;
    font-size: 0.78rem;
    min-height: 2.1rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 6px;
}

.tpl1-mcq-cell:last-child {
    border-right: none;
}

.tpl1-mcq-left {
    display: flex;
    align-items: center;
    gap: 4px;
    flex: 1;
    min-width: 0;
}

.tpl1-mcq-key {
    font-weight: 700;
    flex-shrink: 0;
}

.tpl1-mcq-ur {
    flex-shrink: 0;
    max-width: 48%;
    font-size: 0.72rem;
    text-align: right;
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

.omr-sheet-grid {
    display: grid;
    gap: 1rem 1.5rem;
    align-items: start;
}

.omr-sheet-grid--cols-1 {
    grid-template-columns: 1fr;
}

.omr-sheet-grid--cols-2 {
    grid-template-columns: repeat(2, 1fr);
}

.omr-sheet-grid--cols-3 {
    grid-template-columns: repeat(3, 1fr);
}

.omr-sheet-grid--cols-4 {
    grid-template-columns: repeat(4, 1fr);
}

.omr-sheet-grid--cols-5 {
    grid-template-columns: repeat(5, 1fr);
}

.omr-sheet-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.35rem;
    font-size: 0.8rem;
}

.omr-sheet-num {
    flex-shrink: 0;
    width: 1.75rem;
    text-align: right;
    font-weight: 600;
}

.omr-sheet-bubbles {
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.omr-bubble {
    display: inline-flex;
    height: 1.35rem;
    width: 1.35rem;
    align-items: center;
    justify-content: center;
    border-radius: 9999px;
    border: 1px solid #1f2937;
    font-size: 0.65rem;
    font-weight: 600;
}
</style>
