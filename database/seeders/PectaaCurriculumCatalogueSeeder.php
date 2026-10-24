<?php

namespace Database\Seeders;

use App\Models\Grade;
use App\Models\Subject;
use App\Models\SyllabusEdition;
use App\Models\SyllabusChapter;
use Database\Seeders\Concerns\SeedsSubjectChapters;
use Illuminate\Database\Seeder;

/**
 * Persist PECTAA catalogue offerings as an inventory, not as syllabus
 * verification. The catalogue page identifies available books, but does not
 * prove a book's applicability to an examination session or provide its TOC.
 */
class PectaaCurriculumCatalogueSeeder extends Seeder
{
    use SeedsSubjectChapters;

    private const CATALOGUE_URL = 'https://pectaa.edu.pk/curriculum-compliance/';

    /** @var array<int, list<array{subject:string, group:string, edition:string}>> */
    private const OFFERINGS = [
        1 => [
            ['subject'=>'Tajveedi Qaida','group'=>'Religious education','edition'=>'Tajveedi Qaida (Muslim Students)'],
            ['subject'=>'General Knowledge','group'=>'Curriculum I-III','edition'=>'General Knowledge I-III'],
            ['subject'=>'Urdu','group'=>'Compulsory','edition'=>'Urdu 1'], ['subject'=>'English','group'=>'Compulsory','edition'=>'English 1'],
            ['subject'=>'Mathematics','group'=>'Compulsory / EM','edition'=>'Mathematics 1 (EM)'], ['subject'=>'Waqfiyat e Aama','group'=>'Compulsory / UM','edition'=>'Waqfiyat e Aama 1 (UM)'],
            ['subject'=>'Islamiat','group'=>'Religious education / UM','edition'=>'Islamiat 1 (UM)'], ['subject'=>'Akhlaqiat','group'=>'Religious minorities','edition'=>'Akhlaqiat 1'],
            ['subject'=>'Buddhism','group'=>'Religious minorities','edition'=>'Buddhism 1'], ['subject'=>'Sikhism','group'=>'Religious minorities','edition'=>'Sikhism 1'],
            ['subject'=>'Sanatan Dharam','group'=>'Religious minorities','edition'=>'Sanatan Dharam 1'], ['subject'=>'Zoroastrian Religion','group'=>'Religious minorities','edition'=>'Zoroastrian Religion 1'], ['subject'=>'Mashi Taleem','group'=>'Religious education','edition'=>'Mashi Taleem 1'],
        ],
        2 => [
            ['subject'=>'Nazra Quran','group'=>'Religious education','edition'=>'Nazra Quran 2'], ['subject'=>'General Knowledge','group'=>'Curriculum I-III','edition'=>'General Knowledge I-III'], ['subject'=>'Urdu','group'=>'Compulsory','edition'=>'Urdu 2'], ['subject'=>'English','group'=>'Compulsory','edition'=>'English 2'],
            ['subject'=>'Mathematics','group'=>'Compulsory / EM','edition'=>'Mathematics 2 (EM)'], ['subject'=>'Waqfiyat e Aama','group'=>'Compulsory / UM','edition'=>'Waqfiyat e Aama 2 (UM)'], ['subject'=>'Islamiat','group'=>'Religious education / UM','edition'=>'Islamiat 2 (UM)'],
            ['subject'=>'Akhlaqiat','group'=>'Religious minorities','edition'=>'Akhlaqiat 2'], ['subject'=>'Buddhism','group'=>'Religious minorities','edition'=>'Buddhism 2'], ['subject'=>'Sikhism','group'=>'Religious minorities','edition'=>'Sikhism 2'], ['subject'=>'Sanatan Dharam','group'=>'Religious minorities','edition'=>'Sanatan Dharam 2'], ['subject'=>'Zoroastrian Religion','group'=>'Religious minorities','edition'=>'Zoroastrian Religion 2'], ['subject'=>'Mashi Taleem','group'=>'Religious education','edition'=>'Mashi Taleem 2'],
        ],
        3 => [
            ['subject'=>'Nazra Quran','group'=>'Religious education','edition'=>'Nazra Quran 3'], ['subject'=>'General Knowledge','group'=>'Curriculum I-III','edition'=>'General Knowledge I-III'], ['subject'=>'Urdu','group'=>'Compulsory','edition'=>'Urdu 3'], ['subject'=>'English','group'=>'Compulsory','edition'=>'English 3'], ['subject'=>'Mathematics','group'=>'Compulsory','edition'=>'Mathematics 3'], ['subject'=>'Waqfiyat e Aama','group'=>'Compulsory / UM','edition'=>'Waqfiyat e Aama 3 (UM)'], ['subject'=>'Islamiat','group'=>'Religious education / UM','edition'=>'Islamiat 3 (UM)'], ['subject'=>'Akhlaqiat','group'=>'Religious minorities','edition'=>'Akhlaqiat 3'], ['subject'=>'Buddhism','group'=>'Religious minorities','edition'=>'Buddhism 3'], ['subject'=>'Sikhism','group'=>'Religious minorities','edition'=>'Sikhism 3'], ['subject'=>'Sanatan Dharam','group'=>'Religious minorities','edition'=>'Sanatan Dharam 3'], ['subject'=>'Zoroastrian Religion','group'=>'Religious minorities','edition'=>'Zoroastrian Religion 3'], ['subject'=>'Mashi Taleem','group'=>'Religious education','edition'=>'Mashi Taleem 3'],
        ],
        4 => [
            ['subject'=>'Nazra Quran','group'=>'Religious education','edition'=>'Nazra Quran 4'], ['subject'=>'Urdu','group'=>'Compulsory','edition'=>'Urdu 4'], ['subject'=>'English','group'=>'Compulsory','edition'=>'English 4'], ['subject'=>'Mathematics','group'=>'Compulsory / EM','edition'=>'Mathematics 4 (EM)'], ['subject'=>'General Science','group'=>'Compulsory / EM','edition'=>'General Science 4 (EM)'], ['subject'=>'Islamiat','group'=>'Religious education / UM','edition'=>'Islamiat 4 (UM)'], ['subject'=>'Social Studies','group'=>'Compulsory / UM','edition'=>'Muasharti Uloom 4 (UM)'], ['subject'=>'Akhlaqiat','group'=>'Religious minorities','edition'=>'Akhlaqiat 4'], ['subject'=>'Zoroastrian Religion','group'=>'Religious minorities','edition'=>'Zoroastrian Religion 4'], ['subject'=>'Mashi Taleem','group'=>'Religious education','edition'=>'Mashi Taleem 4'],
        ],
        5 => [
            ['subject'=>'Nazra Quran','group'=>'Religious education','edition'=>'Nazra Quran 5'], ['subject'=>'Urdu','group'=>'Compulsory','edition'=>'Urdu 5'], ['subject'=>'English','group'=>'Compulsory','edition'=>'English 5'], ['subject'=>'Mathematics','group'=>'Compulsory / EM','edition'=>'Mathematics 5 (EM)'], ['subject'=>'General Science','group'=>'Compulsory / EM','edition'=>'General Science 5 (EM)'], ['subject'=>'Islamiat','group'=>'Religious education / UM','edition'=>'Islamiat 5 (UM)'], ['subject'=>'Social Studies','group'=>'Compulsory / UM','edition'=>'Muasharti Uloom 5 (UM)'], ['subject'=>'Akhlaqiat','group'=>'Religious minorities','edition'=>'Akhlaqiat 5'], ['subject'=>'Zoroastrian Religion','group'=>'Religious minorities','edition'=>'Zoroastrian Religion 5'], ['subject'=>'Mashi Taleem','group'=>'Religious education','edition'=>'Mashi Taleem 5'],
        ],
        6 => self::MIDDLE, 7 => self::MIDDLE, 8 => self::MIDDLE,
        9 => self::SECONDARY_NINE, 10 => self::SECONDARY_TEN,
        11 => self::HIGHER_SECONDARY_ELEVEN, 12 => self::HIGHER_SECONDARY_TWELVE,
    ];

    private const MIDDLE = [
        ['subject'=>'Tarjuma-tul-Quran','group'=>'Religious education','edition'=>'Tarjuma-tul-Quran 6-8'], ['subject'=>'Urdu','group'=>'Compulsory','edition'=>'Urdu 6-8'], ['subject'=>'English','group'=>'Compulsory','edition'=>'English 6-8'], ['subject'=>'Islamiat','group'=>'Religious education','edition'=>'Islamiat 6-8'], ['subject'=>'Geography','group'=>'Compulsory / EM and UM','edition'=>'Geography 6-8'], ['subject'=>'History','group'=>'Compulsory / EM and UM','edition'=>'History 6-8'], ['subject'=>'Akhlaqiat','group'=>'Religious minorities','edition'=>'Akhlaqiat 6-8'], ['subject'=>'Arabic','group'=>'Compulsory / optional by class','edition'=>'Arabic 6-8'], ['subject'=>'Mathematics','group'=>'Compulsory','edition'=>'Mathematics 6-8'], ['subject'=>'General Science','group'=>'Compulsory','edition'=>'General Science 6-8'], ['subject'=>'Computer Science','group'=>'Compulsory','edition'=>'Computer Science 6-8'], ['subject'=>'Mashi Taleem','group'=>'Religious education','edition'=>'Mashi Taleem 6-8'], ['subject'=>'Art and Drawing','group'=>'Optional','edition'=>'Art and Drawing 6-8'], ['subject'=>'Zari Taleem','group'=>'Optional','edition'=>'Zari Taleem 6-8'], ['subject'=>'Punjabi','group'=>'Optional','edition'=>'Punjabi 6-8'], ['subject'=>'Farsi','group'=>'Optional','edition'=>'Farsi 6-8'], ['subject'=>'Itlaqi Barqiat','group'=>'Optional / technical','edition'=>'Itlaqi Barqiat 6-8'], ['subject'=>'Home Economics','group'=>'Optional / EM and UM','edition'=>'Home Economics 6-8'],
    ];

    private const SECONDARY_NINE = [
        ['subject'=>'Tarjuma-tul-Quran','group'=>'Religious education','edition'=>'Tarjuma-tul-Quran 9'], ['subject'=>'Islamiat','group'=>'Compulsory','edition'=>'Islamiyat 9 2025-26'], ['subject'=>'Urdu','group'=>'Compulsory','edition'=>'Urdu 9 2025-26'], ['subject'=>'English','group'=>'Compulsory','edition'=>'English 9 2025-26'], ['subject'=>'Mathematics','group'=>'Compulsory / EM and UM','edition'=>'Mathematics 9'], ['subject'=>'Akhlaqiat','group'=>'Religious minorities','edition'=>'Akhlaqiat 9'], ['subject'=>'Christianity','group'=>'Religious minorities','edition'=>'Christianity 9'], ['subject'=>'Physics','group'=>'Science / EM and UM','edition'=>'Physics 9'], ['subject'=>'Chemistry','group'=>'Science / EM and UM','edition'=>'Chemistry 9'], ['subject'=>'Biology','group'=>'Science / EM and UM','edition'=>'Biology 9'], ['subject'=>'Computer Science','group'=>'Science / EM','edition'=>'Computer 9 2025-26'], ['subject'=>'Computer and Entrepreneurship','group'=>'Matric Tech','edition'=>'Computer and Entrepreneurship 9 2026-27'], ['subject'=>'Agriculture Science','group'=>'Matric Tech / EM and UM','edition'=>'Agriculture Science Tech 9'], ['subject'=>'Biology','group'=>'Matric Tech / EM and UM','edition'=>'Biology Tech 9'], ['subject'=>'Chemistry','group'=>'Matric Tech / EM and UM','edition'=>'Chemistry Tech 9'], ['subject'=>'Computer Science','group'=>'Matric Tech / EM and UM','edition'=>'Computer Science Tech 9'], ['subject'=>'Physics','group'=>'Matric Tech / EM and UM','edition'=>'Physics Tech 9'], ['subject'=>'Health Sciences','group'=>'Matric Tech','edition'=>'Health Sciences Tech 9'], ['subject'=>'ICT','group'=>'Matric Tech','edition'=>'ICT Tech 9'], ['subject'=>'Fashion Design','group'=>'Matric Tech','edition'=>'Fashion Design Tech 9'], ['subject'=>'General Science','group'=>'Matric Tech / EM and UM','edition'=>'General Science Tech 9'], ['subject'=>'Communication Skills','group'=>'Matric Tech','edition'=>'Communication Skills Tech 9'], ['subject'=>'Art and Model Drawing','group'=>'Arts elective','edition'=>'Art and Model Drawing 9'], ['subject'=>'Home Economics','group'=>'Arts elective','edition'=>'Home Economics 9'], ['subject'=>'Textile and Clothing','group'=>'Arts elective','edition'=>'Textile and Clothing 9'], ['subject'=>'Food and Nutrition','group'=>'Arts elective','edition'=>'Ghiza aur Ghizayat 9'],
    ];

    private const SECONDARY_TEN = [
        ['subject'=>'Tarjuma-tul-Quran','group'=>'Religious education','edition'=>'Tarjuma-tul-Quran 10'], ['subject'=>'Islamiat','group'=>'Compulsory','edition'=>'Islamiat 10'], ['subject'=>'English','group'=>'Compulsory','edition'=>'English 10 2026-27'], ['subject'=>'Urdu','group'=>'Compulsory','edition'=>'Urdu 10 2026-27'], ['subject'=>'Mathematics','group'=>'Compulsory / EM and UM','edition'=>'Mathematics 10'], ['subject'=>'Pakistan Studies','group'=>'Compulsory / EM and UM','edition'=>'Pakistan Studies 10'], ['subject'=>'Akhlaqiat','group'=>'Religious minorities','edition'=>'Akhlaqiat 10'], ['subject'=>'Biology','group'=>'Science / EM and UM','edition'=>'Biology 10 2026-27'], ['subject'=>'Chemistry','group'=>'Science / EM and UM','edition'=>'Chemistry 10 2026-27'], ['subject'=>'Physics','group'=>'Science / EM and UM','edition'=>'Physics 10 2026-27'], ['subject'=>'Computer Science','group'=>'Science and Matric Tech','edition'=>'Computer Science 10 2026-27'], ['subject'=>'Agriculture Science','group'=>'Matric Tech / EM and UM','edition'=>'Agriculture Science Tech 10'], ['subject'=>'Biology','group'=>'Matric Tech / EM and UM','edition'=>'Biology Tech 10'], ['subject'=>'Chemistry','group'=>'Matric Tech / EM and UM','edition'=>'Chemistry Tech 10'], ['subject'=>'Computer Science','group'=>'Matric Tech / EM and UM','edition'=>'Computer Science Tech 10'], ['subject'=>'Physics','group'=>'Matric Tech / EM and UM','edition'=>'Physics Tech 10'], ['subject'=>'Health Sciences','group'=>'Matric Tech','edition'=>'Health Sciences Tech 10'], ['subject'=>'ICT','group'=>'Matric Tech','edition'=>'ICT Tech 10'], ['subject'=>'Fashion Design','group'=>'Matric Tech','edition'=>'Fashion Design Tech 10'], ['subject'=>'General Science','group'=>'Matric Tech / EM and UM','edition'=>'General Science Tech 10'], ['subject'=>'Communication Skills','group'=>'Matric Tech','edition'=>'Communication Skills Tech 10'], ['subject'=>'Art and Model Drawing','group'=>'Arts elective','edition'=>'Art and Model Drawing 10'], ['subject'=>'Home Economics','group'=>'Arts elective','edition'=>'Home Economics 10'], ['subject'=>'Textile and Clothing','group'=>'Arts elective','edition'=>'Textile and Clothing 10'], ['subject'=>'Food and Nutrition','group'=>'Arts elective','edition'=>'Ghiza aur Ghizayat 10'],
    ];

    private const HIGHER_SECONDARY_ELEVEN = [
        ['subject'=>'Tarjuma-tul-Quran','group'=>'Religious education','edition'=>'Tarjuma-tul-Quran 11'], ['subject'=>'Islamiat','group'=>'Compulsory / UM','edition'=>'Islamiyat 11 2025-26'], ['subject'=>'English','group'=>'Compulsory','edition'=>'English 11 2025-26'], ['subject'=>'Urdu','group'=>'Compulsory','edition'=>'Urdu 11 2025-26'], ['subject'=>'Akhlaqiat','group'=>'Religious minorities','edition'=>'Akhlaqiat 11'], ['subject'=>'Mashi Taleem','group'=>'Religious education','edition'=>'Mashi Taleem 11'], ['subject'=>'Physics','group'=>'Science','edition'=>'Physics 11 2025-26'], ['subject'=>'Mathematics','group'=>'Science','edition'=>'Mathematics 11 2025-26'], ['subject'=>'Biology','group'=>'Science','edition'=>'Biology 11 2025-26'], ['subject'=>'Chemistry','group'=>'Science','edition'=>'Chemistry 11 2025-26'], ['subject'=>'Computer Science','group'=>'Science','edition'=>'Computer 11 2025-26'], ['subject'=>'Economics','group'=>'Science and Arts elective','edition'=>'Economics 11'], ['subject'=>'Statistics','group'=>'Science','edition'=>'Statistics 11'], ['subject'=>'Civics','group'=>'Arts elective','edition'=>'Civics 11'], ['subject'=>'Punjabi','group'=>'Arts elective','edition'=>'Punjabi Ikhtiari 11'], ['subject'=>'Education','group'=>'Arts elective','edition'=>'Ilm-ul-Taleem 11'], ['subject'=>'Farsi','group'=>'Arts elective','edition'=>'Farsi 11'], ['subject'=>'Psychology','group'=>'Arts elective','edition'=>'Psychology 11'], ['subject'=>'Geography','group'=>'Arts elective','edition'=>'Physical Geography 11'], ['subject'=>'Saraiki','group'=>'Arts elective','edition'=>'Saraiki 11'], ['subject'=>'Philosophy','group'=>'Arts elective','edition'=>'Mubadiyat Falsfa 11'], ['subject'=>'Arabic','group'=>'Arts elective','edition'=>'Hadiqat-ul-Adab 11'],
    ];

    private const HIGHER_SECONDARY_TWELVE = [
        ['subject'=>'Tarjuma-tul-Quran','group'=>'Religious education','edition'=>'Tarjuma-tul-Quran 12'], ['subject'=>'Urdu','group'=>'Compulsory','edition'=>'Urdu 12'], ['subject'=>'Pakistan Studies','group'=>'Compulsory / EM and UM','edition'=>'Pakistan Studies 12'], ['subject'=>'English','group'=>'Compulsory','edition'=>'English 12'], ['subject'=>'Akhlaqiat','group'=>'Religious minorities','edition'=>'Akhlaqiat 12'], ['subject'=>'Chemistry','group'=>'Science','edition'=>'Chemistry 12'], ['subject'=>'Biology','group'=>'Science','edition'=>'Biology 12'], ['subject'=>'Mathematics','group'=>'Science','edition'=>'Mathematics 12'], ['subject'=>'Economics','group'=>'Science and Arts elective','edition'=>'Economics 12'], ['subject'=>'Computer Science','group'=>'Science','edition'=>'Computer 12'], ['subject'=>'Statistics','group'=>'Science','edition'=>'Statistics 12'], ['subject'=>'Physics','group'=>'Science','edition'=>'Physics 12'], ['subject'=>'Civics','group'=>'Arts elective','edition'=>'Civics 12'], ['subject'=>'Farsi','group'=>'Arts elective','edition'=>'Farsi 12'], ['subject'=>'Punjabi','group'=>'Arts elective','edition'=>'Punjabi Ikhtiari 12'], ['subject'=>'Education','group'=>'Arts elective','edition'=>'Ilm-ul-Taleem 12'], ['subject'=>'Psychology','group'=>'Arts elective','edition'=>'Psychology 12'], ['subject'=>'Geography','group'=>'Arts elective','edition'=>'Human Geography 12'], ['subject'=>'Saraiki','group'=>'Arts elective','edition'=>'Saraiki 12'], ['subject'=>'Philosophy','group'=>'Arts elective','edition'=>'Mantaq 12'], ['subject'=>'Arabic','group'=>'Arts elective','edition'=>'Hadiqa-tul-Adab 12'],
    ];

    public function run(): void
    {
        foreach (self::OFFERINGS as $number => $offerings) {
            $grade = Grade::query()->firstOrCreate(
                ['number' => $number],
                ['label_en' => "Class {$number}", 'label_ur' => null, 'is_active' => true]
            );
            if (! $grade->is_active) {
                $grade->update(['is_active' => true]);
            }

            foreach ($offerings as $index => $offering) {
                $subject = $grade->subjects()->whereIn('name_en', $this->renamedSubjectAliases($grade, $offering['subject']))->first()
                    ?? Subject::query()->firstOrCreate(
                        ['grade_id' => $grade->id, 'name_en' => $offering['subject']],
                        ['name_ur' => $offering['subject'], 'sort_order' => 100 + $index, 'is_active' => true]
                    );
                if (! $subject->is_active) {
                    $subject->update(['is_active' => true]);
                }

                $session = str_contains($offering['edition'], '2026-27') ? '2026-2027' : '2025-2026';
                $editionName = match ([$number, $offering['subject']]) {
                    [9, 'English'] => 'PECTAA English 9 2025-26',
                    [9, 'Computer Science'] => 'PECTAA Computer Science 9 2025-26',
                    default => $offering['edition'],
                };
                $groupName = $number === 9 && in_array($offering['subject'], ['English', 'Computer Science'], true)
                    ? null
                    : $offering['group'];

                $editionKey = [
                    'academic_session' => $session,
                    'grade_id' => $grade->id,
                    'subject_id' => $subject->id,
                    'board' => 'Punjab Boards',
                    'group_name' => $groupName,
                    'textbook_edition' => $editionName,
                ];

                $edition = SyllabusEdition::query()->firstOrNew($editionKey);
                // Never downgrade an independently verified edition when the
                // catalogue inventory seeder is rerun.
                if ($edition->exists && ($edition->verified_at || $edition->status !== 'unverified')) {
                    continue;
                }
                $edition->fill([
                    'effective_session' => $session,
                    'status' => 'unverified',
                    'source_url' => self::CATALOGUE_URL,
                    'verified_at' => null,
                    'source_type' => 'official_catalog_listing',
                    // This field is reserved for a fetched document hash;
                    // a hash of labels is not evidence of source content.
                    'content_hash' => null,
                ])->save();

                // Preserve existing seeded chapter titles for review, but
                // never promote them to verified from catalogue evidence.
                foreach ($subject->chapters()->where('is_active', true)->orderBy('number')->get() as $chapter) {
                    $existing = SyllabusChapter::query()->where('syllabus_edition_id', $edition->id)
                        ->where('chapter_number', $chapter->number)->first();
                    if ($existing && ($existing->verified_at || $existing->status !== 'unverified')) {
                        continue;
                    }
                    SyllabusChapter::query()->updateOrCreate(
                        ['syllabus_edition_id' => $edition->id, 'chapter_number' => $chapter->number],
                        [
                            'chapter_title' => $chapter->title_en,
                            'topics' => null,
                            'status' => 'unverified',
                            'change_type' => 'unverified',
                            'source_url' => null,
                            'verified_at' => null,
                            'change_notes' => 'Copied from an existing application seeder for review. PECTAA catalogue listing confirms book availability only; chapter title, applicability, topics, and exam status remain unverified.',
                        ]
                    );
                }
            }
        }
    }
}
