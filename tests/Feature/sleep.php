<?php
header('content-type: text/plain');
$pid = getmypid();
$start = microtime(1);
$loop = 5;
$delay = 1;
$loop = is_array($argv) && isset($argv[1]) && is_numeric($argv[1]) && ($tmp = (int) $argv[1]) > 0 ? $tmp : $loop;
$delay = is_array($argv) && isset($argv[2]) && is_numeric($argv[2]) && ($tmp = (int) $argv[2]) > 0 ? $tmp : $delay;
echo "Test sleep (loop=$loop, delay=$delay seconds, pid=$pid)\n";
for ($i = 1; $i <= $loop; $i ++){
	echo "$i - sleep delay...\n";
	//if ($i > 2) throw new Exception('Test error.');
	sleep($delay);
}
$eta = round(microtime(1) - $start, 4);
echo "Test complete ($eta seconds).\n";
exit(0);