export function applySectionOptions(current, options = {}) {
    const next = { ...current };
    const fields = { marks: 'marks', blankLines: 'blank_lines', choiceQuestions: 'choice_questions', questionsPerLine: 'questions_per_line', tabularMcqs: 'tabular_mcqs', showParts: 'show_parts' };
    for (const [source, target] of Object.entries(fields)) {
        if (options[source] !== undefined) next[target] = options[source];
    }
    return next;
}

export function sectionTotalMarks(count, settings = {}) {
    const attempt = Math.max(0, Number(settings.choice_questions) || 0);
    const attempted = attempt > 0 ? Math.min(count, attempt) : count;
    return attempted * Math.max(1, Number(settings.marks) || 1);
}
