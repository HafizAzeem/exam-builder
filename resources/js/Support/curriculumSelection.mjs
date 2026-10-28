const same = (a, b) => String(a ?? '') === String(b ?? '');
export function selectionForChapter(chapters, chapterId) {
    const chapter = chapters.find(c => same(c.id, chapterId));
    return {
        grade_id: chapter?.subject?.grade_id ?? chapter?.subject?.grade?.id ?? '',
        subject_id: chapter?.subject_id ?? chapter?.subject?.id ?? '',
        chapter_id: chapter?.id ?? '',
    };
}
export const copySelection = row => ({ grade_id: row?.grade_id ?? '', subject_id: row?.subject_id ?? '', chapter_id: row?.chapter_id ?? '' });
export const changeGrade = (row, gradeId) => same(row.grade_id, gradeId) ? copySelection(row) : ({ grade_id: gradeId, subject_id: '', chapter_id: '' });
export const changeSubject = (row, subjectId) => same(row.subject_id, subjectId) ? copySelection(row) : ({ grade_id: row.grade_id, subject_id: subjectId, chapter_id: '' });
export function gradeOptions(chapters) {
    const grades = new Map();
    for (const chapter of chapters) {
        const grade = chapter.subject?.grade;
        if (grade) grades.set(grade.id, { value: grade.id, label: grade.label_en || grade.label_ur || `Class ${grade.number}`, number: grade.number });
    }
    return [...grades.values()].sort((a, b) => a.number - b.number);
}
export function subjectOptions(chapters, gradeId) {
    if (!gradeId) return [];
    const subjects = new Map();
    for (const chapter of chapters) {
        const subject = chapter.subject;
        if (subject && same(subject.grade_id ?? subject.grade?.id, gradeId)) {
            subjects.set(subject.id, { value: subject.id, label: subject.name_en || subject.name_ur || `Subject ${subject.id}` });
        }
    }
    return [...subjects.values()].sort((a, b) => a.label.localeCompare(b.label));
}
export function chapterOptions(chapters, subjectId) {
    if (!subjectId) return [];
    return chapters.filter(c => same(c.subject_id ?? c.subject?.id, subjectId))
        .sort((a, b) => a.number - b.number)
        .map(c => ({ value: c.id, label: `${c.number ?? ''}. ${c.title_en || c.title_ur || 'Chapter'}` }));
}
