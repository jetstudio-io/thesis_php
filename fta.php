<?php
include_once 'include/input.php';
include_once 'include/output.php';

const OUT_DIR = 'fta/normal';

const RELAY_IWU = 100;
const RELAY_IDX = 2;
const DEST_IDX = 1;

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
        $twu[DEST_IDX] = $twu[RELAY_IDX] = array_fill(3, $nb_sender, 0);
        $iwu = array_fill(3, $nb_sender, 100);
        $deltaT = array_fill(3, $nb_sender, 100);
        $t = 0;
        //statistic to calculate energy
        $t_sleep = $t_node[0] = $t_node[1] = $t_node[2] = array_fill(1, $nb_node, 0);

        while ($t < sim_time) {
            /**
             * SENDER
             */
            $nb_wakeup = 0;
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

                    $nb_wakeup++;

                    // update iwu estimated in relay
                    if ($twuIdx[$idx] > 2) {
                        $iwu[$idx] = $twu[$idx][$twuIdx[$idx]] - $twu[$idx][$twuIdx[$idx] - 1];
                        $twu[RELAY_IDX][$idx] = $twu[$idx][$twuIdx[$idx]];
                    } else {
                        $twu[RELAY_IDX][$idx] += $iwu[$idx];
                    }
                } else {
                    $twu[RELAY_IDX][$idx] += $iwu[$idx];
                }
            }

            /**
             * RECEIVER
             */
            // sleep time
            $t_trans = 0;
            $t_node[NODE_SLEEP][RELAY_IDX] += $t - $t_sleep[RELAY_IDX];
            // CCA
            $t_node[NODE_RX][RELAY_IDX] += T_CCA;
            $t_trans += T_CCA;
            // send WB
            $t_node[NODE_TX][RELAY_IDX] += T_WB;
            $t_trans += T_WB;
            // CCA, receive DATA, CCA
            $t_node[NODE_RX][RELAY_IDX] += (T_CCA + T_DATA + T_CCA) * $nb_wakeup;
            $t_trans += (T_CCA + T_DATA + T_CCA) * $nb_wakeup;
            // send ACK
            $t_node[NODE_TX][RELAY_IDX] += T_ACK * $nb_wakeup;
            $t_trans += T_ACK * $nb_wakeup;
            // wait for new node join network
            $t_node[NODE_RX][RELAY_IDX] += T_DATA;
            $t_trans += T_DATA;

            // send big data file to destination
            if ($nb_wakeup > 0) {
                $t_data_agg = (L_DATA + ($nb_wakeup - 1) * (L_DATA - 7)) * 8 / bitrate;
                $t_node[NODE_RX][RELAY_IDX] += T_CCA + T_WB + T_CCA + T_CCA + T_ACK;
                $t_node[NODE_TX][RELAY_IDX] += $t_data_agg;
            }

            /**
             * DESTINATION
             */
            if ($nb_wakeup > 0) {
                $t_data_agg = (L_DATA + ($nb_wakeup - 1) * (L_DATA - 7)) * 8 / bitrate;
                $t_node[NODE_SLEEP][DEST_IDX] += $t + $t_trans - $t_sleep[DEST_IDX];
                $t_node[NODE_RX][DEST_IDX] += T_CCA + T_CCA + $t_data_agg + T_CCA;
                $t_node[NODE_TX][DEST_IDX] += T_WB + T_ACK;
                $t_trans += T_CCA + T_WB + T_CCA + $t_data_agg + T_CCA + T_ACK;
            } else {

            }
            $t_sleep[DEST_IDX] = $t_sleep[RELAY_IDX] = $t + $t_trans;

            $t = min($twu[RELAY_IDX]);
        }

        /**
         * ENERGY
         */

        /**
         * DELAY
         */
    }
}