<?php

namespace Database\Seeders;

use App\Models\Grade;
use App\Models\SyllabusChapter;
use App\Models\SyllabusEdition;
use Illuminate\Database\Seeder;

/**
 * Human-reviewed chapter titles and TOC topics transcribed from linked PECTAA
 * textbooks. Edition applicability and comparison remain unverified unless a
 * separate official session notice establishes them.
 */
class PectaaVerifiedBookContentsSeeder extends Seeder
{
    private const ENGLISH_GRADE_1_URL = 'https://drive.google.com/file/d/1qdIPBXxaVHia6BVInY8uXWmzKkzGZ09Y/view?usp=sharing';
    private const ENGLISH_SOURCES = [
        1 => self::ENGLISH_GRADE_1_URL,
        2 => 'https://drive.google.com/file/d/1cZ1ACv3BmE26-3Ys8EzrRrMSSXCOfChg/view?usp=drive_link',
        3 => 'https://drive.google.com/file/d/1WqOqnXmwg6UhfSAzlf4Z-nEQ4GacOdaV/view?usp=drive_link',
        4 => 'https://drive.google.com/file/d/17RoEMjmPeKHFr7fsSY2DZluFgBBfQtkC/view?usp=drive_link',
        5 => 'https://drive.google.com/file/d/1Z9xfT2L8KKvEfyEifJdODkbs-chlonfF/view?usp=drive_link',
    ];

    /** @var array<int, array{title:string, topics:list<string>}> */
    private const GRADE_1_ENGLISH = [
        1 => ['title' => 'Time to Recall', 'topics' => ['oral communication: letter sounds']],
        2 => ['title' => 'My Family and I', 'topics' => ['theme: self, people, places and the globe', 'subtheme: myself', 'subtheme: my family', 'oral communication: letter sounds', 'oral communication: introduce themselves']],
        3 => ['title' => 'Cobbler, Cobbler', 'topics' => ['theme: education and employment (careers/occupations)', 'subtheme: common professions in the surroundings', 'oral communication: consonant blends', 'oral communication: greetings and routine social courtesies']],
        4 => ['title' => "Let's have Fun!", 'topics' => ['theme: life skills', 'theme: dignity of labour', 'theme: ethics and values', 'subtheme: helping others', 'subtheme: respect for elders', 'subtheme: cleanliness', 'subtheme: doing small chores', 'oral communication: consonant digraphs', 'oral communication: listen and respond to commands']],
        5 => ['title' => 'Sharing is Caring', 'topics' => ['theme: peaceful coexistence/peace education', 'subtheme: sharing', 'oral communication: words and sounds', 'oral communication: express limited needs']],
        6 => ['title' => 'Blessings of Allah', 'topics' => ['theme: health education', 'theme: peaceful coexistence/peace education', 'subtheme: fruit and vegetables', 'subtheme: making friends', 'subtheme: healthy food', 'subtheme: listening to others', 'oral communication: initial and ending sounds', 'oral communication: express basic emotions']],
        7 => ['title' => 'Classroom Manners', 'topics' => ['theme: participatory citizenship', 'theme: ethics and values', 'subtheme: following classroom rules', 'subtheme: making queues', 'oral communication: rhyming words', 'oral communication: greetings and routine social courtesies']],
        8 => ['title' => 'Nature is Beautiful', 'topics' => ['theme: nature', 'subtheme: seasons', 'oral communication: one-syllable words', 'oral communication: participate in a conversation and take turns']],
        9 => ['title' => 'A Greeting Card', 'topics' => ['theme: peaceful coexistence/peace education', 'subtheme: good habits', 'subtheme: sharing', 'subtheme: keeping the classroom clean', 'subtheme: cooperating and collaboration', 'oral communication: sight words', 'oral communication: express needs']],
        10 => ['title' => 'The Hare and the Tortoise', 'topics' => ['theme: nature', 'theme: peaceful coexistence/peace education', 'subtheme: making friends', 'subtheme: animals/birds', 'oral communication: /s/ and /z/ sounds in plurals', 'oral communication: express enjoyment while playing']],
        11 => ['title' => 'Love Animals', 'topics' => ['theme: nature', 'subtheme: taking care of animals', 'oral communication: /s/ and /z/ sounds in plurals', 'oral communication: body language']],
        12 => ['title' => 'Review 4', 'topics' => ['review section; not a new instructional chapter']],
    ];

    /** @var array<int, array{title:string, topics:list<string>}> */
    private const GRADE_2_ENGLISH = [
        1 => ['title'=>'A Tiny Creature','topics'=>['theme: nature','subtheme: birds/animals','oral communication: initial and final sounds of letters','oral communication: sounds of alphabetical letters']],
        2 => ['title'=>'My Home and City','topics'=>['theme: self, people and places','subtheme: my family','oral communication: consonants and vowels','oral communication: consonant blends','oral communication: introduce self and family']],
        3 => ['title'=>"Let’s Plant Trees",'topics'=>['theme: environmental education','subtheme: importance of plants and trees','oral communication: digraphs','oral communication: greetings and routine social courtesies']],
        4 => ['title'=>'Bee on My Nose','topics'=>['theme: life skills','subtheme: being grateful','oral communication: consonant clusters','oral communication: listen and respond to commands','oral communication: express needs and feelings']],
        5 => ['title'=>'Attention!','topics'=>['theme: participatory citizenship','subtheme: following simple traffic rules','oral communication: letters and sounds','oral communication: seek permission']],
        6 => ['title'=>'Be Honest','topics'=>['theme: ethics/values','subtheme: honesty','oral communication: initial and final same sounds','oral communication: express greetings and routine social courtesies']],
        7 => ['title'=>'Sports Day','topics'=>['theme: sports','subtheme: local sports','oral communication: syllables','oral communication: introduce themselves','oral communication: participate in conversation and take turns']],
        8 => ['title'=>'My School','topics'=>['theme: education and employment','subtheme: my school','oral communication: syllable','oral communication: take turns','oral communication: use polite expressions to seek attention']],
        9 => ['title'=>'What a Good Deed!','topics'=>['theme: role model','subtheme: making friends','subtheme: cooperation','subtheme: helping others','oral communication: irregular sight words','oral communication: express likes/dislikes, needs and feelings']],
        10 => ['title'=>'An Ant and a Dove','topics'=>['theme: peaceful co-existence/peace education','subtheme: heroic deeds of children','oral communication: plurals with s, z and iz sounds','oral communication: express joy while playing']],
        11 => ['title'=>'Love for Parents','topics'=>['theme: life skills','theme: ethics and values','subtheme: respect for elders','oral communication: weak forms of a and the','oral communication: contractions of be','oral communication: describe things and objects']],
        12 => ['title'=>'Seasons','topics'=>['theme: nature','subtheme: seasons','oral communication: rhythm, stress and intonation','oral communication: use of body language']],
    ];

    /** @var array<int, array{title:string, topics:list<string>}> */
    private const GRADE_3_ENGLISH = [
        1 => ['title'=>'All are Welcome','topics'=>['theme: education and employment','subtheme: my school','subtheme: following school rules','oral communication: soft sounds of c and g','oral communication: consonant cluster','oral communication: courtesies to show respect','oral communication: express greetings']],
        2 => ['title'=>'Gifts of Nature','topics'=>['theme: nature','subtheme: taking care of animals/birds','subtheme: seasons/colors','oral communication: long and short vowels and trigraphs','oral communication: recite a poem','oral communication: describe events in a picture']],
        3 => ['title'=>'The People I Love','topics'=>['theme: self, people, places and globe','subtheme: my self','subtheme: my family','subtheme: my home','oral communication: weak forms of has/have','oral communication: mark of exclamation','oral communication: my self and my family']],
        4 => ['title'=>'Kindness to Children','topics'=>['theme: role models (national heroes/character building)','subtheme: heroic deeds','subtheme: courage/honesty','oral communication: Naat recitation','oral communication: ch sounds','oral communication: daily routine talk']],
        5 => ['title'=>'Road Safety','topics'=>['theme: participatory citizenship','subtheme: traffic rules, making queues and following rules','oral communication: weak/strong forms of do/does','oral communication: express likes and dislikes']],
        6 => ['title'=>'The Day of Silence','topics'=>['theme: ethics and values (patience/tolerance)','subtheme: making friends, sharing/cooperating/collaboration','oral communication: silent b sounds','oral communication: speech in bubbles','oral communication: talk about a place recently visited']],
        7 => ['title'=>'I Like to Play','topics'=>['theme: sports','subtheme: game viewing','subtheme: local sports','oral communication: consonant cluster sounds','oral communication: express opinions','oral communication: lead and follow']],
        8 => ['title'=>'Saving Resources','topics'=>['theme: environmental education','subtheme: knowledge/awareness of resources','subtheme: saving water/resources','oral communication: pronounce weak forms of do/have','oral communication: public speaking']],
        9 => ['title'=>'My Culture – My Pride','topics'=>['theme: festivals and cultural events','subtheme: enjoying festivals','oral communication: practise short e sound','oral communication: respect and gratitude','oral communication: magic word']],
        10 => ['title'=>'Our Family Picnic','topics'=>['theme: adventure','subtheme: accepting a challenge for a field trip','oral communication: consonant blends','oral communication: talk on a picture']],
        11 => ['title'=>'Healthy Habits','topics'=>['theme: health/personal safety','subtheme: personal cleanliness and hygiene','subtheme: use of water after using the bathroom','oral communication: pronouns, simple words with silent letters, syllable stress and dictionary use']],
    ];

    /** @var array<int, array{title:string, topics:list<string>}> */
    private const GRADE_4_ENGLISH = [
        1 => ['title'=>'Great Caliphs of Islam','topics'=>['theme: ethics and values (moral character, right and wrong)','subtheme: sense of fair play','subtheme: respect for elders','subtheme: punctuality','oral communication: vowel sounds','oral communication: formulaic expressions and routine greetings']],
        2 => ['title'=>'Beauty of Nature (Poem)','topics'=>['theme: environmental education','subtheme: understanding and appreciation of natural environment','subtheme: use of clean drinking water','subtheme: planting of trees','oral communication: silent letters','oral communication: introduce self and others']],
        3 => ['title'=>'The Journey of Chocolate','topics'=>['theme: technology','subtheme: amazing inventions','oral communication: diphthongs','oral communication: express likes and dislikes']],
        4 => ['title'=>'The Pride of Pakistan','topics'=>['theme: patriotism/national pride','subtheme: amazing inventions','oral communication: diphthongs','oral communication: respond to instructions and directions']],
        5 => ['title'=>'Thank you, Lord (Poem)','topics'=>['theme: ethics and values','subtheme: showing/exhibiting patriotism through deeds','oral communication: consonant clusters','oral communication: express opinion']],
        6 => ['title'=>'Valuing Others','topics'=>['theme: role models (male/female, past and present)','subtheme: moral character/right and wrong','oral communication: use of the','oral communication: asking permission','oral communication: show ability or inability']],
        7 => ['title'=>'Colours of Pakistan','topics'=>['theme: self, people and places','subtheme: notable special people as role models, national and international','oral communication: long vowel sounds','oral communication: respond to instructions and directions']],
        8 => ['title'=>'Good Study Habits','topics'=>['theme: festivals and cultural events','subtheme: religious/cultural festivals in Pakistan','oral communication: short vowel sounds','oral communication: express regret']],
        9 => ['title'=>'Manners (Poem)','topics'=>['theme: health, personal safety and drug education','subtheme: benefits of hygiene, sanitation and healthy eating habits','oral communication: three sounds of -ed','oral communication: engage in conversation']],
        10 => ['title'=>'Be Aware Be Safe','topics'=>['theme: crisis awareness and management, health/personal safety, drug education','subtheme: safety at school/playground/streets','subtheme: avoid befriending strangers','oral communication: weak forms of and/of/or/but','oral communication: seek attention']],
        11 => ['title'=>'The Fox and The Stork','topics'=>['theme: peaceful coexistence/peace education','subtheme: friendship','subtheme: respecting others and self','oral communication: intonation patterns','oral communication: agree/disagree politely','oral communication: lead and follow']],
        12 => ['title'=>'Time to think!','topics'=>['theme: participatory citizenship','subtheme: traffic education','subtheme: obeying rules and regulations','oral communication: weak forms','oral communication: express needs and feelings, joy, sadness and anger']],
        13 => ['title'=>'Little Things (Poem)','topics'=>['theme: nature','subtheme: hills, rivers, lakes, plains, deserts and mountains','oral communication: rhythm, stress and intonation','oral communication: non-verbal cues']],
    ];

    /** @var array<int, array{title:string, topics:list<string>}> */
    private const GRADE_5_ENGLISH = [
        1 => ['title'=>'Patience','topics'=>['theme: ethics (moral character/right and wrong), values of society','subtheme: honesty, patience, tolerance','oral communication: silent letters','oral communication: routine greetings and social courtesies']],
        2 => ['title'=>'Be Grateful (Poem)','topics'=>['theme: ethics and values','subtheme: be grateful','oral communication: vowel sounds','oral communication: express and respond to opinions']],
        3 => ['title'=>'Women as Role Models','topics'=>['theme: gender equality','subtheme: importance of female education','oral communication: short and long vowel sounds','oral communication: offer and accept apologies']],
        4 => ['title'=>'Unforgettable Moments of My Life','topics'=>['theme: nature/environment','subtheme: hills, rivers, lakes, plains, deserts and mountains','oral communication: diphthongs','oral communication: simple directions and instructions']],
        5 => ['title'=>'Amazing Planet (Poem)','topics'=>['theme: environmental education','subtheme: understanding of natural environment','subtheme: introduction to climate change','oral communication: consonant clusters']],
        6 => ['title'=>'A Fit and Healthy Life','topics'=>['theme: health, personal safety and drug education','subtheme: benefits of hygiene, healthy food and healthy eating habits','subtheme: effects of littering on surroundings','oral communication: consonant clusters','oral communication: introduce themselves and others','oral communication: engage in a conversation']],
        7 => ['title'=>'What Goes Around, Comes Around','topics'=>['theme: avoiding social evils','subtheme: eluding bullying','subtheme: avoiding degrading','oral communication: vowels and consonants','oral communication: engage in a conversation']],
        8 => ['title'=>"Do What’s Right",'topics'=>['theme: participatory citizenship','subtheme: effects of littering (avoiding plastic bags/wall chalking)','oral communication: long and short vowel sounds','oral communication: taking turns']],
        9 => ['title'=>'Patriotism','topics'=>['theme: patriotism/national pride','subtheme: showing/exhibiting patriotism through deeds, including minorities','oral communication: -ed sounds','oral communication: seek attention']],
        10 => ['title'=>'Eid-ul-Azha','topics'=>['theme: festivals and cultural events','subtheme: religious festivals in Pakistan','oral communication: syllables','oral communication: agree and disagree politely']],
        11 => ['title'=>"Let’s Be Helpful",'topics'=>['theme: education and employment/careers/occupations','theme: crisis awareness and management','subtheme: good study habits, book reading, cyber crime and internet safety','oral communication: use of a dictionary','oral communication: lead and follow']],
        12 => ['title'=>'Our National Animal','topics'=>['theme: national pride/patriotism','subtheme: love for Pakistan','oral communication: weak forms of will not and shall not','oral communication: express needs']],
        13 => ['title'=>'When Something Went Wrong','topics'=>['theme: media','subtheme: media as a source of general information','oral communication: intonation','oral communication: making polite requests and taking leaves']],
        14 => ['title'=>'Together We Live (Poem)','topics'=>['theme: peaceful coexistence/peace education, avoiding social evils','subtheme: keeping promises, resolving quarrels, respecting others, controlling anger','oral communication: rhythm, stress and intonation','oral communication: seek attention','oral communication: non-verbal cues']],
    ];

    public function run(): void
    {
        $this->seedEnglishBook(1, 'English 1', self::GRADE_1_ENGLISH);
        $this->seedEnglishBook(2, 'English 2', self::GRADE_2_ENGLISH);
        $this->seedEnglishBook(3, 'English 3', self::GRADE_3_ENGLISH);
        $this->seedEnglishBook(4, 'English 4', self::GRADE_4_ENGLISH);
        $this->seedEnglishBook(5, 'English 5', self::GRADE_5_ENGLISH);
    }

    /** @param array<int, array{title:string, topics:list<string>}> $chapters */
    private function seedEnglishBook(int $gradeNumber, string $editionName, array $chapters): void
    {
        $grade = Grade::query()->where('number', $gradeNumber)->first();
        $subject = $grade?->subjects()->where('name_en', 'English')->first();
        if (! $grade || ! $subject) {
            return;
        }

        $edition = SyllabusEdition::query()
            ->where('academic_session', '2025-2026')
            ->where('grade_id', $grade->id)
            ->where('subject_id', $subject->id)
            ->where('board', 'Punjab Boards')
            ->where('group_name', 'Compulsory')
            ->where('textbook_edition', $editionName)
            ->first();
        if (! $edition) {
            return;
        }

        // The PECTAA catalogue links the book, but does not state that this
        // edition is applicable to the selected examination session.
        if (! $edition->verified_at && $edition->status === 'unverified') {
            $edition->update([
                'status' => 'unverified',
                'source_url' => self::ENGLISH_SOURCES[$gradeNumber],
                'source_type' => 'official_textbook_catalog_link',
                'verified_at' => null,
            ]);
        }

        foreach ($chapters as $number => $chapter) {
            $existing = SyllabusChapter::query()->where('syllabus_edition_id', $edition->id)
                ->where('chapter_number', $number)->first();
            if ($existing && ($existing->verified_at || $existing->status !== 'unverified')) {
                continue;
            }
            SyllabusChapter::query()->updateOrCreate(
                ['syllabus_edition_id' => $edition->id, 'chapter_number' => $number],
                [
                    'chapter_title' => $chapter['title'],
                    'topics' => $chapter['topics'],
                    'status' => 'current',
                    'change_type' => 'unverified',
                    'source_url' => self::ENGLISH_SOURCES[$gradeNumber],
                    'verified_at' => now()->toDateString(),
                    'change_notes' => 'Chapter title and TOC topics transcribed from the linked PECTAA/PCTB textbook contents page and visually checked against the rendered page. Session applicability and comparison with prior editions remain unverified.',
                ]
            );
        }
    }
}
