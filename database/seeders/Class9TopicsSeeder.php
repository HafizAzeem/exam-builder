<?php

namespace Database\Seeders;

use App\Models\Chapter;
use App\Models\Grade;
use App\Models\Question;
use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Database\Seeder;

class Class9TopicsSeeder extends Seeder
{
    public function run(): void
    {
        $grade = Grade::query()->where('number', 9)->first();
        if (! $grade) {
            return;
        }

        $subjects = Subject::query()->where('grade_id', $grade->id)->get();

        foreach ($subjects as $subject) {
            $blueprint = $this->blueprintFor($subject->name_en);

            foreach ($blueprint as $index => $chapterData) {
                $number = $index + 1;

                $chapter = Chapter::query()->updateOrCreate(
                    [
                        'subject_id' => $subject->id,
                        'number' => $number,
                    ],
                    [
                        'title_en' => $chapterData['title_en'],
                        'title_ur' => $chapterData['title_ur'] ?? null,
                    ]
                );

                foreach ($chapterData['topics'] as $topicIndex => $topicData) {
                    Topic::query()->updateOrCreate(
                        [
                            'chapter_id' => $chapter->id,
                            'code' => $topicData['code'],
                        ],
                        [
                            'title_en' => $topicData['title_en'],
                            'title_ur' => $topicData['title_ur'] ?? null,
                            'sort_order' => $topicIndex + 1,
                        ]
                    );
                }
            }

            $this->assignQuestionsToTopics($subject);
        }
    }

    protected function assignQuestionsToTopics(Subject $subject): void
    {
        $chapters = Chapter::query()
            ->where('subject_id', $subject->id)
            ->with('topics')
            ->get();

        foreach ($chapters as $chapter) {
            $topics = $chapter->topics;
            if ($topics->isEmpty()) {
                continue;
            }

            $questions = Question::query()
                ->where('chapter_id', $chapter->id)
                ->whereNull('topic_id')
                ->whereNull('parent_question_id')
                ->orderBy('id')
                ->get();

            foreach ($questions as $i => $question) {
                $topic = $topics[$i % $topics->count()];
                $question->update(['topic_id' => $topic->id]);
            }
        }
    }

    /**
     * @return array<int, array{title_en: string, title_ur?: string, topics: array<int, array{code: string, title_en: string, title_ur?: string}>}>
     */
    protected function blueprintFor(string $subjectName): array
    {
        return match ($subjectName) {
            'Chemistry' => [
                [
                    'title_en' => 'Fundamentals of Chemistry',
                    'title_ur' => 'کیمسٹری کی بنیادیں',
                    'topics' => [
                        ['code' => '1.1', 'title_en' => 'Branches of Chemistry', 'title_ur' => 'کیمسٹری کی شاخیں'],
                        ['code' => '1.2', 'title_en' => 'Basic Definitions', 'title_ur' => 'بنیادی تعریفات'],
                        ['code' => '1.3', 'title_en' => 'Chemical Species', 'title_ur' => 'کیمیائی انواع'],
                        ['code' => '1.4', 'title_en' => 'Avogadro\'s Number and Mole', 'title_ur' => 'ایووگیڈرو نمبر اور مول'],
                        ['code' => '1.5', 'title_en' => 'Chemical Calculations', 'title_ur' => 'کیمیائی حساب'],
                    ],
                ],
                [
                    'title_en' => 'Structure of Atoms',
                    'title_ur' => 'ایٹم کی ساخت',
                    'topics' => [
                        ['code' => '2.1', 'title_en' => 'Theories and Experiments Related to Atomic Structure', 'title_ur' => 'ایٹمی ساخت سے متعلق نظریات'],
                        ['code' => '2.2', 'title_en' => 'Electronic Configuration', 'title_ur' => 'برقی ترتیب'],
                        ['code' => '2.3', 'title_en' => 'Isotopes', 'title_ur' => 'آئسوٹوپس'],
                    ],
                ],
                [
                    'title_en' => 'Periodic Table and Periodicity of Properties',
                    'title_ur' => 'دوری جدول',
                    'topics' => [
                        ['code' => '3.1', 'title_en' => 'Periodic Table', 'title_ur' => 'دوری جدول'],
                        ['code' => '3.2', 'title_en' => 'Periodicity of Properties', 'title_ur' => 'خصوصیات کی تکرار'],
                    ],
                ],
                [
                    'title_en' => 'Structure of Molecules',
                    'title_ur' => 'مالیکیولز کی ساخت',
                    'topics' => [
                        ['code' => '4.1', 'title_en' => 'Why do Atoms Form Chemical Bonds?', 'title_ur' => 'کیمیائی بندھن کیوں؟'],
                        ['code' => '4.2', 'title_en' => 'Chemical Bonds', 'title_ur' => 'کیمیائی بندھن'],
                        ['code' => '4.3', 'title_en' => 'Intermolecular Forces', 'title_ur' => 'بین المالیکیولی قوتیں'],
                        ['code' => '4.4', 'title_en' => 'Nature of Bonding and Properties', 'title_ur' => 'بندھن اور خصوصیات'],
                        ['code' => '4.5', 'title_en' => 'Shapes of Molecules', 'title_ur' => 'مالیکیولز کی شکلیں'],
                    ],
                ],
                [
                    'title_en' => 'Physical States of Matter',
                    'title_ur' => 'مادے کی طبیعی حالتیں',
                    'topics' => [
                        ['code' => '5.1', 'title_en' => 'Gaseous State', 'title_ur' => 'گیسی حالت'],
                        ['code' => '5.2', 'title_en' => 'Liquid State', 'title_ur' => 'مائع حالت'],
                        ['code' => '5.3', 'title_en' => 'Diffusion and Effusion', 'title_ur' => 'انتشار'],
                        ['code' => '5.4', 'title_en' => 'Plasma State', 'title_ur' => 'پلازما حالت'],
                        ['code' => '5.5', 'title_en' => 'Solid State', 'title_ur' => 'جامد حالت'],
                        ['code' => '5.6', 'title_en' => 'Allotropes of Carbon', 'title_ur' => 'کاربن کے ایلوٹروپس'],
                    ],
                ],
                [
                    'title_en' => 'Solutions',
                    'title_ur' => 'محلول',
                    'topics' => [
                        ['code' => '6.1', 'title_en' => 'Solution, Aqueous Solution, Solute and Solvent', 'title_ur' => 'محلول اور محلول اجزا'],
                        ['code' => '6.2', 'title_en' => 'Saturated, Unsaturated and Supersaturated Solutions', 'title_ur' => 'سیر شدہ محلول'],
                        ['code' => '6.3', 'title_en' => 'Types of Solution', 'title_ur' => 'محلول کی اقسام'],
                        ['code' => '6.4', 'title_en' => 'Concentration Units', 'title_ur' => 'ارتکاز کی اکائیاں'],
                        ['code' => '6.5', 'title_en' => 'Solubility', 'title_ur' => 'حل پذیری'],
                        ['code' => '6.6', 'title_en' => 'Comparison of Solution, Suspension and Colloid', 'title_ur' => 'محلول، سسپنشن اور کولوئیڈ'],
                    ],
                ],
                [
                    'title_en' => 'Electrochemistry',
                    'title_ur' => 'برق کیمیا',
                    'topics' => [
                        ['code' => '7.1', 'title_en' => 'Oxidation and Reduction', 'title_ur' => 'آکسیڈیشن اور ریڈکشن'],
                        ['code' => '7.2', 'title_en' => 'Oxidation States and Balancing', 'title_ur' => 'آکسیڈیشن نمبر'],
                        ['code' => '7.3', 'title_en' => 'Electrochemical Cells', 'title_ur' => 'برقی کیمیائی سیلز'],
                        ['code' => '7.4', 'title_en' => 'Electrochemical Industries', 'title_ur' => 'برقی کیمیائی صنعتیں'],
                        ['code' => '7.5', 'title_en' => 'Corrosion and Protection', 'title_ur' => 'زنگ اور حفاظت'],
                    ],
                ],
            ],
            'Physics' => $this->genericChapters('Physics', 'طبیعیات', [
                'Physical Quantities and Measurement',
                'Kinematics',
                'Dynamics',
                'Turning Effect of Forces',
                'Gravitation',
                'Work and Energy',
            ], 'فزکس'),
            'Biology' => $this->genericChapters('Biology', 'حیاتیات', [
                'Introduction to Biology',
                'Solving a Biological Problem',
                'Biodiversity',
                'Cells and Tissues',
                'Cell Cycle',
                'Enzymes',
            ], 'حیاتیات'),
            'Mathematics' => $this->genericChapters('Mathematics', 'ریاضی', [
                'Matrices and Determinants',
                'Real and Complex Numbers',
                'Logarithms',
                'Algebraic Expressions and Algebraic Formulas',
                'Factorization',
                'Algebraic Manipulation',
                'Linear Equations and Inequalities',
                'Quadratic Equations',
            ], 'ریاضی'),
            'Computer Science' => $this->genericChapters('Computer Science', 'کمپیوٹر سائنس', [
                'Fundamentals of Computer',
                'Fundamentals of Operating System',
                'Office Automation',
                'Data Communication',
                'Computer Networks',
                'Computer Security and Ethics',
            ], 'کمپیوٹر'),
            // PCTB Class 9 English (NCP 2023): 11 textbook units + Grammar & Composition companion.
            'English' => $this->genericChapters('English', 'انگریزی', [
                'The Saviour of Mankind',
                'Patriotism',
                'Daffodils',
                'Hazrat Asma (R.A.)',
                'Women Empowerment through Entrepreneurship',
                'The Value of Time',
                'If',
                'The Impact of Globalisation on Culture and Economy',
                'Quality Education: A Key to Success',
                'The Silent Predator and the Majestic Prey — Snow Leopard and Markhor',
                'The Dear Departed',
                'English Grammar and Composition',
            ], 'انگریزی'),
            default => $this->genericChapters(
                $subjectName,
                $subjectName,
                array_map(fn ($i) => "{$subjectName} Chapter {$i}", range(1, 6)),
                $subjectName
            ),
        };
    }

    /**
     * @param  array<int, string>  $titles
     * @return array<int, array{title_en: string, title_ur: string, topics: array<int, array{code: string, title_en: string, title_ur: string}>}>
     */
    protected function genericChapters(string $subjectEn, string $subjectUr, array $titles, string $topicUrPrefix): array
    {
        $chapters = [];

        foreach ($titles as $index => $title) {
            $n = $index + 1;
            $topicCount = $n === 1 ? 4 : 3;
            $topics = [];

            for ($t = 1; $t <= $topicCount; $t++) {
                $topics[] = [
                    'code' => "{$n}.{$t}",
                    'title_en' => "Topic {$n}.{$t}: Key Concepts",
                    'title_ur' => "{$topicUrPrefix} موضوع {$n}.{$t}",
                ];
            }

            $chapters[] = [
                'title_en' => $title,
                'title_ur' => "{$subjectUr} باب {$n}",
                'topics' => $topics,
            ];
        }

        return $chapters;
    }
}
