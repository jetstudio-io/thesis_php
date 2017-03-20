<?php
function generateTraffic($simTime, $numberChange, $fixedIWU) {
    $segment = $simTime / ($numberChange + 1);
    $twu = array_fill(1, 510, 0);
    $t = rand(5, 30);
    $twu[1] = $t;
    $change_idx = 1;
    $wu_idx = 2;
    if ($fixedIWU != 0) {
        $iwu = $fixedIWU;
    } else {
        $iwu = rand(20, 100) * 10;
    }
    while ($t < $simTime) {
        if ($t >= $segment * $change_idx) {
            if ($fixedIWU != 0) {
                $iwu = $fixedIWU;
            } else {
                $iwu = rand(20, 100) * 10;
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

for ($max_node = 3; $max_node <= 15; $max_node++) {
    $dir = "data/" . $max_node;
    if (!file_exists($dir)) {
        mkdir($dir, 0777, true);
    }
    for ($sim = 0; $sim < 100; $sim++) {
        $file = $dir . "/" . ($sim + 1) . ".csv";
        $t = rand(10, 40);
        file_put_contents($file, ($t + 7) . "\n", FILE_APPEND);
        file_put_contents($file, $t . "\n", FILE_APPEND);
        for ($node = 0; $node < $max_node; $node++) {
            $iwu = generateTraffic(100000, 0, 0);
            $row = implode(",", $iwu) . "\n";
            file_put_contents($file, $row, FILE_APPEND);
        }
    }
}
