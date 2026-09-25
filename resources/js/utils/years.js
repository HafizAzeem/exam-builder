/**
 * Exam year options for past-paper forms (newest first).
 *
 * @param {number} [fromYear=1990]
 * @param {number} [ahead=1] years beyond the current calendar year
 * @returns {number[]}
 */
export function examYearOptions(fromYear = 1990, ahead = 1) {
    const end = new Date().getFullYear() + ahead;
    const start = Math.min(fromYear, end);
    const years = [];
    for (let y = end; y >= start; y--) {
        years.push(y);
    }
    return years;
}
