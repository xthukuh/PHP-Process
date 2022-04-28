<?php
header('content-type: text/plain');
$loop = 5;
$delay = 1;
$loop = is_array($argv) && isset($argv[1]) && is_numeric($argv[1]) && ($tmp = (int) $argv[1]) > 0 ? $tmp : $loop;
$delay = is_array($argv) && isset($argv[2]) && is_numeric($argv[2]) && ($tmp = (int) $argv[2]) > 0 ? $tmp : $delay;
echo "Test sleep (loop=$loop, delay=$delay seconds)\n";
for ($i = 1; $i <= $loop; $i ++){
	echo "$i - sleep delay...\n";
	sleep($delay);
}
echo "Test complete.\n";
exit(0);