<?php
header('content-type: text/plain');

require_once __DIR__ . '/src/Process.php';

use xthukuh\Process;

//test process command
$php = Process::is_win() ? 'php' : '/usr/local/bin/php';
$eval = 'echo \'[pid \' . getmypid() . \'-\' . getcwd() . \'] sleep 4...\' . PHP_EOL;sleep(4);echo \'- done.\';';
$cmd = sprintf('%s -r "%s" > "example bg.log"', $php, $eval);
print("Test background process command ($cmd)\n");

//open process
$start = $t = microtime(1);
if (($proc = new Process($cmd, ['cwd' => __DIR__])) -> open($background=true)){
	
	//success
	$et = round(microtime(1) - $t, 4);
	print(sprintf("> Process started ($et sec): ppid=%s, cpid=%s, pid=%s, background=%s\n", $proc -> ppid, $proc -> cpid, $proc -> pid, json_encode($proc -> background)));
	print(sprintf("-ccmd: %s\n", $proc -> ccmd));
	print(sprintf("-cmd: %s\n", $proc -> cmd));
	print(sprintf("-cwd: %s\n", $proc -> cwd));

	//delay check running
	$delay = 2; //seconds
	for ($i = 1; $i <= 2; $i ++){
		if (!$proc -> running($pid)) break;
		$t = microtime(1);
		print("> $i. Delay check exist (pid: $pid) wait $delay seconds...\n");
		sleep($delay);
		$ppid_exists = Process::exists($proc -> ppid);
		$cpid_exists = Process::exists($proc -> cpid);
		print(sprintf("- ppid exists(%s) = %s\n", $proc -> ppid, json_encode($ppid_exists)));
		print(sprintf("- cpid exists(%s) = %s\n", $proc -> cpid, json_encode($cpid_exists)));
		$et = round(microtime(1) - $t, 4);
		print("> Check $i done ($et sec)\n");
	}
	print(sprintf("> Check %s.\n", $i >= 2 ? 'complete' : 'incomplete'));
}
else {

	//failure
	$et = round(microtime(1) - $t, 4);
	print(sprintf("> Process failure ($et sec): error='%s'\n", $proc -> error));
}

$et = round(microtime(1) - $start, 4);
print("Test complete ($et sec).\n");
exit($proc -> exit);

/*
PS C:\www\process> php example-bg.php
Test background process command (php -r "echo '[pid ' . getmypid() . '-' . getcwd() . '] sleep 4...' . PHP_EOL;sleep(4);echo '- done.';" > "example bg.log")
> Process started (0.2213 sec): ppid=8124, cpid=12144, pid=12144, background=true
-ccmd: start /b php -r "echo '[pid ' . getmypid() . '-' . getcwd() . '] sleep 4...' . PHP_EOL;sleep(4);echo '- done.';" >"example bg.log" 2>nul &
-cmd: php -r "echo '[pid ' . getmypid() . '-' . getcwd() . '] sleep 4...' . PHP_EOL;sleep(4);echo '- done.';" > "example bg.log"
-cwd: C:\www\process
> 1. Delay check exist (pid: 12144) wait 2 seconds...
- ppid exists(8124) = false
- cpid exists(12144) = true
> Check 1 done (2.3626 sec)
> 2. Delay check exist (pid: 12144) wait 2 seconds...
- ppid exists(8124) = false
- cpid exists(12144) = false
> Check 2 done (2.4462 sec) 
> Check complete.
Test complete (5.2588 sec). 
PS C:\www\process>
*/

/* "example bg.log"
[pid 12144-C:\www\process] sleep 4...
- done.
*/