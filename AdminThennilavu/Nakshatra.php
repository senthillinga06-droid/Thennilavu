<?php
// porutham_real.php
// Real 12-Porutham implementation (practical/traditional rules).
// Save and open in a PHP-enabled server (XAMPP, LAMP, etc.).

date_default_timezone_set('Asia/Colombo');

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
function evaluate_all_poruthams($boy_id, $girl_id, $nakshatras) {
    $boy = getNak($boy_id, $nakshatras);
    $girl = getNak($girl_id, $nakshatras);
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

    return ['boy'=>$boy,'girl'=>$girl,'porutham'=>$res];
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
// Handle POST submission
// -----------------------------
$show = false;
$out = null;
$score = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $boy_id = intval($_POST['boy_id'] ?? 0);
    $girl_id = intval($_POST['girl_id'] ?? 0);
    $out = evaluate_all_poruthams($boy_id, $girl_id, $nakshatras);
    if ($out !== null) {
        $show = true;
        $score = count(array_filter($out['porutham']));
    }
}

// -----------------------------
// HTML display (Tamil UI)
// -----------------------------
?>
<!doctype html>
<html lang="ta">
<head>
<meta charset="utf-8">
<title>திருமண பொருத்தம் — 12 Porutham (மெய்நிகர்)</title>
<style>
    body { font-family: "Latha", Tahoma, Arial; background:#fafafa; padding:18px; }
    .card { background:white; max-width:980px; margin:20px auto; padding:18px; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,0.06);}
    h1,h2 { margin:8px 0; }
    form select, form button { padding:8px 10px; font-size:15px; }
    table { width:100%; border-collapse:collapse; margin-top:12px; }
    th,td { border:1px solid #ddd; padding:10px; text-align:center; }
    th { background:#f2f2f2; font-weight:700; }
    .ok{ color:green; font-weight:700;}
    .bad{ color:red; font-weight:700;}
    .muted{ color:#666; font-size:14px; }
</style>
</head>
<body>
<div class="card">
    <h1>🔯 திருமண பொருத்தம் — 12 பொருத்தங்கள்</h1>
    <p class="muted">நட்சத்திரம் (27) அடிப்படையில் பாரம்பரிய விதிகளால் கணக்கிடப்பட்டது.</p>

    <form method="post" style="margin-top:12px;">
        <label>மணமகன் நட்சத்திரம்</label><br>
        <select name="boy_id" required>
            <option value="">-- தேர்வு செய்யவும் --</option>
            <?php foreach ($nakshatras as $n): ?>
                <option value="<?= $n['id'] ?>" <?= (isset($out) && $out && $out['boy']['id']==$n['id']) ? 'selected' : '' ?>>
                    <?= $n['id'] ?>. <?= htmlspecialchars($n['ta']) ?> (<?= htmlspecialchars($n['en']) ?>)
                </option>
            <?php endforeach; ?>
        </select>

        &nbsp;&nbsp;

        <label>மணப்பெண் நட்சத்திரம்</label><br>
        <select name="girl_id" required>
            <option value="">-- தேர்வு செய்யவும் --</option>
            <?php foreach ($nakshatras as $n): ?>
                <option value="<?= $n['id'] ?>" <?= (isset($out) && $out && $out['girl']['id']==$n['id']) ? 'selected' : '' ?>>
                    <?= $n['id'] ?>. <?= htmlspecialchars($n['ta']) ?> (<?= htmlspecialchars($n['en']) ?>)
                </option>
            <?php endforeach; ?>
        </select>

        <br><br>
        <button type="submit">திருமண பொருத்தம் பார்க்க</button>
    </form>

    <?php if ($show && $out): 
        $boy = $out['boy'];
        $girl = $out['girl'];
        $por = $out['porutham'];
    ?>
        <h2>🧾 நட்சத்திரம் — விவரம்</h2>
        <table>
            <tr>
                <th>விவரம்</th>
                <th>மணமகன்</th>
                <th>மணப்பெண்</th>
            </tr>
            <tr>
                <td>நட்சத்திரம்</td>
                <td><?= htmlspecialchars($boy['ta']) ?> (<?= htmlspecialchars($boy['en']) ?>)</td>
                <td><?= htmlspecialchars($girl['ta']) ?> (<?= htmlspecialchars($girl['en']) ?>)</td>
            </tr>
            <tr>
                <td>ராசி</td>
                <td><?= htmlspecialchars($boy['rasi']) ?></td>
                <td><?= htmlspecialchars($girl['rasi']) ?></td>
            </tr>
            <tr>
                <td>ராசி அதிபதி</td>
                <td><?= htmlspecialchars($boy['rasi_lord']) ?></td>
                <td><?= htmlspecialchars($girl['rasi_lord']) ?></td>
            </tr>
            <tr>
                <td>மிருகம் / யோனி</td>
                <td><?= htmlspecialchars($boy['yoni']) ?></td>
                <td><?= htmlspecialchars($girl['yoni']) ?></td>
            </tr>
            <tr>
                <td>கணம் / Gana</td>
                <td><?= htmlspecialchars($boy['gana']) ?></td>
                <td><?= htmlspecialchars($girl['gana']) ?></td>
            </tr>
            <tr>
                <td>நாடி</td>
                <td><?= htmlspecialchars($boy['nadi']) ?></td>
                <td><?= htmlspecialchars($girl['nadi']) ?></td>
            </tr>
            <tr>
                <td>வர்ணம்</td>
                <td><?= htmlspecialchars($boy['varna']) ?></td>
                <td><?= htmlspecialchars($girl['varna']) ?></td>
            </tr>
        </table>

        <h2>📋 12 பொருத்தம் முடிவுகள்</h2>
        <table>
            <tr><th>#</th><th>பொருத்தம்</th><th>முடிவு</th></tr>
            <?php $i=1; foreach ($por as $k => $v): ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td><?= $tamil_porutham[$k] ?? $k ?></td>
                    <td class="<?= $v ? 'ok' : 'bad' ?>"><?= $v ? 'பொருந்தும்' : 'பொருந்தாது' ?></td>
                </tr>
            <?php endforeach; ?>
        </table>

        <h3>🔢 பொருத்தம் மதிப்பு: <strong><?= $score ?>/12</strong></h3>

        <?php
            // short textual guidance:
            if ($score >= 8) {
                echo '<p class="ok"><strong>முடிவு:</strong> பொருத்தம் நல்லது (திருமணத்திற்குப் பொருத்தமான அமைவு).</p>';
            } elseif ($score >= 5) {
                echo '<p class="muted"><strong>முடிவு:</strong> சில பொருத்தங்கள் இல்லை — மேலும் விவாதிக்கவும்.</p>';
            } else {
                echo '<p class="bad"><strong>முடிவு:</strong> பல முக்கிய பொருத்தங்கள் இல்லை — கவனிக்க வேண்டியது.</p>';
            }
        ?>
    <?php endif; ?>

    <hr>
    <p class="muted">குறிப்பு: இந்த கருவி பொதுவாகப் பயன்படுத்தப்படும் வரையறைகளை அடிப்படையாகக் கொண்டு உருவாக்கப்பட்டது — பலவும் பண்டைய தமிழ்/சாஸ்திர பண்டிகைகளில் சிறிய வேறுபாடுகள் ஏற்படக்கூடும். நிர்வகிக்க விரும்பினால், துய்யமான குறிப்புகளுக்காக பாடத்தில் அடிப்படை அட்டவணைகளை மாற்றலாம்.</p>
</div>
</body>
</html>
