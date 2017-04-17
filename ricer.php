<?php
include_once 'include/input.php';
include_once 'include/output.php';

const OUT_DIR = 'out/ricer3/std/';

const RELAY_IWU = 50;

if (!file_exists(OUT_DIR)) {
    mkdir(OUT_DIR, 0777, TRUE);
}

$delay_avg = $e_avg = array_fill(3, max_node - 2 , 0);
$nb_agg = $e = array_fill(1, max_node + 2, array_fill(1, number_sim + 1, 0));
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
        //Syn destination & relay
        /*
        if ($twu[DEST_IDX][0] < $t) {
            // Destination still wake up & send WB
            $t_node[NODE_SLEEP][DEST_IDX] = $twu[DEST_IDX][0] - $t_sleep[DEST_IDX];
            $t_node[NODE_RX][DEST_IDX] += T_CCA + T_DATA;
            $t_node[NODE_TX][DEST_IDX] += T_WB;
            $t_sleep[DEST_IDX] = $twu[DEST_IDX][0] + T_CCA + T_WB + T_DATA;
            $twu[DEST_IDX][0] += RELAY_IWU;
        }*/

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
                $t_node[NODE_RX][RELAY_IDX] += $twu[DEST_IDX][0] - $t;
                // update current simulation time
                $t = $twu[DEST_IDX][0];

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

                //Calculate delay
                foreach ($queue as $idx => $packet) {
                    $delay[$packet['idx']][$sim] += $t + (T_CCA + T_DATA + T_CCA + T_ACK) * $idx - $packet['time'];
                    $nb_pkg_recv[$packet['idx']][$sim]++;
                }

                //empty queue
                $queue = [];
                $queueIdx = 0;
            } else {
                // RELAY : go to sleep
                $t_sleep[RELAY_IDX] = $t;
                $twu[RELAY_IDX][0] += RELAY_IWU;

                // DESTINATION
                // send WB & wait T_DATA if does not receive DATA
                $t_node[NODE_SLEEP][DEST_IDX] += $twu[DEST_IDX][0] - $t_sleep[DEST_IDX];
                $t_node[NODE_RX][DEST_IDX] += T_CCA;
                $t_node[NODE_TX][DEST_IDX] += T_WB;
                $t_node[NODE_RX][DEST_IDX] += T_DATA;
                // go to sleep
                $t_sleep[DEST_IDX] = $twu[DEST_IDX][0] + T_CCA + T_WB + T_DATA;
                $twu[DEST_IDX][0] += RELAY_IWU;
            }
            // Next wake up of relay node
            $t = $twu[RELAY_IDX][0];
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
    }
    // Energy consumption per packet receipt
    $e_avg[$nb_sender] = number_format(array_sum($e[$nb_sender]) / 1000 / number_sim * 2, 3);
    // Delay average
    $delay_avg[$nb_sender] = number_format(array_sum($delay[$nb_sender]) / number_sim, 3);
    printf("%s\n", $nb_sender);
}

$filename = "energy.csv";
$file = fopen(OUT_DIR . $filename, 'w');
fputcsv($file, $e_avg);

$filename = "delay.csv";
$file = fopen(OUT_DIR . $filename, 'w');
fputcsv($file, $delay_avg);