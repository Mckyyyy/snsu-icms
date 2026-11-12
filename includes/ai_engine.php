<?php
// includes/ai_engine.php - AI Dynamic Prioritization Engine (DPT-RRT)
//
// Single source of truth for urgency scoring, SLA assignment and category
// suggestion. The submission form's live preview calls api/ai_prioritize.php,
// which uses this same function, so the preview always matches what is stored.

/** Priority → SLA target in hours (Scope: "SLA target assignment"). */
const SLA_HOURS_BY_PRIORITY = [
    'Critical' => 24,
    'High'     => 48,
    'Medium'   => 72,
    'Low'      => 120,
];

const AI_CRITICAL_KEYWORDS = [
    'exposed wire', 'exposed wiring', 'live wire', 'loose wire', 'bare wire', 'electrical wire', 'wiring',
    'spark', 'sparks', 'sparking',
    'fire', 'smoke', 'shock', 'electric shock', 'short circuit', 'electrocution',
    'water leak', 'leak near', 'flooding', 'collapse', 'collapsed', 'ceiling collapsed',
    'medical emergency', 'injured', 'injury', 'bleeding', 'explosion', 'toxic',
    'gas leak', 'danger', 'dangerous', 'hazard', 'immediate danger', 'life-threatening', 'fainted', 'unconscious',
    // Filipino (Tagalog)
    'sunog', 'nasusunog', 'nasunog', 'nagliliyab', 'apoy', 'usok', 'umuusok', 'nag-uusok', 'nakuryente', 'nakoryente',
    'kuryente sa tubig', 'baha', 'binaha', 'bumabaha', 'gumuho', 'bumagsak ang kisame', 'nahulog ang kisame',
    'sugatan', 'nasugatan', 'dumudugo', 'nahimatay', 'nawalan ng malay', 'naaksidente', 'aksidente',
    'delikado', 'mapanganib', 'peligro', 'pagsabog', 'sumabog', 'tagas ng gas', 'singaw ng gas',
    // Cebuano / Surigaonon
    'kalayo', 'nasunog', 'nakuryentehan', 'nakoryentehan', 'lunop', 'gibaha', 'nangaguba', 'nahugno',
    'nasamdan', 'naangol', 'nagdugo', 'nakuyapan', 'kuyap', 'mibuto', 'nibuto',
];

const AI_HIGH_KEYWORDS = [
    'broken chair', 'broken chairs', 'projector not working', 'projector', 'hdmi broken',
    'exam', 'exams', 'examination', 'midterm', 'final exam', 'deadline today', 'deadline tomorrow',
    'cannot enroll', 'enrollment locked', 'portal error', 'missing grade', 'graduation requirement',
    'aircon not cooling', 'extreme heat', 'no power', 'blackout', 'server down',
    'lab unusable', 'unsafe', 'harassment', 'bullying', 'threat', 'threatened', 'brownout', 'power outage',
    // Filipino (Tagalog)
    'sirang upuan', 'sira ang upuan', 'sirang projector', 'sira ang projector', 'walang kuryente', 'nawalan ng kuryente',
    'pagsusulit', 'eksamen', 'hindi makapag-enroll', 'di makapag-enroll', 'walang grado', 'nawawalang grado',
    'sobrang init', 'init na init', 'binubully', 'binu-bully', 'hinarass', 'hina-harass', 'pinagbantaan', 'pananakot',
    'panliligalig', 'hindi ligtas',
    // Cebuano / Surigaonon
    'guba ang lingkuranan', 'gubang lingkuranan', 'walay kuryente', 'wala\'y kuryente', 'gi-bully', 'gibully',
    'gihulga', 'gidaogdaog', 'init kaayo', 'dili luwas',
];

const AI_MEDIUM_KEYWORDS = [
    'internet', 'wifi', 'wi-fi', 'slow connection', 'comfort room', 'restroom', 'dirty',
    'no water', 'sanitation', 'canteen', 'library book', 'card error', 'printer jam',
    'attendance error', 'schedule conflict', 'clarification', 'overlap',
    // Filipino (Tagalog)
    'mabagal ang internet', 'mahina ang wifi', 'walang internet', 'walang tubig', 'marumi', 'mabaho', 'banyo', 'kasilyas',
    'kantina', 'sira ang printer', 'sira',
    // Cebuano / Surigaonon
    'hinay ang internet', 'hinay kaayo', 'walay internet', 'walay tubig', 'wala\'y tubig', 'hugaw', 'baho', 'guba',
];

/**
 * Negation words: a keyword right after one of these in the same clause is not counted
 * ("no fire", "walang sunog", "dili delikado"). The scope ends at punctuation.
 */
const AI_NEGATION_WORDS = [
    'no', 'not', 'never', 'without', 'isnt', "isn't", 'wasnt', "wasn't", 'nothing',
    'walang', 'wala', 'hindi', 'di', 'hinde', 'dili', 'walay', "wala'y",
];

/** Harmless phrases removed before matching, so "fire drill" or "fire exit sign" is not read as a fire. */
const AI_BENIGN_PHRASES = [
    'fire drill', 'earthquake drill', 'evacuation drill', 'drill schedule', 'fire exit', 'fire extinguisher training',
    'sunog drill', 'fire safety seminar', 'fire safety training',
];

/** Keywords used to auto-suggest a category (IPO: "auto-categorizes"). Keys must match categories.category_name. */
const AI_CATEGORY_KEYWORDS = [
    'Facilities' => [
        'chair', 'chairs', 'classroom', 'room', 'restroom', 'comfort room', 'toilet', 'aircon', 'air-conditioning',
        'air conditioner', 'light', 'lights', 'projector', 'ceiling', 'leak', 'wiring', 'door', 'window',
        'electric', 'electrical', 'water', 'building', 'lab', 'laboratory', 'equipment', 'fan', 'outlet',
        'upuan', 'silya', 'lingkuranan', 'kisame', 'pinto', 'pultahan', 'bintana', 'ilaw', 'suga', 'bentilador',
        'electric fan', 'banyo', 'cr', 'kasilyas', 'gusali', 'tubig', 'kuryente', 'koryente', 'sunog', 'baha',
    ],
    'Academic' => [
        'exam', 'exams', 'examination', 'midterm', 'grade', 'grades', 'grading', 'schedule', 'subject', 'course',
        'curriculum', 'faculty', 'professor', 'instructor', 'teacher', 'enroll', 'enrollment', 'attendance',
        'lms', 'class', 'syllabus', 'thesis', 'capstone', 'units', 'reference books',
        'grado', 'marka', 'guro', 'maestra', 'maestro', 'klase', 'iskedyul', 'eksamen', 'pagsusulit', 'asignatura',
    ],
    'Services' => [
        'wifi', 'wi-fi', 'internet', 'network', 'id', 'id card', 'printing', 'printer', 'library', 'canteen',
        'food', 'portal', 'website', 'system', 'cashier', 'payment', 'registrar', 'document request',
        'kantina', 'pagkain', 'bayad', 'bayarin', 'silid-aklatan', 'opisina',
    ],
    'Student Welfare' => [
        'guidance', 'scholarship', 'health', 'clinic', 'counseling', 'counselling', 'bullying', 'harassment',
        'mental', 'stress', 'anxiety', 'organization', 'discrimination', 'financial', 'allowance', 'welfare',
        'bully', 'binubully', 'gibully', 'kalusugan', 'klinika', 'nalulungkot', 'depressed', 'pananakot', 'gihulga',
    ],
];

/**
 * Whole-word / whole-phrase keyword detection (avoids "fire" matching "firewall",
 * "exam" matching "example", etc.).
 */
function aiDetectKeywords(string $text, array $keywords, ?array &$negated = null): array {
    $found = [];
    foreach ($keywords as $kw) {
        $pattern = '/(?<![\p{L}\p{N}])' . preg_quote($kw, '/') . '(?![\p{L}\p{N}])/u';
        if (!preg_match_all($pattern, $text, $m, PREG_OFFSET_CAPTURE)) {
            continue;
        }
        // Counted when at least one mention is not negated ("no fire here, but the lab is on fire" still counts)
        $counted = false;
        foreach ($m[0] as [, $offset]) {
            if (!aiIsNegated($text, (int)$offset)) {
                $counted = true;
                break;
            }
        }
        if ($counted) {
            $found[] = $kw;
        } elseif ($negated !== null) {
            $negated[] = $kw;
        }
    }
    return $found;
}

/**
 * Is the mention at byte $offset negated? Looks at up to 3 words before it within the same clause
 * (stops at . , ; : ! ? or "but"/"pero"/"apan"), e.g. "no fire", "walang sunog", "there is not any smoke".
 */
function aiIsNegated(string $text, int $offset): bool {
    $before = substr($text, max(0, $offset - 60), min($offset, 60));
    $clause = preg_split('/[.,;:!?\n]|\b(?:but|pero|apan|kaso)\b/u', $before);
    $words  = array_slice(preg_split("/[^\p{L}']+/u", (string)end($clause), -1, PREG_SPLIT_NO_EMPTY), -3);
    // "walang tigil" / "walay undang" mean "non-stop" - they intensify, they do not negate
    $notNegating = ['tigil', 'humpay', 'katapusan', 'undang', 'hunong', 'stop', 'end'];
    foreach ($words as $i => $w) {
        if (in_array($w, AI_NEGATION_WORDS, true) && !in_array($words[$i + 1] ?? '', $notNegating, true)) {
            return true;
        }
    }
    return false;
}

/**
 * Suggest the best-matching category name, or null when nothing matches.
 */
function aiSuggestCategory(string $text): ?string {
    $best = null;
    $bestHits = 0;
    foreach (AI_CATEGORY_KEYWORDS as $category => $keywords) {
        $hits = count(aiDetectKeywords($text, $keywords));
        if ($hits > $bestHits) {
            $best = $category;
            $bestHits = $hits;
        }
    }
    return $best;
}

function slaHoursForPriority(string $priority): int {
    return SLA_HOURS_BY_PRIORITY[$priority] ?? 72;
}

/**
 * Compute urgency score (1-100), priority level, SLA hours, RRT alert flag,
 * human-readable reasons and a suggested category.
 */
function calculateDynamicUrgency(string $title, string $description, string $categoryName = ''): array {
    $text = mb_strtolower($title . ' . ' . $description, 'UTF-8');
    $text = str_replace(['’', '‘'], "'", $text);
    // Harmless phrases ("fire drill") are blanked out so their words are not read as hazards
    $text = str_replace(AI_BENIGN_PHRASES, ' ', $text);

    $negated  = [];
    $critical = aiDetectKeywords($text, AI_CRITICAL_KEYWORDS, $negated);
    $high     = aiDetectKeywords($text, AI_HIGH_KEYWORDS, $negated);
    $medium   = aiDetectKeywords($text, AI_MEDIUM_KEYWORDS, $negated);
    // A word found un-negated elsewhere is not reported as ignored
    $negated  = array_values(array_diff(array_unique($negated), $critical, $high, $medium));

    $score   = 25; // baseline
    $reasons = [];

    // Category weighting (exact category names, not free text)
    $catWeights = ['facilities' => 15, 'academic' => 10, 'services' => 5];
    $catKey = mb_strtolower(trim($categoryName), 'UTF-8');
    if (isset($catWeights[$catKey])) {
        $score += $catWeights[$catKey];
        $reasons[] = "Category weight applied: {$categoryName} (+{$catWeights[$catKey]})";
    }

    if ($critical) {
        $score = max($score, 85) + count($critical) * 4;
        array_unshift($reasons, 'Severe hazard keywords detected: ' . implode(', ', array_slice($critical, 0, 3)));
    } elseif ($high) {
        $score = max($score, 65) + count($high) * 3;
        array_unshift($reasons, 'High impact operational keywords detected: ' . implode(', ', array_slice($high, 0, 3)));
    } elseif ($medium) {
        $score = max($score, 45) + count($medium) * 2;
        array_unshift($reasons, 'General service keywords detected: ' . implode(', ', array_slice($medium, 0, 3)));
    } else {
        array_unshift($reasons, 'Routine inquiry / standard priority');
    }

    if ($negated) {
        $reasons[] = 'Negated mentions not counted: ' . implode(', ', array_slice($negated, 0, 3));
    }

    $score = min(100, max(1, $score));

    if ($score >= 80) {
        $priority = 'Critical';
    } elseif ($score >= 60) {
        $priority = 'High';
    } elseif ($score >= 40) {
        $priority = 'Medium';
    } else {
        $priority = 'Low';
    }

    return [
        'score'              => $score,
        'priority'           => $priority,
        'sla_hours'          => slaHoursForPriority($priority),
        'is_rrt_alert'       => $priority === 'Critical' ? 1 : 0,
        'reasons'            => $reasons,
        'suggested_category' => aiSuggestCategory($text),
    ];
}
