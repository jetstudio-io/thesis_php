<?php
include_once 'include/input.php';
include_once 'include/output.php';

const OUT_DIR = 'out/fta/dda/';

const RELAY_IWU = 100;
const DELTA_MAX = 30;

if (!file_exists(OUT_DIR)) {
    mkdir(OUT_DIR, 0777, TRUE);
}
$agg_ratio = $agg_avg = $delay_avg = $e_avg = array_fill(1, max_delta_max / 10, array_fill(3, max_node - 2, 0));

for ($delta_max = 10; $delta_max <= max_delta_max; $delta_max+=10) {
    $e = array_fill(1, max_node + 2, array_fill(1, number_sim + 1, 0));
//variate number of sender
    for ($nb_sender = 3; $nb_sender <= max_node; $nb_sender++) {
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

            while ($t < sim_time) {
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
                        $t_node[NODE_RX][$idx] += T_CCA + T_WB + $nb_node_wakeup * T_SLOT;

                        // calculate time in transmission process
                        $t_node[NODE_RX][$idx] += T_CCA + T_CCA + T_ACK;
                        $t_node[NODE_TX][$idx] += T_DATA;

                        // Change back to sleep
                        $t_sleep[$idx] = $t + T_CCA + T_WB + ($nb_node_wakeup + 1) * T_SLOT;
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
                    } elseif ($twu[RELAY_IDX][$idx] <= $t) {
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
                $t_node[NODE_RX][RELAY_IDX] += (T_CCA + T_DATA + T_CCA) * $nb_node_wakeup;
                $t_trans += (T_CCA + T_DATA + T_CCA) * $nb_node_wakeup;
                // send ACK
                $t_node[NODE_TX][RELAY_IDX] += T_ACK * $nb_node_wakeup;
                $t_trans += T_ACK * $nb_node_wakeup;
                // wait for new node join network
                $t_node[NODE_RX][RELAY_IDX] += T_DATA;
                $t_trans += T_DATA;

                //add 3ms to delay
                $t_node[NODE_RX][RELAY_IDX] += 2;
                $t_trans += 2;

                // if there is some packet in queue
                // calculate the aggregation condition
                if (count($queue)) {
                    $nextWakeup = min($twu[RELAY_IDX]);
                    // aggregation condition is satisfied -> go to sleep
                    if ($nextWakeup < $queue[0]["time"] + $delta_max) {
                        // Destination still wake up & send WB
                        $t_node[NODE_SLEEP][DEST_IDX] += $t + $t_trans - $t_sleep[DEST_IDX];
                        $t_node[NODE_RX][DEST_IDX] += T_CCA + T_DATA;
                        $t_node[NODE_TX][DEST_IDX] += T_WB;
                        $t_sleep[DEST_IDX] = $t + $t_trans + T_CCA + T_WB + T_DATA;
                    } else { // send big packet to destination
                        $nb_pkg_relay[$sim]++;
                        $nbPacket = count($queue);
                        // Receiver
                        $t_data_agg = (L_DATA + ($nbPacket - 1) * (L_DATA - DATA_SAVED)) * 8 / bitrate;
                        $t_node[NODE_RX][RELAY_IDX] += T_CCA + T_WB + T_CCA + T_CCA + T_ACK;
                        $t_node[NODE_TX][RELAY_IDX] += $t_data_agg;

                        // Destination
                        $t_data_agg = (L_DATA + ($nbPacket - 1) * (L_DATA - DATA_SAVED)) * 8 / bitrate;
                        $t_node[NODE_SLEEP][DEST_IDX] += $t + $t_trans - $t_sleep[DEST_IDX];
                        $t_node[NODE_RX][DEST_IDX] += T_CCA + T_CCA + $t_data_agg + T_CCA;
                        $t_node[NODE_TX][DEST_IDX] += T_WB + T_ACK;
                        $t_trans += T_CCA + T_WB + T_CCA + $t_data_agg + T_CCA + T_ACK;
                        $t_sleep[DEST_IDX] = $t + $t_trans;

                        //Calculate delay
                        foreach ($queue as $packet) {
                            $delay[$packet['idx']][$sim] += $t + $t_trans - T_CCA - T_ACK - $packet['time'];
                            $nb_pkg_recv[$packet['idx']][$sim]++;
                        }

                        //number of aggregation
                        if (count($queue) > 1) {
                            $nb_agg[$sim]++;
                            $nb_pkg_agg[$sim] += count($queue);
                            if (count($queue) > $nb_pkg_agg_max[$sim]) {
                                $nb_pkg_agg_max[$sim] = count($queue);
                            }
                            if (count($queue) < $nb_pkg_agg_min[$sim]) {
                                $nb_pkg_agg_min[$sim] = count($queue);
                            }
                        }
                        //empty queue
                        $queue = [];
                        $queueIdx = 0;
                    }
                } else {
                    $t_node[NODE_SLEEP][DEST_IDX] += $t + $t_trans - $t_sleep[DEST_IDX];
                    $t_node[NODE_RX][DEST_IDX] += T_CCA + T_DATA;
                    $t_node[NODE_TX][DEST_IDX] += T_WB;
                    $t_sleep[DEST_IDX] = $t + $t_trans + T_CCA + T_WB + T_DATA;
                }

                $t += $t_trans;

                $t_sleep[RELAY_IDX] = $t;

                $next_wakeup = min($twu[RELAY_IDX]);
                if ($t < $next_wakeup) {
                    $t = $next_wakeup;
                }
            }

            /**
             * ENERGY
             */
            $e[$nb_sender][$sim] += (Psp * array_sum($t_node[NODE_SLEEP]) + Prx * array_sum($t_node[NODE_RX]) + Ptx * array_sum($t_node[NODE_TX]));
            $nb_pkg_total = 0;
            for ($idx = 1; $idx <= $nb_node; $idx++) {
                $nb_pkg_total += $nb_pkg_recv[$idx][$sim];
            }
            $e[$nb_sender][$sim] /= $nb_pkg_total;
            /**
             * DELAY
             */
            for ($idx = 3; $idx <= $nb_node; $idx++) {
                $delay[$idx][$sim] = $delay[$idx][$sim] / $nb_pkg_recv[$idx][$sim];
            }
            /**
             * Aggregation ratio
             */
            $delta_idx = floor($delta_max / 10);
            $agg_ratio[$delta_idx][$nb_sender] += $nb_agg[$sim] / $nb_pkg_relay[$sim];
            $agg_avg[$delta_idx][$nb_sender] += $nb_pkg_agg[$sim] / $nb_agg[$sim];
        }
        $agg_ratio[$delta_idx][$nb_sender] = number_format($agg_ratio[$delta_idx][$nb_sender], 3);
        $agg_avg[$delta_idx][$nb_sender] = number_format($agg_avg[$delta_idx][$nb_sender] / 100, 3);
        // Energy consumption per packet receipt
        $e_avg[$delta_idx][$nb_sender] = number_format(array_sum($e[$nb_sender]) / 1000 / number_sim * 2, 3);
        // Delay average
        $delay_avg[$delta_idx][$nb_sender] = number_format(array_sum($delay[$nb_sender]) / number_sim, 3);
        printf("%s: %s\n", $delta_max, $nb_sender);
    }
}

$filename = "energy.csv";
$file = fopen(OUT_DIR . $filename, 'w');
foreach ($e_avg as $e) {
    fputcsv($file, $e);
}

$filename = "delay.csv";
$file = fopen(OUT_DIR . $filename, 'w');
foreach ($delay_avg as $d) {
    fputcsv($file, $d);
}

$filename = "ratio.csv";
$file = fopen(OUT_DIR . $filename, 'w');
foreach ($agg_ratio as $d) {
    fputcsv($file, $d);
}

$filename = "agg.csv";
$file = fopen(OUT_DIR . $filename, 'w');
foreach ($agg_avg as $d) {
    fputcsv($file, $d);
}