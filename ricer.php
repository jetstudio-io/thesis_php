<?php
include_once 'include/input.php';
include_once 'include/output.php';

const OUT_DIR = 'ricer3/FA';

const RELAY_IWU = 50;

//variate number of sender
for ($nb_sender = 3; $nb_sender < max_node; $nb_sender++) {
    $nb_node = $nb_sender + 2;
    $delay = $e = array_fill(1, $nb_node, 0);
    $nb_pkg_agg = $nb_pkg_max = $nb_agg = array_fill(1, number_sim, 0);
    $nb_pkg_agg_min = array_fill(1, number_sim, $nb_sender);

    //Run 100 simulation for each configuration
    for ($sim = 1; $sim <= number_sim; $sim++) {
        //Get Iwu
        $iwu = getIWU($nb_sender, $sim);
        $iwu[1] = RELAY_IWU;
        $t = 0;
    }
}