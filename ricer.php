<?php
include_once 'include/input.php';
include_once 'include/output.php';

const OUT_DIR = 'out/ricer3/std';

const RELAY_IWU = 50;

if (!file_exists(OUT_DIR)) {
    mkdir(OUT_DIR, 0777, TRUE);
}

//variate number of sender
for ($nb_sender = 3; $nb_sender < max_node; $nb_sender++) {
    $nb_node = $nb_sender + 2;

    $nb_agg = $nb_pkg_relay = $nb_pkg_agg = $nb_pkg_agg_max = array_fill(1, number_sim, 0);
    $nb_pkg_agg_min = array_fill(1, number_sim, $nb_sender);

    $nb_pkg_recv = $delay = array_fill(1, max_node + 2, array_fill(1, number_sim + 1, 0));

    //Run 100 simulation for each configuration
    for ($sim = 1; $sim <= number_sim; $sim++) {
        //Get Iwu
        //statistic to calculate energy
        $t_sleep = $t_node[0] = $t_node[1] = $t_node[2] = array_fill(1, $nb_node, 0);
        $twu = getIWU($nb_sender, $sim);
        $t = (int)$twu[RELAY_IDX][0];
        $t_sleep[RELAY_IDX] = $t_sleep[DEST_IDX] = $t;
        $twuIdx = array_fill(3, $nb_sender, 0);
        $twu[DEST_IDX] = $twu[RELAY_IDX] = array_fill(3, $nb_sender, $t);
        $iwu = array_fill(3, $nb_sender, 100);
        // queue to store data packet -> calculate the delay
        $queue = [];
        $queueIdx = 0;

        while ($t <= sim_time) {
            /**
             * SENDER
             */
            $nb_node_wakeup = 0;
            for ($idx = 3; $idx <= $nb_node; $idx++) {
                if ($twu[$idx][$twuIdx[$idx]] <= $t) {
                    // calculate sleep time
                    $t_node[NODE_SLEEP][$idx] += $twu[$idx][$twuIdx[$idx]] - $t_sleep[$idx];
                    // calculate idle as rx time
                    $t_node[NODE_RX][$idx] += $t - $twu[$idx][$twuIdx[$idx]];
                    // calculate idle in waiting slot to send data
                    $t_node[NODE_RX][$idx] += T_CCA + T_WB + ($idx - 3) * T_SLOT;

                    // calculate time in transmission process
                    $t_node[NODE_RX][$idx] += T_CCA + T_CCA + T_ACK;
                    $t_node[NODE_TX][$idx] += T_DATA;

                    // Change back to sleep
                    $t_sleep[$idx] = $t + T_CCA + T_WB + ($idx - 2) * T_SLOT;
                    $twuIdx[$idx]++;

                    $nb_node_wakeup++;
                    // Calculate the idle_listening
                    $t_trans = T_CCA + T_WB + $nb_node_wakeup * T_SLOT;
                    //Calculate communication time
                    $t_trans += T_CCA + T_DATA;
                    // store packet in queue
                    $queue[$queueIdx] = ['idx' => $idx, 'time' => $t + $t_trans];
                    $queueIdx++;

                    // update iwu estimated in relay
                    if ($twuIdx[$idx] > 2) {
                        $iwu[$idx] = $twu[$idx][$twuIdx[$idx]] - $twu[$idx][$twuIdx[$idx] - 1];
                        $twu[RELAY_IDX][$idx] = $twu[$idx][$twuIdx[$idx]];
                    } else {
                        $twu[RELAY_IDX][$idx] += $iwu[$idx];
                    }
                }
            }
        }
    }
}