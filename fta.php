<?php
include_once 'include/input.php';
include_once 'include/output.php';

const OUT_DIR = 'fta/normal';

const RELAY_IWU = 100;

//variate number of sender
for ($nb_sender = 3; $nb_sender < max_node; $nb_sender++) {
    $nb_node = $nb_sender + 2;
    $delay = $e = array_fill(1, $nb_node, 0);
    $nb_pkg_agg = $nb_pkg_max = $nb_agg = array_fill(1, number_sim, 0);
    $nb_pkg_agg_min = array_fill(1, number_sim, $nb_sender);

    //Run 100 simulation for each configuration
    for ($sim = 1; $sim <= number_sim; $sim++) {
        //Get Iwu
        $twu = getIWU($nb_sender, $sim);
        $twuIdx = array_fill(3, $nb_sender, 0);
        $twu[1] = $twu[2] = array_fill(3, $nb_sender, 0);
        $iwu = array_fill(3, $nb_sender, 100);
        $deltaT = array_fill(3, $nb_sender, 100);
        $t = 0;
        //statistic to calculate energy
        $t_sleep = $t_node[0] = $t_node[1] = $t_node[2] = array_fill(1, $nb_node, 0);
        $idle[1] = array_fill(3, $nb_sender, 0);
        $idle[2] = array_fill(3, $nb_sender, 0);

        while ($t < sim_time) {
            // Check if there is a sender awake
            $isAwake = array_fill(3, $nb_sender, 0);
            $idle[1] = array_fill(3, $nb_sender, 0);

            // Calculate state time for senders
            for ($idx = 3; $idx < $nb_node; $idx++) {
                if ($twu[$twuIdx[$idx]] <= $t) {
                    $isAwake[$idx] = 1;
                    $idle[1][$idx] = $t - $twu[$twuIdx[$idx]];
                    // calculate sleep time
                    $t_node[NODE_SLEEP][$idx] += $twu[$twuIdx[$idx]] - $t_sleep[$idx];
                    // calculate idle as rx time
                    $t_node[NODE_RX][$idx] += $idle[$idx];
                    // calculate idle in waiting slot to send data
                    $t_node[NODE_RX][$idx] += T_CCA + T_WB + ($idx - 3) * T_SLOT;

                    // calculate time in transmission process
                    $t_node[NODE_RX][$idx] += T_CCA + T_CCA + T_ACK;
                    $t_node[NODE_TX][$idx] += T_DATA;

                    // Change back to sleep
                    $node_state[$idx] = NODE_SLEEP;
                    $t_sleep[$idx] = $t + T_CCA + T_WB + ($idx - 2) * T_SLOT;
                }
            }
        }
    }
}