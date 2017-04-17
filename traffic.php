<?php
function generateTraffic($simTime, $numberChange, $fixedInit) {
    $segment = $simTime / ($numberChange + 1);
    $twu = array_fill(1, 510, 0);
    if ($fixedInit > 0) {
        $t = $fixedInit;
    } else {
        $t = rand(1, 50);
    }
    $twu[1] = $t;
    $change_idx = 1;
    $wu_idx = 2;
    $iwu = rand(30, 100) * 10;
    while ($t < $simTime) {
        if ($t >= $segment * $change_idx) {
            if ($fixedInit != 0) {
                $iwu = $fixedInit;
            } else {
                $iwu = rand(30, 100) * 10;
            }
            $change_idx = $change_idx + 1;
        }
        $t = $t + $iwu;
        $twu[$wu_idx] = $t;
        $wu_idx = $wu_idx + 1;
    }
    $twu[$wu_idx] = $t + $iwu;
    return $twu;
}

for ($max_node = 3; $max_node <= 13; $max_node++) {
    $dir = "data/" . $max_node;
    if (!file_exists($dir)) {
        mkdir($dir, 0777, true);
    }
    for ($sim = 0; $sim < 100; $sim++) {
        $file = $dir . "/" . ($sim + 1) . ".csv";
        $t = rand(1, 50);
        file_put_contents($file, $t . "\n", FILE_APPEND);
        $t = rand(1, 50);
        file_put_contents($file, $t . "\n", FILE_APPEND);
        for ($node = 0; $node < $max_node; $node++) {
            $iwu = generateTraffic(100000, 0, 0);
            $row = implode(",", $iwu) . "\n";
            file_put_contents($file, $row, FILE_APPEND);
        }
    }
}
