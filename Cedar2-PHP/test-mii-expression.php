<?php
require_once 'lib/connect.php';

$name = 'Rasberry2016';

$stmt = $dbc->prepare(
    'SELECT user_face FROM users WHERE user_name = ? LIMIT 1'
);
$stmt->bind_param('s', $name);
$stmt->execute();

$user = $stmt->get_result()->fetch_assoc();

if (!$user || empty($user['user_face'])) {
    exit('No Mii URL found.');
}

$url = $user['user_face'];

$parts = parse_url($url);
parse_str($parts['query'] ?? '', $query);

if (empty($query['data'])) {
    exit('No Mii data found.');
}

$mii = base64_decode($query['data'], true);

if ($mii === false || strlen($mii) !== 96) {
    exit('Invalid 96-byte Mii data.');
}

/*
 * Wii U / 3DS FFLStoreData:
 *
 * 0x42 bits 5-3 = expression
 */
$expression = (ord($mii[0x42]) >> 3) & 7;

echo '<h1>Mii Expression Test</h1>';

echo '<p>User: <b>' . htmlspecialchars($name) . '</b></p>';

echo '<p>Expression: <b>' . $expression . '</b></p>';

echo '<p>Source byte 0x42: <b>0x' .
    strtoupper(bin2hex($mii[0x42])) .
    '</b></p>';

echo '<h2>Current Mii</h2>';

echo '<img src="' .
    htmlspecialchars($url, ENT_QUOTES) .
    '" width="270">';

echo '<p>Database was <b>NOT</b> modified.</p>';
