<?php

require_once __DIR__ . '/lib/connect.php';

$name = 'Rasberry2016';

/*
 * ------------------------------------------------------------
 * Get the user's existing Mii URL
 * ------------------------------------------------------------
 */

$stmt = $dbc->prepare(
    'SELECT user_face FROM users WHERE user_name = ? LIMIT 1'
);

$stmt->bind_param('s', $name);
$stmt->execute();

$user = $stmt->get_result()->fetch_assoc();

if (!$user || empty($user['user_face'])) {
    exit('No Mii URL found.');
}

$originalUrl = $user['user_face'];

$parts = parse_url($originalUrl);

if (empty($parts['query'])) {
    exit('Mii URL has no query string.');
}

parse_str($parts['query'], $query);

if (empty($query['data'])) {
    exit('Mii URL has no data parameter.');
}

/*
 * ------------------------------------------------------------
 * Decode the original Wii U Mii
 * ------------------------------------------------------------
 */

$mii = base64_decode($query['data'], true);

if ($mii === false) {
    exit('The Mii data is not valid Base64.');
}

if (strlen($mii) !== 96) {
    exit(
        'Expected 96-byte Wii U Mii data. Got ' .
        strlen($mii) .
        ' bytes.'
    );
}

/*
 * ------------------------------------------------------------
 * Convert Wii U / 3DS Mii data to Studio data
 *
 * Based on MiiToStudio.fu.
 * ------------------------------------------------------------
 */

$studio = array_fill(0, 46, 0);

$b = function (int $offset) use ($mii): int {
    return ord($mii[$offset]);
};


/*
 * Face
 */

$studio[0x00] = ($b(0x42) >> 3) & 7;
$studio[0x01] = $b(0x42) & 7;
$studio[0x02] = $b(0x2f);

$studio[0x03] = $b(0x35) >> 5;

$studio[0x04] =
    (($b(0x35) & 1) << 2) |
    ($b(0x34) >> 6);

$studio[0x05] = $b(0x36) & 0x1f;
$studio[0x06] = ($b(0x35) >> 1) & 0xf;
$studio[0x07] = $b(0x34) & 0x3f;

$studio[0x08] =
    (($b(0x37) & 1) << 3) |
    ($b(0x36) >> 5);

$studio[0x09] = ($b(0x37) >> 1) & 0x1f;

$studio[0x0a] = ($b(0x39) >> 4) & 7;
$studio[0x0b] = $b(0x38) >> 5;
$studio[0x0c] = $b(0x3a) & 0x1f;
$studio[0x0d] = $b(0x39) & 0xf;
$studio[0x0e] = $b(0x38) & 0x1f;

$studio[0x0f] =
    (($b(0x3b) & 1) << 3) |
    ($b(0x3a) >> 5);

$studio[0x10] = ($b(0x3b) >> 1) & 0x1f;


/*
 * Facial positioning
 */

$studio[0x11] = $b(0x30) >> 5;
$studio[0x12] = $b(0x31) >> 4;
$studio[0x13] = ($b(0x30) >> 1) & 0xf;
$studio[0x14] = $b(0x31) & 0xf;

$studio[0x15] = ($b(0x19) >> 2) & 0xf;
$studio[0x16] = $b(0x18) & 1;


/*
 * Glasses
 */

$studio[0x17] = ($b(0x44) >> 4) & 7;

$studio[0x18] =
    (($b(0x45) & 7) * 2) |
    ($b(0x44) >> 7);

$studio[0x19] = $b(0x44) & 0xf;
$studio[0x1a] = $b(0x45) >> 3;


/*
 * Hair / facial hair / other
 */

$studio[0x1b] = $b(0x33) & 7;
$studio[0x1c] = ($b(0x33) >> 3) & 1;
$studio[0x1d] = $b(0x32);
$studio[0x1e] = $b(0x2e);

$studio[0x1f] = ($b(0x46) >> 1) & 0xf;
$studio[0x20] = $b(0x46) & 1;

$studio[0x21] =
    (($b(0x47) & 3) << 3) |
    ($b(0x46) >> 5);

$studio[0x22] =
    ($b(0x47) >> 2) & 0x1f;


/*
 * Remaining fields
 */

$studio[0x23] = $b(0x3f) >> 5;

$studio[0x24] =
    (($b(0x3f) & 1) << 2) |
    ($b(0x3e) >> 6);

$studio[0x25] = ($b(0x3f) >> 1) & 0xf;
$studio[0x26] = $b(0x3e) & 0x3f;

$studio[0x27] = $b(0x40) & 0x1f;

$studio[0x28] =
    (($b(0x43) & 3) << 2) |
    ($b(0x42) >> 6);

$studio[0x29] = $b(0x40) >> 5;
$studio[0x2a] = ($b(0x43) >> 2) & 0x1f;

$studio[0x2b] =
    (($b(0x3d) & 1) << 3) |
    ($b(0x3c) >> 5);

$studio[0x2c] = $b(0x3c) & 0x1f;
$studio[0x2d] = ($b(0x3d) >> 1) & 0x1f;


/*
 * ------------------------------------------------------------
 * ConvertFieldsVer3ToNx()
 * ------------------------------------------------------------
 */

if ($studio[0x1b] === 0) {
    $studio[0x1b] = 8;
}

if ($studio[0x00] === 0) {
    $studio[0x00] = 8;
}

if ($studio[0x0b] === 0) {
    $studio[0x0b] = 8;
}

$studio[0x24] += 19;
$studio[0x04] += 8;

if ($studio[0x17] === 0) {
    $studio[0x17] = 8;
} elseif ($studio[0x17] < 6) {
    $studio[0x17] += 13;
}

if ($studio[0x02] > 127) {
    $studio[0x02] = 127;
}

if ($studio[0x1e] > 127) {
    $studio[0x1e] = 127;
}


/*
 * ------------------------------------------------------------
 * Studio URL encoder
 * ------------------------------------------------------------
 *
 * Studio:
 *   46 bytes raw
 *   ↓
 *   47 bytes obfuscated
 *   ↓
 *   hexadecimal
 *
 * The first byte is the seed.
 */

function encodeStudio(array $studio): string
{
    $urlData = array_fill(0, 47, 0);

    $urlData[0] = 0;

    for ($i = 0; $i < 46; $i++) {

        $value =
            $studio[$i] ^
            $urlData[$i];

        $urlData[$i + 1] =
            (7 + $value) & 0xff;
    }

    $hex = '';

    foreach ($urlData as $byte) {
        $hex .= sprintf('%02x', $byte);
    }

    return $hex;
}


/*
 * ------------------------------------------------------------
 * Make an image URL
 * ------------------------------------------------------------
 */

function makeMiiUrl(array $studio): string
{
    $hex = encodeStudio($studio);

    return
        'https://mii-unsecure.ariankordi.net/miis/image.png' .
        '?data=' . $hex .
        '&width=270&type=face';
}


/*
 * ------------------------------------------------------------
 * IMPORTANT
 *
 * These are deliberately separate test values.
 *
 * We are NOT changing the database.
 * We are NOT changing printFace().
 * ------------------------------------------------------------
 */

$tests = array(
    0 => 'Expression 0',
    1 => 'Expression 1',
    2 => 'Expression 2',
    3 => 'Expression 3',
    4 => 'Expression 4',
    5 => 'Expression 5',
    6 => 'Expression 6',
    7 => 'Expression 7'
);


/*
 * ------------------------------------------------------------
 * HTML
 * ------------------------------------------------------------
 */

?>
<!DOCTYPE html>
<html>
<head>

<meta charset="UTF-8">

<title>Cedar 2 Mii Expression Test</title>

<style>

body {
    margin: 30px;
    background: #f5f5f5;
    color: #222;
    font-family: Arial, sans-serif;
}

h1 {
    margin-bottom: 5px;
}

.info {
    margin-bottom: 25px;
}

.grid {
    display: grid;
    grid-template-columns: repeat(4, 270px);
    gap: 30px;
}

.card {
    background: white;
    border-radius: 10px;
    padding: 15px;
    box-sizing: border-box;
    box-shadow: 0 2px 8px rgba(0,0,0,.12);
}

.card h2 {
    margin-top: 0;
    text-align: center;
}

.card img {
    width: 270px;
    height: 270px;
    display: block;
    object-fit: contain;
}

.url {
    margin-top: 12px;
    padding: 8px;
    background: #eee;
    font-size: 10px;
    line-height: 1.4;
    word-break: break-all;
}

.notice {
    margin-top: 25px;
    padding: 15px;
    background: #fff;
    border-left: 4px solid #888;
}

</style>

</head>

<body>

<h1>Cedar 2 Mii Expression Test</h1>

<div class="info">

    <p>
        User:
        <strong>
            <?= htmlspecialchars($name, ENT_QUOTES) ?>
        </strong>
    </p>

    <p>
        Original Mii:
        <strong>96 bytes</strong>
    </p>

    <p>
        Database:
        <strong>NOT MODIFIED</strong>
    </p>

</div>

<div class="grid">

<?php foreach ($tests as $expression => $label): ?>

<?php

    /*
     * Copy the converted Mii for this test.
     */
    $testStudio = $studio;

    /*
     * Expression is Studio byte 0.
     */
    $testStudio[0] = $expression;

    $imageUrl = makeMiiUrl($testStudio);

?>

<div class="card">

    <h2>
        <?= htmlspecialchars($label, ENT_QUOTES) ?>
    </h2>

    <img
        src="<?= htmlspecialchars($imageUrl, ENT_QUOTES) ?>"
        alt="<?= htmlspecialchars($label, ENT_QUOTES) ?>"
    >

    <div class="url">
        <?= htmlspecialchars($imageUrl, ENT_QUOTES) ?>
    </div>

</div>

<?php endforeach; ?>

</div>

<div class="notice">

    <strong>Safe test:</strong>

    This page only reads your existing
    <code>user_face</code> value.

    It does not update the database and does not modify
    <code>printFace()</code>.

</div>

</body>
</html>
