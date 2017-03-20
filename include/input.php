<?php
function getIWU($nb_sender, $sim) {
    $file = "data/" . $nb_sender . "/" . $sim . ".csv";
    $fileHandle = fopen($file, "r");
    $twu = array();
    $twu[1] = fgetcsv($fileHandle, null, ",", "\n");
    $twu[2] = fgetcsv($fileHandle, null, ",", "\n");
    for ($idx = 3; $idx <= $nb_sender + 2; $idx++) {
        $row = fgetcsv($fileHandle, null, ",", "\n");
        $twu[$idx] = $row;
    }
    return $twu;
}

const Ptx = 17.4 * 3.3; //power in mW
const Prx = 18.8 * 3.3; //power in mW
const Psleep = 0.03 * 3.3;

const NODE_SLEEP = 0;
const NODE_RX = 1;
const NODE_TX = 2;

const L_WB = 7;
const L_DATA = 18;
const L_ACK = 11;
const bitrate = 250;
const T_WB = L_WB * 8 / bitrate;
const T_DATA = L_DATA * 8 / bitrate;
const T_ACK = L_ACK * 8 / bitrate;
const T_CCA = T_WB;
const T_SLOT = T_CCA + T_DATA + T_CCA + T_ACK;

// Config for simulation
const max_node = 5;
const number_sim = 100;
const sim_time = 100000; //100s
const delta_t = 10;