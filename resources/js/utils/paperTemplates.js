export const PAPER_TEMPLATES = [
    { id: 1, name: 'Template 1 — School Classic', description: 'Original school header with a central logo and student details.' },
    { id: 'board', name: 'Board Practice', description: 'Formal board practice paper with group, code and session details.' },
    { id: 'federal', name: 'Federal / SLO Practice', description: 'Versioned paper with signatures and response bubbles beside MCQs.' },
    { id: 'academy', name: 'Academy Test Series', description: 'Branded chapter tests with campus, batch and syllabus coverage.' },
    { id: 'urdu', name: 'Urdu / Dual Medium', description: 'Urdu student details and bilingual questions. Enable dual medium for other layouts too.' },
    { id: 'entry', name: 'Entry Test Practice', description: 'Compact two-column questions with booklet version and candidate details.' },
];

export function normalizePaperTemplate(value) {
    return PAPER_TEMPLATES.find(template => String(template.id) === String(value))?.id ?? 1;
}

export const TEMPLATE_FIELDS = [
    { key: 'board', label: 'Board / affiliation', templates: ['board', 'federal'] },
    { key: 'session', label: 'Exam session / year', templates: ['board', 'federal', 'urdu'] },
    { key: 'group', label: 'Group', templates: ['board'] },
    { key: 'paper_code', label: 'Paper code', templates: ['board', 'federal', 'entry'] },
    { key: 'version', label: 'Paper / booklet version', templates: ['federal', 'entry'] },
    { key: 'campus', label: 'Campus', templates: ['academy'] },
    { key: 'batch', label: 'Batch', templates: ['academy'] },
    { key: 'test_number', label: 'Test number', templates: ['academy'] },
    { key: 'coverage', label: 'Chapters / syllabus coverage', templates: ['academy'] },
    { key: 'date', label: 'Exam date', templates: ['academy', 'urdu', 'entry'] },
];
