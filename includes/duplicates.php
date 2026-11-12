<?php
// includes/duplicates.php - Duplicate / similar concern detection
//
// Compares a concern's words with recent concerns so that:
//   - a student is warned while typing that the same problem was already reported, and
//   - an administrator sees possibly related tickets on the ticket page.
//
// Method (keyword overlap, no external service):
//   1. Lower-case the text, drop punctuation and common English/Filipino filler words.
//   2. Reduce simple plurals ("chairs" -> "chair"); keep room numbers such as "304".
//   3. Title words count twice, because the title names the problem.
//   4. Similarity = weighted Dice coefficient = 2 x shared weight / (weight A + weight B),
//      plus a small bonus when both concerns are in the same category.
//   A match needs at least 2 shared keywords and a similarity of DUPLICATE_MIN_SCORE or more.

const DUPLICATE_MIN_SCORE     = 0.30;
const DUPLICATE_LOOKBACK_DAYS = 60;
const DUPLICATE_CATEGORY_BONUS = 0.08;

/** Categories whose concerns are personal: other students never see their titles. */
const DUPLICATE_PRIVATE_CATEGORIES = ['Student Welfare'];

const DUPLICATE_STOPWORDS = [
    // English
    'the', 'and', 'for', 'are', 'was', 'were', 'has', 'have', 'had', 'not', 'but', 'with', 'this', 'that', 'there',
    'from', 'they', 'them', 'their', 'our', 'you', 'your', 'its', 'into', 'about', 'been', 'being', 'very', 'also',
    'can', 'cannot', 'could', 'would', 'should', 'will', 'just', 'still', 'please', 'some', 'any', 'all', 'more',
    'many', 'much', 'when', 'where', 'what', 'which', 'who', 'why', 'how', 'than', 'then', 'too', 'did', 'does',
    'doing', 'done', 'get', 'got', 'need', 'needs', 'since', 'always', 'every', 'again', 'already', 'now', 'today',
    'yesterday', 'week', 'day', 'days', 'issue', 'problem', 'concern', 'request', 'students', 'student', 'school',
    'campus', 'working', 'work', 'one', 'two', 'other', 'because', 'while', 'during', 'near', 'inside', 'like', 'make', 'used', 'using',
    // Filipino / Taglish
    'ang', 'mga', 'yung', 'iyong', 'ito', 'iyan', 'yan', 'dito', 'doon', 'diyan', 'kasi', 'pero', 'para', 'lang',
    'naman', 'po', 'opo', 'din', 'rin', 'siya', 'sila', 'kami', 'tayo', 'namin', 'natin', 'nila', 'niya', 'ako',
    'ko', 'mo', 'sa', 'ng', 'na', 'ay', 'at', 'may', 'wala', 'hindi', 'ba', 'pa', 'nga', 'kung', 'kapag', 'pag',
    'nang', 'nasa', 'mula', 'hanggang', 'talaga', 'sobrang', 'masyado',
];

/**
 * Keyword weights for a concern: title words weigh 2, description words 1.
 * @return array<string,int>
 */
function duplicateKeywords(string $title, string $description): array {
    static $stop = null;
    $stop ??= array_flip(DUPLICATE_STOPWORDS);

    $weights = [];
    foreach ([[$title, 2], [$description, 1]] as [$text, $w]) {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY);
        foreach ($words as $word) {
            $isNumber = ctype_digit($word);
            if (isset($stop[$word]) || (!$isNumber && mb_strlen($word) < 3) || ($isNumber && strlen($word) < 2)) {
                continue;
            }
            // Simple plural folding: chairs -> chair, classes -> class (kept short words intact)
            if (!$isNumber && mb_strlen($word) > 4) {
                if (str_ends_with($word, 'ies')) {
                    $word = mb_substr($word, 0, -3) . 'y';
                } elseif (str_ends_with($word, 'sses')) {
                    $word = mb_substr($word, 0, -2);
                } elseif (str_ends_with($word, 's') && !str_ends_with($word, 'ss')) {
                    $word = mb_substr($word, 0, -1);
                }
            }
            $weights[$word] = max($weights[$word] ?? 0, $w);
        }
    }
    return $weights;
}

/**
 * Similarity of two keyword sets (0..1) and the words they share.
 * @return array{0: float, 1: string[]}
 */
function duplicateSimilarity(array $a, array $b): array {
    $shared = array_intersect_key($a, $b);
    if (count($shared) < 2) {
        return [0.0, []];
    }
    $sharedWeight = 0;
    foreach ($shared as $word => $_) {
        $sharedWeight += min($a[$word], $b[$word]);
    }
    $total = array_sum($a) + array_sum($b);
    $score = $total > 0 ? (2 * $sharedWeight) / $total : 0.0;
    // Most important shared words first (title words, then longer words)
    uksort($shared, fn($x, $y) => [$b[$y] + $a[$y], mb_strlen($y)] <=> [$b[$x] + $a[$x], mb_strlen($x)]);
    return [$score, array_keys($shared)];
}

/**
 * Find concerns similar to the given text.
 *
 * @param int[] $statusIds  statuses to search (default: open = Pending, In Progress)
 * @return array<int, array> best matches first; each row has the concern columns plus
 *                           similarity (0-100) and shared_keywords (string[])
 */
function findSimilarConcerns(PDO $pdo, string $title, string $description, int $categoryId = 0,
                             int $excludeId = 0, int $limit = 3, array $statusIds = [1, 2]): array {
    $mine = duplicateKeywords($title, $description);
    if (count($mine) < 2) {
        return [];
    }

    $in = implode(',', array_map('intval', $statusIds ?: [1, 2]));
    $stmt = $pdo->prepare("
        SELECT c.concern_id, c.ticket_id, c.student_id, c.title, c.description, c.category_id, c.status_id,
               c.priority, c.date_submitted, cat.category_name, st.status_name
        FROM concerns c
        JOIN categories cat ON cat.category_id = c.category_id
        JOIN statuses st ON st.status_id = c.status_id
        WHERE c.status_id IN ({$in})
          AND c.concern_id <> :ex
          AND c.date_submitted >= DATE_SUB(NOW(), INTERVAL " . DUPLICATE_LOOKBACK_DAYS . " DAY)
        ORDER BY c.date_submitted DESC
        LIMIT 500
    ");
    $stmt->execute(['ex' => $excludeId]);

    $matches = [];
    foreach ($stmt as $row) {
        [$score, $shared] = duplicateSimilarity($mine, duplicateKeywords($row['title'], $row['description']));
        if ($score <= 0) {
            continue;
        }
        if ($categoryId > 0 && (int)$row['category_id'] === $categoryId) {
            $score += DUPLICATE_CATEGORY_BONUS;
        }
        if ($score < DUPLICATE_MIN_SCORE) {
            continue;
        }
        unset($row['description']);
        $row['similarity']      = (int)round(min(1, $score) * 100);
        $row['shared_keywords'] = array_slice($shared, 0, 5);
        $matches[] = $row;
    }

    usort($matches, fn($x, $y) => $y['similarity'] <=> $x['similarity']);
    return array_slice($matches, 0, $limit);
}

/**
 * What a student may see about a similar concern. Their own concerns are shown in full
 * (with a link); other students' concerns never reveal who sent them, and personal
 * categories hide the title too.
 */
function duplicateForStudent(array $m, int $studentId): array {
    $own     = (int)$m['student_id'] === $studentId;
    $private = !$own && in_array($m['category_name'], DUPLICATE_PRIVATE_CATEGORIES, true);
    return [
        'own'        => $own,
        'concern_id' => $own ? (int)$m['concern_id'] : null,
        'ticket_id'  => $own ? $m['ticket_id'] : null,
        'title'      => $private ? null : $m['title'],
        'category'   => $m['category_name'],
        'status'     => $m['status_name'],
        'submitted'  => timeAgo($m['date_submitted']),
        'similarity' => $m['similarity'],
        'keywords'   => $private ? [] : $m['shared_keywords'],
    ];
}
