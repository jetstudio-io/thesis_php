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
        // Destination & relay node is well synchronized
        $twu[DEST_IDX][0] = $t + T_CCA + T_WB + T_SLOT * $nb_sender + T_CCA;
        $twuIdx = array_fill(3, $nb_sender, 0);
        //$iwu = array_fill(3, $nb_sender, 100);
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
                    // calculate idle in waiting slot to send data - the slot idx = node idx
                    $t_node[NODE_RX][$idx] += T_CCA + T_WB + ($idx - 3) * T_SLOT;

                    // calculate time in transmission process
                    $t_node[NODE_RX][$idx] += T_CCA + T_CCA + T_ACK;
                    $t_node[NODE_TX][$idx] += T_DATA;

                    // Change back to sleep
                    $t_sleep[$idx] = $t + T_CCA + T_WB + ($idx - 2) * T_SLOT;
                    $twuIdx[$idx]++;

                    $nb_node_wakeup++;

                    // Calculate the idle_listening
                    $t_trans = T_CCA + T_WB + ($idx - 2) * T_SLOT - T_CCA - T_ACK;
                    // store packet in queue
                    $queue[$queueIdx] = ['idx' => $idx, 'time' => $t + $t_trans];
                    $queueIdx++;
                }
            }

            /**
             * RELAY part 1: receive data
             */
            //Sleep time
            $t_node[NODE_SLEEP][RELAY_IDX] += $t - $t_sleep[RELAY_IDX];
            //CCA before send WB
            $t_node[NODE_RX][RELAY_IDX] += T_CCA;
            //Send WB
            $t_node[NODE_TX][RELAY_IDX] += T_WB;
            //n data slot
            $t_node[NODE_RX][RELAY_IDX] += (T_CCA + T_DATA + T_CCA) * $nb_sender;
            $t_node[NODE_TX][RELAY_IDX] += T_ACK * $nb_sender;
            //update simulation time
            $t += T_CCA + T_WB + T_SLOT * $nb_sender;

            //If relay node receipt data packets
            if (count($queue)) {
                //idle listening time in waiting WB from destination
                $t_node[NODE_RX][RELAY_IDX] += $twu[DEST_IDX] - $t;
                // update current simulation time
                $t = $twu[DEST_IDX];

                //RELAY: receive WB
                $t_node[NODE_RX][RELAY_IDX] += T_CCA + T_WB;
                // send data packets to destination
                $t_node[NODE_RX][RELAY_IDX] += (T_CCA + T_CCA + T_ACK) * count($queue);
                $t_node[NODE_TX][RELAY_IDX] += T_DATA * count($queue);

                // go to sleep
                $t_sleep[RELAY_IDX] = $t + T_CCA + T_WB + (T_CCA + T_CCA + T_ACK) * count($queue);
                $twu[RELAY_IDX][0] += RELAY_IWU;

                //DESTINATION:
                // Sleep time
                $t_node[NODE_SLEEP][DEST_IDX] += $t - $t_sleep[DEST_IDX];
                // CCA before send WB
                $t_node[NODE_RX][DEST_IDX] += T_CCA;
                // send WB
                $t_node[NODE_TX][DEST_IDX] += T_WB;
                // receive data packets
                $t_node[NODE_RX][DEST_IDX] += (T_CCA + T_DATA + T_CCA) * count($queue);
                // go to sleep
                $t_sleep[DEST_IDX] = $t + T_CCA + T_WB + (T_CCA + T_CCA + T_ACK) * count($queue) + T_DATA;
                $twu[DEST_IDX][0] += RELAY_IWU;
            } else {
                // RELAY : go to sleep
                $t_sleep[RELAY_IDX] = $t;
                $twu[RELAY_IDX][0] += RELAY_IWU;

                // DESTINATION
                $t_node[NODE_SLEEP][DEST_IDX];
            }
        }
    }
}