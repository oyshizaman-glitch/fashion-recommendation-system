<?php
// Preference-aware outfit suggestion engine
include_once __DIR__ . '/db.php';

function get_suggestions_for_user($user_id, $limit = 6) {
    global $conn;

    // fetch user preferences (free text or comma-separated)
    $user_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT preferences FROM users WHERE id = " . intval($user_id) . " LIMIT 1"));
    $prefs_raw = trim($user_row['preferences'] ?? '');
    $tokens = [];
    if ($prefs_raw !== '') {
        $tokens = array_filter(array_map('trim', preg_split('/[,;\s]+/', $prefs_raw)));
    }

    $results = [];

    // Fetch candidate items with average rating
    $sql = "SELECT i.*, COALESCE(AVG(r.rating),0) as avg_rating FROM items i LEFT JOIN ratings r ON r.item_id = i.id GROUP BY i.id";
    $res = mysqli_query($conn, $sql);
    while ($row = mysqli_fetch_assoc($res)) {
        $results[$row['id']] = $row;
    }

    // If no preferences given, return top-rated items
    if (count($tokens) === 0) {
        usort($results, function($a,$b){ return ($b['avg_rating'] ?? 0) <=> ($a['avg_rating'] ?? 0); });
        return array_slice(array_values($results), 0, $limit);
    }

    // Score items by matching tokens to structured fields
    $scored = [];
    foreach ($results as $it) {
        $score = 0;
        $name = strtolower($it['name'] ?? '');
        $category = strtolower($it['category'] ?? '');
        $description = strtolower($it['description'] ?? '');
        $brand = strtolower($it['brand'] ?? '');
        $color = strtolower($it['color'] ?? '');
        $season = strtolower($it['season'] ?? '');

        foreach ($tokens as $t) {
            $tok = strtolower($t);
            if ($tok === $category) $score += 6;
            if ($tok === $brand) $score += 5;
            if ($tok === $color) $score += 4;
            if ($tok === $season) $score += 3;
            if (strpos($name, $tok) !== false) $score += 2;
            if (strpos($description, $tok) !== false) $score += 1;
        }

        // boost by average rating
        $avg = floatval($it['avg_rating'] ?? 0);
        $score += intval(round($avg * 2));

        if ($score > 0) {
            $it['score'] = $score;
            $scored[] = $it;
        }
    }

    // Sort by score desc then rating
    usort($scored, function($a,$b){
        $sd = ($b['score'] ?? 0) <=> ($a['score'] ?? 0);
        if ($sd !== 0) return $sd;
        return ($b['avg_rating'] ?? 0) <=> ($a['avg_rating'] ?? 0);
    });

    return array_slice($scored, 0, $limit);
}

?>
