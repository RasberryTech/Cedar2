<?php

$pnid = 'Rasberry2016';

$folder = __DIR__ . '/assets/mii/' . $pnid;

if (!is_dir($folder)) {
    mkdir($folder, 0755, true);
}

/*
 * Your working Mii Studio data.
 */
$data = 'AwAAQFKbdKIjRRIy37N%2FzcpKAF0dDgAAOCtoAGEAcABwAHkAaQBuAGcAbwB0AG82AAAhAxJGohohQqMUgQ4GiAwAACkAUkhQAABhAHMAdABlAGMAaAAAAAAAAAAAABXu';

$feelings = [
    0 => 'normal',
    1 => 'happy',
    2 => 'like',
    3 => 'surprised',
    4 => 'frustrated',
    5 => 'puzzled'
];

foreach ($feelings as $feeling => $name) {

    /*
     * TEST ONLY:
     * For now we use the known-working renderer request.
     * Expression handling will be added after the request format
     * is verified.
     */
    $url =
        'https://mii-unsecure.ariankordi.net/miis/image.png' .
        '?data=' . $data .
        '&type=face' .
        '&width=270' .
        '&shaderType=wiiu';

    $filename = $folder . '/' . $name . '_face.png';

    $image = @file_get_contents($url);

    if ($image === false) {
        echo "FAILED: $name\n";
        continue;
    }

    file_put_contents($filename, $image);

    echo "CREATED: $filename\n";
}

echo "\nDone.\n";
