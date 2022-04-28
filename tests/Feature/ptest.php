<?php
header('content-type: text/plain');

require_once __DIR__ . '/../../src/Process.php';

use xthukuh\Process;

#(bool) check if platform is windows
$is_win = Process::is_win($uname);
$php = $is_win ? 'php' : '/usr/local/bin/php';
echo '> Test process (my-pid: ' . getmypid() . ", uname=$uname, php=$php)\n";
$test_start = microtime(1);

//DEBUG:
#process (test) cmd
$loop = 10; //loop count
$delay = 1; //seconds
$cmd = sprintf('%s sleep.php %d %d', $php, $loop, $delay);

#process options
$options=[
	'cwd' => __DIR__, //set working directory
];

#(bool) create process instance
$process = new Process($cmd, $options);

//DEBUG:
#(bool) open process
$background = 1;
$success = $process -> open($background, $callback=function(Process $p){
	echo "process open callback:\n";
	print_r([
		'ccmd' => $p -> ccmd, //(string) process current command line.
		'cwd' => $p -> cwd, //(string) process working directory.
		'id' => $p -> id, //(string) process unique id.
		'background' => json_encode($p -> background), //(bool) process is running in background.
		'ppid' => $p -> ppid, //(int) process parent pid.
		'cpid' => $p -> cpid, //(int) process child pid.
		'pid' => $p -> pid, //(int) process current pid.
	]);
	return true; //keep open
});

#check failure
if (!$success) die($process -> error);
echo '$process -> open(...) = ' . json_encode($success) . "\n";

#(int|null) get child process pid
$child_pid = Process::child($process -> ppid);
echo 'Process::child(ppid=' . $process -> ppid . ') = ' . json_encode($child_pid) . "\n";

#(bool) check if process is running
$is_running = $process -> running();
echo '$process -> running() = ' . json_encode($is_running) . "\n";

#check if current process exists
$exists = Process::exists($process -> pid);
echo 'Process::exists(pid=' . $process -> pid . ') = ' . json_encode($exists) . "\n";

//DEBUG:
#process output buffer (print enabled)
$get_output = 1;
$output_print = 2;
$output_timeout = 1;
if ($get_output){
	echo "\n--------- output start --------\n";
	$output = null;
	if ($output_timeout){
		$start = microtime(1);
		$process -> output($callback=function(string $buffer) use (&$process, &$output, &$start, &$output_timeout) {
			if (is_null($output)) $output = '';
			$output .= $buffer;
			if ((microtime(1) - $start) >= $output_timeout) return false; //buffer timeout abort
		}, $output_print);
	}
	else $output = $process -> output(null, $output_print);
	if (!is_null($output)){
		echo "\n--------- output text ----------\n";
		echo $output;
	}
	echo "\n--------- output end ----------\n\n";
}

#check if current process exists
$exists = Process::exists($process -> pid);
echo 'Process::exists(pid=' . $process -> pid . ') = ' . json_encode($exists) . "\n";

#check if parent process exists
$exists = Process::exists($process -> ppid);
echo 'Process::exists(ppid=' . $process -> ppid . ') = ' . json_encode($exists) . "\n";
echo '$process -> open = ' . json_encode($process -> open) . "\n";

//DEBUG:
#(bool, $killed=(null|1|0)) kill process
$process_kill = 1;
if ($process_kill){
	$kill = Process::kill($child_pid, $killed);
	echo 'Process::kill(' . $child_pid . ') = ' . json_encode($kill) . ', $killed=' . json_encode($killed) . "\n";
}

#(int|null) close process.
$exit_code = $process -> close();
echo '$process -> close() = ' . json_encode($exit_code) . "\n";
echo '$process -> open = ' . json_encode($process -> open) . "\n";

#delay
$d = 2;
echo "\n- delay $d second...\n\n";
sleep($d);

#check if current process exists
$exists = Process::exists($process -> ppid);
echo 'Process::exists(ppid=' . $process -> ppid . ') = ' . json_encode($exists) . "\n";

#check if parent process exists
$exists = Process::exists($process -> cpid);
echo 'Process::exists(cpid=' . $process -> cpid . ') = ' . json_encode($exists) . "\n";
if ($exists) echo "\n\n- tasklist | findstr /i " . $process -> cpid . "\n\n";

#(int|null) process exit return code.
$process -> exit;
echo '$process -> exit = ' . json_encode($process -> exit) . "\n";

#test done
$eta = round(microtime(1) - $test_start, 4);
echo "\n> Test done ($eta seconds).\n\n";

#exit
exit();