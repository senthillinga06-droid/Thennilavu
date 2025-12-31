<?php
// porutham_real.php
// Real 12-Porutham implementation (practical/traditional rules).
// Save and open in a PHP-enabled server (XAMPP, LAMP, etc.).

date_default_timezone_set('Asia/Colombo');

// -----------------------------
// Mapping of new 1001-1054 values to original 27 nakshatras
// -----------------------------
$nakshatra_mapping = [
    1001 => 1,   // அஸ்வினி -> Ashwini
    1002 => 2,   // பரணி -> Bharani
    1003 => 3,   // கார்த்திகை 1ம் பாதம் -> Krittika
    1004 => 3,   // கார்த்திகை 2ம் பாதம் -> Krittika
    1005 => 3,   // கார்த்திகை 3ம் பாதம் -> Krittika
    1006 => 3,   // கார்த்திகை 4ம் பாதம் -> Krittika
    1007 => 4,   // ரோகிணி -> Rohini
    1008 => 5,   // மிருகசீரிடம் 1ம் பாதம் -> Mrigashirsha
    1009 => 5,   // மிருகசீரிடம் 2ம் பாதம் -> Mrigashirsha
    1010 => 5,   // மிருகசீரிடம் 3ம் பாதம் -> Mrigashirsha
    1011 => 5,   // மிருகசீரிடம் 4ம் பாதம் -> Mrigashirsha
    1012 => 6,   // திருவாதிரை -> Ardra
    1013 => 7,   // புனர்பூசம் 1ம் பாதம் -> Punarvasu
    1014 => 7,   // புனர்பூசம் 2ம் பாதம் -> Punarvasu
    1015 => 7,   // புனர்பூசம் 3ம் பாதம் -> Punarvasu
    1016 => 7,   // புனர்பூசம் 4ம் பாதம் -> Punarvasu
    1017 => 8,   // பூசம் -> Pushya
    1018 => 9,   // ஆயிலியம் -> Ashlesha
    1019 => 10,  // மகம் -> Magha
    1020 => 11,  // பூரம் -> Purva Phalguni
    1021 => 12,  // உத்தரம் 1ம் பாதம் -> Uttara Phalguni
    1022 => 12,  // உத்தரம் 2ம் பாதம் -> Uttara Phalguni
    1023 => 12,  // உத்தரம் 3ம் பாதம் -> Uttara Phalguni
    1024 => 12,  // உத்தரம் 4ம் பாதம் -> Uttara Phalguni
    1025 => 13,  // அஸ்தம் -> Hasta
    1026 => 14,  // சித்திரை 1ம் பாதம் -> Chitra
    1027 => 14,  // சித்திரை 2ம் பாதம் -> Chitra
    1028 => 14,  // சித்திரை 3ம் பாதம் -> Chitra
    1029 => 14,  // சித்திரை 4ம் பாதம் -> Chitra
    1030 => 15,  // சுவாதி -> Swati
    1031 => 16,  // விசாகம் 1ம் பாதம் -> Vishakha
    1032 => 16,  // விசாகம் 2ம் பாதம் -> Vishakha
    1033 => 16,  // விசாகம் 3ம் பாதம் -> Vishakha
    1034 => 16,  // விசாகம் 4ம் பாதம் -> Vishakha
    1035 => 17,  // அனுஷம் -> Anuradha
    1036 => 18,  // கேட்டை -> Jyeshtha
    1037 => 19,  // மூலம் -> Mula
    1038 => 20,  // பூராடம் -> Purva Ashadha
    1039 => 21,  // உத்திராடம் 1ம் பாதம் -> Uttara Ashadha
    1040 => 21,  // உத்திராடம் 2ம் பாதம் -> Uttara Ashadha
    1041 => 21,  // உத்திராடம் 3ம் பாதம் -> Uttara Ashadha
    1042 => 21,  // உத்திராடம் 4ம் பாதம் -> Uttara Ashadha
    1043 => 22,  // திருவோணம் -> Shravana
    1044 => 23,  // அவிட்டம் 1 ம் பாதம் -> Dhanishtha
    1045 => 23,  // அவிட்டம் 2ம் பாதம் -> Dhanishtha
    1046 => 23,  // அவிட்டம் 3ம் பாதம் -> Dhanishtha
    1047 => 23,  // அவிட்டம் 4ம் பாதம் -> Dhanishtha
    1048 => 24,  // சதயம் -> Shatabhisha
    1049 => 25,  // பூரட்டாதி 1ம் பாதம் -> Purva Bhadrapada
    1050 => 25,  // பூரட்டாதி 2ம் பாதம் -> Purva Bhadrapada
    1051 => 25,  // பூரட்டாதி 3ம் பாதம் -> Purva Bhadrapada
    1052 => 25,  // பூரட்டாதி 4ம் பாதம் -> Purva Bhadrapada
    1053 => 26,  // உத்திரட்டாதி -> Uttara Bhadrapada
    1054 => 27,  // ரேவதி -> Revati
];

// Tamil names for display
$tamil_names = [
    1001 => 'அஸ்வினி',
    1002 => 'பரணி',
    1003 => 'கார்த்திகை 1ம் பாதம்',
    1004 => 'கார்த்திகை 2ம் பாதம்',
    1005 => 'கார்த்திகை 3ம் பாதம்',
    1006 => 'கார்த்திகை 4ம் பாதம்',
    1007 => 'ரோகிணி',
    1008 => 'மிருகசீரிடம் 1ம் பாதம்',
    1009 => 'மிருகசீரிடம் 2ம் பாதம்',
    1010 => 'மிருகசீரிடம் 3ம் பாதம்',
    1011 => 'மிருகசீரிடம் 4ம் பாதம்',
    1012 => 'திருவாதிரை',
    1013 => 'புனர்பூசம் 1ம் பாதம்',
    1014 => 'புனர்பூசம் 2ம் பாதம்',
    1015 => 'புனர்பூசம் 3ம் பாதம்',
    1016 => 'புனர்பூசம் 4ம் பாதம்',
    1017 => 'பூசம்',
    1018 => 'ஆயிலியம்',
    1019 => 'மகம்',
    1020 => 'பூரம்',
    1021 => 'உத்தரம் 1ம் பாதம்',
    1022 => 'உத்தரம் 2ம் பாதம்',
    1023 => 'உத்தரம் 3ம் பாதம்',
    1024 => 'உத்தரம் 4ம் பாதம்',
    1025 => 'அஸ்தம்',
    1026 => 'சித்திரை 1ம் பாதம்',
    1027 => 'சித்திரை 2ம் பாதம்',
    1028 => 'சித்திரை 3ம் பாதம்',
    1029 => 'சித்திரை 4ம் பாதம்',
    1030 => 'சுவாதி',
    1031 => 'விசாகம் 1ம் பாதம்',
    1032 => 'விசாகம் 2ம் பாதம்',
    1033 => 'விசாகம் 3ம் பாதம்',
    1034 => 'விசாகம் 4ம் பாதம்',
    1035 => 'அனுஷம்',
    1036 => 'கேட்டை',
    1037 => 'மூலம்',
    1038 => 'பூராடம்',
    1039 => 'உத்திராடம் 1ம் பாதம்',
    1040 => 'உத்திராடம் 2ம் பாதம்',
    1041 => 'உத்திராடம் 3ம் பாதம்',
    1042 => 'உத்திராடம் 4ம் பாதம்',
    1043 => 'திருவோணம்',
    1044 => 'அவிட்டம் 1 ம் பாதம்',
    1045 => 'அவிட்டம் 2ம் பாதம்',
    1046 => 'அவிட்டம் 3ம் பாதம்',
    1047 => 'அவிட்டம் 4ம் பாதம்',
    1048 => 'சதயம்',
    1049 => 'பூரட்டாதி 1ம் பாதம்',
    1050 => 'பூரட்டாதி 2ம் பாதம்',
    1051 => 'பூரட்டாதி 3ம் பாதம்',
    1052 => 'பூரட்டாதி 4ம் பாதம்',
    1053 => 'உத்திரட்டாதி',
    1054 => 'ரேவதி',
];

// -----------------------------
// 27 Nakshatra dataset (complete)
// Each entry has: id, en, ta, rasi, rasi_lord, gana, yoni, nadi, varna, rajju
// -----------------------------
$nakshatras = [
    1 => ['id'=>1,'en'=>'Ashwini','ta'=>'அஸ்வினி','rasi'=>'Mesha','rasi_lord'=>'Mars','gana'=>'Deva','yoni'=>'Horse','nadi'=>'Adi','varna'=>'Kshatriya','rajju'=>1],
    2 => ['id'=>2,'en'=>'Bharani','ta'=>'பரணி','rasi'=>'Mesha','rasi_lord'=>'Venus','gana'=>'Manushya','yoni'=>'Elephant','nadi'=>'Madhya','varna'=>'Shudra','rajju'=>2],
    3 => ['id'=>3,'en'=>'Krittika','ta'=>'கிருத்திகை','rasi'=>'Mesha','rasi_lord'=>'Sun','gana'=>'Rakshasa','yoni'=>'Sheep','nadi'=>'Antya','varna'=>'Kshatriya','rajju'=>3],
    4 => ['id'=>4,'en'=>'Rohini','ta'=>'ரோஹிணி','rasi'=>'Vrishabha','rasi_lord'=>'Venus','gana'=>'Manushya','yoni'=>'Serpent','nadi'=>'Madhya','varna'=>'Brahmin','rajju'=>4],
    5 => ['id'=>5,'en'=>'Mrigashirsha','ta'=>'மிருகசீரிடம்','rasi'=>'Mithuna','rasi_lord'=>'Mercury','gana'=>'Deva','yoni'=>'Deer','nadi'=>'Adi','varna'=>'Kshatriya','rajju'=>5],
    6 => ['id'=>6,'en'=>'Ardra','ta'=>'திருவாதிரை','rasi'=>'Mithuna','rasi_lord'=>'Rahu','gana'=>'Rakshasa','yoni'=>'Dog','nadi'=>'Adi','varna'=>'Shudra','rajju'=>1],
    7 => ['id'=>7,'en'=>'Punarvasu','ta'=>'புனர்பூசம்','rasi'=>'Mithuna','rasi_lord'=>'Jupiter','gana'=>'Deva','yoni'=>'Cat','nadi'=>'Madhya','varna'=>'Brahmin','rajju'=>2],
    8 => ['id'=>8,'en'=>'Pushya','ta'=>'பூசம்','rasi'=>'Karka','rasi_lord'=>'Saturn','gana'=>'Deva','yoni'=>'Goat','nadi'=>'Madhya','varna'=>'Brahmin','rajju'=>3],
    9 => ['id'=>9,'en'=>'Ashlesha','ta'=>'ஆயில்யம்','rasi'=>'Karka','rasi_lord'=>'Mercury','gana'=>'Rakshasa','yoni'=>'Serpent','nadi'=>'Antya','varna'=>'Shudra','rajju'=>4],
    10 => ['id'=>10,'en'=>'Magha','ta'=>'மகம்','rasi'=>'Simha','rasi_lord'=>'Ketu','gana'=>'Rakshasa','yoni'=>'Rat','nadi'=>'Adi','varna'=>'Kshatriya','rajju'=>5],
    11 => ['id'=>11,'en'=>'Purva Phalguni','ta'=>'பூரம்','rasi'=>'Simha','rasi_lord'=>'Sun','gana'=>'Manushya','yoni'=>'Rat','nadi'=>'Madhya','varna'=>'Vaishya','rajju'=>1],
    12 => ['id'=>12,'en'=>'Uttara Phalguni','ta'=>'உத்திரம்','rasi'=>'Kanya','rasi_lord'=>'Sun','gana'=>'Manushya','yoni'=>'Cow','nadi'=>'Antya','varna'=>'Vaishya','rajju'=>2],
    13 => ['id'=>13,'en'=>'Hasta','ta'=>'அஸ்தம்','rasi'=>'Kanya','rasi_lord'=>'Moon','gana'=>'Deva','yoni'=>'Buffalo','nadi'=>'Adi','varna'=>'Brahmin','rajju'=>3],
    14 => ['id'=>14,'en'=>'Chitra','ta'=>'சித்திரை','rasi'=>'Thula','rasi_lord'=>'Mars','gana'=>'Rakshasa','yoni'=>'Tiger','nadi'=>'Madhya','varna'=>'Kshatriya','rajju'=>4],
    15 => ['id'=>15,'en'=>'Swati','ta'=>'சுவாதி','rasi'=>'Thula','rasi_lord'=>'Rahu','gana'=>'Deva','yoni'=>'Buffalo','nadi'=>'Antya','varna'=>'Vaishya','rajju'=>5],
    16 => ['id'=>16,'en'=>'Vishakha','ta'=>'விசாகம்','rasi'=>'Thula','rasi_lord'=>'Jupiter','gana'=>'Rakshasa','yoni'=>'Tiger','nadi'=>'Adi','varna'=>'Kshatriya','rajju'=>1],
    17 => ['id'=>17,'en'=>'Anuradha','ta'=>'அனுஷம்','rasi'=>'Vrischika','rasi_lord'=>'Saturn','gana'=>'Deva','yoni'=>'Tiger','nadi'=>'Madhya','varna'=>'Brahmin','rajju'=>2],
    18 => ['id'=>18,'en'=>'Jyeshtha','ta'=>'கேட்டை','rasi'=>'Vrischika','rasi_lord'=>'Mercury','gana'=>'Rakshasa','yoni'=>'Deer','nadi'=>'Antya','varna'=>'Kshatriya','rajju'=>3],
    19 => ['id'=>19,'en'=>'Mula','ta'=>'முழுசரம்','rasi'=>'Dhanus','rasi_lord'=>'Ketu','gana'=>'Rakshasa','yoni'=>'Dog','nadi'=>'Adi','varna'=>'Shudra','rajju'=>4],
    20 => ['id'=>20,'en'=>'Purva Ashadha','ta'=>'பூராடம்','rasi'=>'Dhanus','rasi_lord'=>'Venus','gana'=>'Manushya','yoni'=>'Elephant','nadi'=>'Madhya','varna'=>'Vaishya','rajju'=>5],
    21 => ['id'=>21,'en'=>'Uttara Ashadha','ta'=>'உத்திராடம்','rasi'=>'Makara','rasi_lord'=>'Sun','gana'=>'Manushya','yoni'=>'Elephant','nadi'=>'Antya','varna'=>'Vaishya','rajju'=>1],
    22 => ['id'=>22,'en'=>'Shravana','ta'=>'திருவோணம்','rasi'=>'Makara','rasi_lord'=>'Moon','gana'=>'Deva','yoni'=>'Deer','nadi'=>'Adi','varna'=>'Brahmin','rajju'=>2],
    23 => ['id'=>23,'en'=>'Dhanishtha','ta'=>'அவிட்டம்','rasi'=>'Kumbha','rasi_lord'=>'Mars','gana'=>'Manushya','yoni'=>'Fox','nadi'=>'Madhya','varna'=>'Shudra','rajju'=>3],
    24 => ['id'=>24,'en'=>'Shatabhisha','ta'=>'சதயம்','rasi'=>'Kumbha','rasi_lord'=>'Saturn','gana'=>'Rakshasa','yoni'=>'Horse','nadi'=>'Antya','varna'=>'Shudra','rajju'=>4],
    25 => ['id'=>25,'en'=>'Purva Bhadrapada','ta'=>'பூரட்டாதி','rasi'=>'Meena','rasi_lord'=>'Jupiter','gana'=>'Manushya','yoni'=>'Horse','nadi'=>'Adi','varna'=>'Vaishya','rajju'=>5],
    26 => ['id'=>26,'en'=>'Uttara Bhadrapada','ta'=>'உத்திரட்டாதி','rasi'=>'Meena','rasi_lord'=>'Saturn','gana'=>'Deva','yoni'=>'Horse','nadi'=>'Madhya','varna'=>'Brahmin','rajju'=>1],
    27 => ['id'=>27,'en'=>'Revati','ta'=>'ரேவதி','rasi'=>'Meena','rasi_lord'=>'Mercury','gana'=>'Deva','yoni'=>'Elephant','nadi'=>'Antya','varna'=>'Brahmin','rajju'=>2],
];

// safe helpers
function getNak($id, $nakshatras) {
    $id = intval($id);
    return $nakshatras[$id] ?? null;
}

function getOriginalNakId($new_id) {
    global $nakshatra_mapping;
    return $nakshatra_mapping[$new_id] ?? null;
}

function posDiff27($from, $to) {
    // returns distance from 'from' to 'to' in 1..27 (if same returns 27)
    $d = ($to - $from) % 27;
    if ($d <= 0) $d += 27;
    return $d; // 1..27
}

function mod9_safe($v) {
    $r = $v % 9;
    if ($r < 0) $r += 9;
    return $r;
}

// -----------------------------
// Porutham rules implementations
// -----------------------------

// 1. Dina (தினப்பொருத்தம்)
// Common practical rule: d = (girl - boy) mod 9; acceptable values {1,2,4,6,8}
function porutham_dina($boy_id, $girl_id) {
    $d = mod9_safe($girl_id - $boy_id);
    $ok = [1,2,4,6,8];
    return in_array($d, $ok, true);
}

// 2. Gana (கணப் பொருத்தம்)
// Gana types: Deva, Manushya, Rakshasa
function porutham_gana($boy, $girl) {
    $b = ($boy['gana'] ?? '');
    $g = ($girl['gana'] ?? '');
    // rules: Deva with Deva/Manushya ok; Manushya with Manushya ok; Rakshasa with Rakshasa ok
    if ($b === $g) return true;
    if ($b === 'Deva' && $g === 'Manushya') return true;
    if ($b === 'Manushya' && $g === 'Deva') return true;
    return false;
}

// 3. Mahendra (மகேந்திரப்பொருத்தம்)
// Girl should be 4,7,10,13,16,19,22,25 ahead of boy
function porutham_mahendra($boy_id, $girl_id) {
    $d = posDiff27($boy_id, $girl_id);
    $ok = [4,7,10,13,16,19,22,25];
    return in_array($d, $ok, true);
}

// 4. Stree Deergha / Stree porutham (ஸ்திரி)
// Use a commonly used practical rule: distance >= 7 (i.e. not too close). Many calculators use tables; this is a practical rule.
function porutham_stree($boy_id, $girl_id) {
    $d = posDiff27($boy_id, $girl_id);
    return ($d >= 7);
}

// 5. Yoni (யோனி)
// We'll map all 27 nakshatras to standard yoni animals and use incompatible pairs table
$yoni_incompat_pairs = [
    // common incompatible pairs (bidirectional)
    ['Horse','Elephant'],
    ['Tiger','Cow'],
    ['Dog','Cat'],
    // (this list is conservative; most calculators use extensive table — these are typical incompatibilities)
];
function porutham_yoni($boy, $girl) {
    global $yoni_incompat_pairs;
    $by = $boy['yoni'] ?? '';
    $gy = $girl['yoni'] ?? '';
    foreach ($yoni_incompat_pairs as $p) {
        if (($by === $p[0] && $gy === $p[1]) || ($by === $p[1] && $gy === $p[0])) return false;
    }
    return true;
}

// 6. Vedha (வேதை) - inimical nakshatra pairs
$vedha_pairs = [
    ['Krittika','Vishakha'],
    ['Ashwini','Jyeshtha'],
    ['Rohini','Swati'],
    ['Bharani','Anuradha'],
    ['Punarvasu','Uttara Ashadha'],
    ['Ardra','Shravana'],
    ['Pushya','Purva Ashadha'],
    ['Ashlesha','Mula'],
    ['Magha','Revati'],
    // if you want more pairs, extend here
];
function porutham_vedha($boy, $girl) {
    global $vedha_pairs;
    $be = strtolower($boy['en'] ?? '');
    $ge = strtolower($girl['en'] ?? '');
    foreach ($vedha_pairs as $p) {
        if (($be === strtolower($p[0]) && $ge === strtolower($p[1])) || ($be === strtolower($p[1]) && $ge === strtolower($p[0]))) {
            return false;
        }
    }
    return true;
}

// 7. Rajju (ரஜ்ஜி) - use rajju group (1..5) - same group => BAD
function porutham_rajju($boy, $girl) {
    return ($boy['rajju'] ?? '') !== ($girl['rajju'] ?? '');
}

// 8. Rasi porutham (ராசி)
// Practical simple rule: same rasi considered compatible
function porutham_rasi($boy, $girl) {
    return (($boy['rasi'] ?? '') === ($girl['rasi'] ?? ''));
}

// 9. Rasi Adhipati (ராசி அதிபதி) - compare rasi lords
function porutham_rasi_adhipati($boy, $girl) {
    $bl = strtolower($boy['rasi_lord'] ?? '');
    $gl = strtolower($girl['rasi_lord'] ?? '');
    return ($bl === $gl);
}

// 10. Vasya (வசியம்) - use a practical rasi-pair table (common pairs)
$vasya_good_pairs = [
    ['Mesha','Simha'], ['Mesha','Dhanus'],
    ['Vrishabha','Kanya'], ['Vrishabha','Makara'],
    ['Mithuna','Thula'], ['Mithuna','Kumbha'],
    ['Karka','Vrischika'], ['Karka','Meena'],
    ['Simha','Mesha'], ['Kanya','Vrishabha'],
    ['Thula','Mithuna'], ['Vrischika','Karka'],
    ['Dhanus','Mesha'], ['Makara','Vrishabha'],
    ['Kumbha','Mithuna'], ['Meena','Karka'],
];
function porutham_vasya($boy, $girl) {
    global $vasya_good_pairs;
    $br = $boy['rasi'] ?? '';
    $gr = $girl['rasi'] ?? '';
    if ($br === $gr) return true;
    foreach ($vasya_good_pairs as $p) {
        if (($br === $p[0] && $gr === $p[1]) || ($br === $p[1] && $gr === $p[0])) return true;
    }
    return false;
}

// 11. Nadi (நாடி) - three nadis: Adi / Madhya / Antya. Same nadi => BAD
function porutham_nadi($boy, $girl) {
    return (($boy['nadi'] ?? '') !== ($girl['nadi'] ?? ''));
}

// 12. Varna (வர்ணம்) - Brahmin(4) > Kshatriya(3) > Vaishya(2) > Shudra(1)
// Condition: Boy varna rank >= Girl varna rank => GOOD
$varna_rank = ['Brahmin'=>4,'Kshatriya'=>3,'Vaishya'=>2,'Shudra'=>1];
function porutham_varna($boy, $girl) {
    global $varna_rank;
    $br = $boy['varna'] ?? '';
    $gr = $girl['varna'] ?? '';
    $bval = $varna_rank[$br] ?? 0;
    $gval = $varna_rank[$gr] ?? 0;
    return ($bval >= $gval);
}

// -----------------------------
// Porutham evaluation aggregator
// -----------------------------
function evaluate_all_poruthams($boy_new_id, $girl_new_id, $nakshatras) {
    // Convert new IDs to original IDs
    $boy_orig_id = getOriginalNakId($boy_new_id);
    $girl_orig_id = getOriginalNakId($girl_new_id);
    
    if (!$boy_orig_id || !$girl_orig_id) return null;
    
    $boy = getNak($boy_orig_id, $nakshatras);
    $girl = getNak($girl_orig_id, $nakshatras);
    
    if (!$boy || !$girl) return null;

    $res = [];
    $res['Dina'] = porutham_dina($boy['id'], $girl['id']);
    $res['Gana'] = porutham_gana($boy, $girl);
    $res['Mahendra'] = porutham_mahendra($boy['id'], $girl['id']);
    $res['Stree'] = porutham_stree($boy['id'], $girl['id']);
    $res['Yoni'] = porutham_yoni($boy, $girl);
    $res['Vedha'] = porutham_vedha($boy, $girl);
    $res['Rajju'] = porutham_rajju($boy, $girl);
    $res['Rasi'] = porutham_rasi($boy, $girl);
    $res['RasiAdhipati'] = porutham_rasi_adhipati($boy, $girl);
    $res['Vasya'] = porutham_vasya($boy, $girl);
    $res['Nadi'] = porutham_nadi($boy, $girl);
    $res['Varna'] = porutham_varna($boy, $girl);

    return [
        'boy_new_id' => $boy_new_id,
        'girl_new_id' => $girl_new_id,
        'boy' => $boy,
        'girl' => $girl,
        'porutham' => $res
    ];
}

// -----------------------------
// Tamil labels for poruthams
// -----------------------------
$tamil_porutham = [
    'Dina'=>'தினப்பொருத்தம்',
    'Gana'=>'கணப்பொருத்தம்',
    'Mahendra'=>'மகேந்திரப்பொருத்தம்',
    'Stree'=>'ஸ்திரி பொருத்தம்',
    'Yoni'=>'யோனி பொருத்தம்',
    'Vedha'=>'வேதைப்பொருத்தம்',
    'Rajju'=>'ரஜ்ஜிப்பொருத்தம்',
    'Rasi'=>'ராசி பொருத்தம்',
    'RasiAdhipati'=>'ராசி அதிபதி பொருத்தம்',
    'Vasya'=>'வசிய பொருத்தம்',
    'Nadi'=>'நாடி பொருத்தம்',
    'Varna'=>'வர்ண பொருத்தம்',
];

// -----------------------------
// Handle automatic processing (NO FORM NEEDED)
// -----------------------------
$show = false;
$out = null;
$score = 0;

// Get values from GET parameters or your data source
// Example: porutham_real.php?boy_id=1005&girl_id=1001
$boy_id = isset($_GET['boy_id']) ? intval($_GET['boy_id']) : 1005;  // Default to Male (1005)
$girl_id = isset($_GET['girl_id']) ? intval($_GET['girl_id']) : 1001;  // Default to Female (1001)

// You can also hardcode your values here:
// $boy_id = 1005;  // Male nakshatra
// $girl_id = 1001;  // Female nakshatra

$out = evaluate_all_poruthams($boy_id, $girl_id, $nakshatras);
if ($out !== null) {
    $show = true;
    $score = count(array_filter($out['porutham']));
}

// -----------------------------
// HTML display (Tamil UI)
// -----------------------------
?>
<!doctype html>
<html lang="ta">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>திருமண பொருத்தம் — 12 Porutham (மெய்நிகர்)</title>
<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }
    :root {
        --primary: #fd2c79;
        --primary-dark: #c2185b;
        --primary-contrast: #ffffff;
    }

    body {
        font-family: "Latha", "Kumbh Sans", "Noto Sans Tamil", Tahoma, Arial;
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        padding: 0;
        min-height: 100vh;
        color: #2c3e50;
    }
    
    .container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
    }
    
    .card {
        background: white;
        border-radius: 16px;
        padding: 30px;
        margin: 20px auto;
        box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        border: 1px solid #e1e5eb;
        transition: transform 0.3s ease;
    }
    
    .card:hover {
        transform: translateY(-5px);
    }
    
    h1 {
        color: #2c3e50;
        text-align: center;
        margin-bottom: 20px;
        font-size: 2.2rem;
        position: relative;
        padding-bottom: 15px;
    }
    
    h1:after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 50%;
        transform: translateX(-50%);
        width: 120px;
        height: 4px;
        background: linear-gradient(90deg, var(--primary), var(--primary-dark));
        border-radius: 2px;
    }
    
    .subtitle {
        text-align: center;
        color: #666;
        margin-bottom: 30px;
        font-size: 1.1rem;
        line-height: 1.6;
    }
    
    .info-box {
        background: linear-gradient(135deg, #fff0f6 0%, #fff6fb 100%);
        border-left: 6px solid var(--primary);
        padding: 20px;
        margin: 25px 0;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    }
    
    .info-title {
        font-size: 1.3rem;
        color: #2c3e50;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .info-title i {
        color: #8b0000;
    }
    
    .couple-info {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 25px;
        margin-top: 15px;
    }
    
    .person-card {
        background: white;
        padding: 20px;
        border-radius: 12px;
        border: 2px solid;
        box-shadow: 0 6px 15px rgba(0,0,0,0.05);
    }
    
    .person-card.male {
        border-color: var(--primary);
    }
    
    .person-card.female {
        border-color: var(--primary-dark);
    }
    
    .person-title {
        font-size: 1.2rem;
        font-weight: bold;
        margin-bottom: 15px;
        color: #2c3e50;
    }
    
    .person-detail {
        margin: 8px 0;
        display: flex;
        justify-content: space-between;
        padding-bottom: 8px;
        border-bottom: 1px dashed #eee;
    }
    
    table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        margin: 25px 0;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 6px 15px rgba(0,0,0,0.08);
    }
    
    th {
        background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 100%);
        color: var(--primary-contrast);
        padding: 18px 15px;
        font-weight: 600;
        text-align: center;
        border: none;
    }
    
    td {
        padding: 16px 15px;
        border-bottom: 1px solid #eef2f7;
        text-align: center;
        transition: background-color 0.2s;
    }
    
    tr:nth-child(even) {
        background-color: #f9fafb;
    }
    
    tr:hover td {
        background-color: #f0f7ff;
    }
    
    .ok {
        color: #27ae60;
        font-weight: 700;
        background: rgba(39, 174, 96, 0.1);
        padding: 6px 15px;
        border-radius: 20px;
        display: inline-block;
        min-width: 120px;
    }
    
    .bad {
        color: #e74c3c;
        font-weight: 700;
        background: rgba(231, 76, 60, 0.1);
        padding: 6px 15px;
        border-radius: 20px;
        display: inline-block;
        min-width: 120px;
    }
    
    .score-box {
        text-align: center;
        background: linear-gradient(135deg, #fff7fb 0%, #fff0f6 100%);
        padding: 25px;
        border-radius: 16px;
        margin: 30px 0;
        border: 2px solid var(--primary);
    }
    
    .score-value {
        font-size: 3.5rem;
        font-weight: 800;
        color: #2c3e50;
        margin: 10px 0;
        text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
    }
    
    .score-total {
        font-size: 1.5rem;
        color: #666;
    }
    
    .result-message {
        text-align: center;
        padding: 20px;
        border-radius: 12px;
        margin: 25px 0;
        font-size: 1.2rem;
        font-weight: 600;
    }
    
    .result-good {
        background: rgba(39, 174, 96, 0.15);
        color: #27ae60;
        border: 2px solid #27ae60;
    }
    
    .result-average {
        background: rgba(243, 156, 18, 0.15);
        color: #f39c12;
        border: 2px solid #f39c12;
    }
    
    .result-poor {
        background: rgba(231, 76, 60, 0.15);
        color: #e74c3c;
        border: 2px solid #e74c3c;
    }
    
    .form-section {
        background: #f8f9fa;
        padding: 25px;
        border-radius: 16px;
        margin-top: 30px;
        border: 1px solid #dee2e6;
    }
    
    .form-title {
        font-size: 1.4rem;
        margin-bottom: 20px;
        color: #2c3e50;
        text-align: center;
    }
    
    .form-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 25px;
        margin-bottom: 25px;
    }
    
    .form-group {
        margin-bottom: 20px;
    }
    
    label {
        display: block;
        margin-bottom: 10px;
        font-weight: 600;
        color: #2c3e50;
    }
    
    select {
        width: 100%;
        padding: 14px;
        border: 2px solid #ced4da;
        border-radius: 10px;
        font-size: 1rem;
        font-family: inherit;
        background: white;
        color: #495057;
        transition: all 0.3s;
        cursor: pointer;
    }
    
    select:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(253, 44, 121, 0.2);
    }
    
    .btn-group {
        display: flex;
        gap: 15px;
        justify-content: center;
        flex-wrap: wrap;
        margin-top: 25px;
    }
    
    .btn {
        padding: 14px 32px;
        border: none;
        border-radius: 10px;
        font-size: 1.1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        min-width: 200px;
    }
    
    .btn-primary {
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        color: var(--primary-contrast);
    }
    
    .btn-primary:hover {
        transform: translateY(-3px);
        box-shadow: 0 7px 20px rgba(253, 44, 121, 0.18);
    }
    
    .btn-secondary {
        background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
        color: white;
    }
    
    .btn-secondary:hover {
        transform: translateY(-3px);
        box-shadow: 0 7px 20px rgba(108, 117, 125, 0.3);
    }
    
    .btn-back {
        background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 100%);
        color: var(--primary-contrast);
    }
    
    .btn-back:hover {
        transform: translateY(-3px);
        box-shadow: 0 7px 20px rgba(253, 44, 121, 0.18);
    }
    
    .disclaimer {
        background: rgba(255, 244, 229, 0.8);
        border: 1px solid #ffd8a6;
        padding: 20px;
        border-radius: 12px;
        margin-top: 30px;
        font-size: 0.95rem;
        color: #666;
        line-height: 1.7;
    }
    
    .disclaimer strong {
        color: #e67e22;
    }
    
    @media (max-width: 768px) {
        .container {
            padding: 15px;
        }
        
        .card {
            padding: 20px;
            margin: 10px auto;
        }
        
        h1 {
            font-size: 1.8rem;
        }
        
        .couple-info {
            grid-template-columns: 1fr;
        }
        
        .btn {
            min-width: 100%;
        }
        
        .btn-group {
            flex-direction: column;
        }
        
        table {
            display: block;
            overflow-x: auto;
        }
    }
    
    .highlight {
        color: var(--primary-dark);
        font-weight: bold;
    }
</style>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
<div class="container">
    <div class="card">
        <h1><i class="fas fa-heart"></i> திருமண பொருத்தம் — 12 பொருத்தங்கள்</h1>
        

        <div class="info-box">
            
            <div class="couple-info">
                <div class="person-card male">
                    <div class="person-title"><i class="fas fa-mars"></i> மணமகன் விவரங்கள்</div>
                    <div class="person-detail">
                        <span>நட்சத்திரம்:</span>
                        <span class="highlight"><?= htmlspecialchars($tamil_names[$boy_id] ?? 'Unknown') ?></span>
                    </div>
                </div>
                
                <div class="person-card female">
                    <div class="person-title"><i class="fas fa-venus"></i> மணப்பெண் விவரங்கள்</div>
                    <div class="person-detail">
                        <span>நட்சத்திரம்:</span>
                        <span class="highlight"><?= htmlspecialchars($tamil_names[$girl_id] ?? 'Unknown') ?></span>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($show && $out): 
            $boy = $out['boy'];
            $girl = $out['girl'];
            $por = $out['porutham'];
        ?>
            

            
            <table>
                <tr>
                    <th>விவரம்</th>
                    <th>மணமகன்</th>
                    <th>மணப்பெண்</th>
                </tr>
                <tr>
                    <td><strong>நட்சத்திரம்</strong></td>
                    <td><?= htmlspecialchars($tamil_names[$out['boy_new_id']]) ?><br>
                        <small>(<?= htmlspecialchars($boy['en']) ?>)</small></td>
                    <td><?= htmlspecialchars($tamil_names[$out['girl_new_id']]) ?><br>
                        <small>(<?= htmlspecialchars($girl['en']) ?>)</small></td>
                </tr>
                <tr>
                    <td><strong>ராசி</strong></td>
                    <td><?= htmlspecialchars($boy['rasi']) ?></td>
                    <td><?= htmlspecialchars($girl['rasi']) ?></td>
                </tr>
                <tr>
                    <td><strong>ராசி அதிபதி</strong></td>
                    <td><?= htmlspecialchars($boy['rasi_lord']) ?></td>
                    <td><?= htmlspecialchars($girl['rasi_lord']) ?></td>
                </tr>
                <tr>
                    <td><strong>யோனி (மிருகம்)</strong></td>
                    <td><span class="highlight"><?= htmlspecialchars($boy['yoni']) ?></span></td>
                    <td><span class="highlight"><?= htmlspecialchars($girl['yoni']) ?></span></td>
                </tr>
                <tr>
                    <td><strong>கணம்</strong></td>
                    <td><?= htmlspecialchars($boy['gana']) ?></td>
                    <td><?= htmlspecialchars($girl['gana']) ?></td>
                </tr>
                <tr>
                    <td><strong>நாடி</strong></td>
                    <td><?= htmlspecialchars($boy['nadi']) ?></td>
                    <td><?= htmlspecialchars($girl['nadi']) ?></td>
                </tr>
                <tr>
                    <td><strong>வர்ணம்</strong></td>
                    <td><?= htmlspecialchars($boy['varna']) ?></td>
                    <td><?= htmlspecialchars($girl['varna']) ?></td>
                </tr>
            </table>

            <table>
                <tr>
                    <th>#</th>
                    <th>பொருத்தம்</th>
                    <th>முடிவு</th>
                </tr>
                <?php 
                $i=1; 
                $statusColors = ['success' => '#27ae60', 'warning' => '#f39c12', 'danger' => '#e74c3c'];
                foreach ($por as $k => $v): 
                    $status = $v ? 'பொருந்தும்' : 'பொருந்தாது';
                    $statusClass = $v ? 'ok' : 'bad';
                ?>
                    <tr>
                        <td><strong><?= $i++ ?></strong></td>
                        <td><strong><?= $tamil_porutham[$k] ?? $k ?></strong></td>
                        <td style="display:flex; justify-content:center; align-items:center;" class="<?= $statusClass ?>"><?= $status ?></td>
                        
                    </tr>
                <?php endforeach; ?>
            </table>

            <div class="result-message <?php 
                if ($score >= 8) echo 'result-good';
                elseif ($score >= 5) echo 'result-average';
                else echo 'result-poor';
            ?>">
                <?php
                   
                        echo '<strong>முடிவு:</strong> மொத்த பொருத்த மதிப்பெண்: ' . $score . '/12';

                ?>
            </div>
            
           
            

            <div class="disclaimer">
                <p><strong><i class="fas fa-exclamation-triangle"></i> குறிப்பு:</strong> இந்த கருவி AI-சார்ந்த முன்னேற்றத்துடன் உருவாக்கப்பட்ட ஒரு மெய்நிகர் (virtual) பொருத்தம் சோதனையாகும். இது முன்னோக்கு முடிவுகளை வழங்குவதற்காக வடிவமைக்கப்பட்டுள்ளது. உண்மையான ஜோதிட ஆலோசனைகளுக்கு சான்றிதழ் பெற்ற ஜோதிடர்களுடன் கலந்தாலோசிக்கவும்.</p>
            </div>
            
        <?php else: ?>
            <div class="result-message result-poor">
                <i class="fas fa-exclamation-circle"></i><br>
                பொருத்தம் கணக்கிட முடியவில்லை. தயவு செய்து சரியான நட்சத்திரம் தேர்வு செய்யவும்.
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Optional: You can use this to automatically trigger the porutham calculation
// when you have the values from your JavaScript
function calculatePoruthamAuto(boyNakshatraId, girlNakshatraId) {
    // Redirect to this page with the parameters
    window.location.href = 'porutham_real.php?boy_id=' + boyNakshatraId + '&girl_id=' + girlNakshatraId;
}

// Add some interactive effects
document.addEventListener('DOMContentLoaded', function() {
    // Add animation to score
    const scoreValue = document.querySelector('.score-value');
    if (scoreValue) {
        scoreValue.style.opacity = '0';
        scoreValue.style.transform = 'scale(0.8)';
        
        setTimeout(() => {
            scoreValue.style.transition = 'all 0.8s ease';
            scoreValue.style.opacity = '1';
            scoreValue.style.transform = 'scale(1)';
        }, 300);
    }
    
    // Table row hover effects
    const tableRows = document.querySelectorAll('tr');
    tableRows.forEach(row => {
        row.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
        });
        
        row.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
    
    // Back button functionality
    const backBtn = document.querySelector('.btn-back');
    if (backBtn) {
        backBtn.addEventListener('click', function() {
            window.location.href = 'mem.php';
        });
    }
});
</script>
</body>
</html>