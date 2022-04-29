<?php

namespace Tests\Feature;

use Tests\TestCase;
use xthukuh\Process;

class ProcessTest extends TestCase
{
	/**
	 * Process test example.
	 *
	 * @return void
	 */
	public function testExample(){
		
		#(bool) check if platform is windows
		$is_win = Process::is_win($uname);
		$php = $is_win ? 'php' : '/usr/local/bin/php';
		echo '> Test process (my-pid: ' . getmypid() . ", uname=$uname, php=$php)\n";
		$test_start = microtime(1);

		#process (test) cmd
		$loop = 3; //DEBUG: (int) loop count
		$delay = 1; //DEBUG: (int) seconds
		//$cmd = sprintf('%s sleep.php %d %d', $php, $loop, $delay);
		$cmd = sprintf('%s sleep.php %d %d > sleep.log', $php, $loop, $delay);

		#process options
		$options=[
			'cwd' => __DIR__, //DEBUG: working directory
		];
		
		#(bool) create process instance
		$process = new Process($cmd, $options);

		#(bool) open process
		$background = 1; //DEBUG: 0, 1
		$success = $process -> open($background, $callback=function(Process $p){
			echo "process open callback:\n";
			print_r([
				'id' => $p -> id,
				'key' => $p -> key,
				'cwd' => $p -> cwd,
				'cmd' => $p -> cmd,
				'ccmd' => $p -> ccmd,
				'background' => $p -> background,
				'ppid' => $p -> ppid,
				'cpid' => $p -> cpid,
				'pid' => $p -> pid,
			]);
			return true; //keep open
		});
		echo '$success = ' . json_encode($success) . "\n";

		#(int|null) get child process pid
		$res = Process::child($process -> ppid, $pids);
		echo 'Process::child(ppid=' . $process -> ppid . ') = ' . json_encode($res) . " (pids=" . json_encode($pids) . ")\n";
		
		#(bool) check if process is running
		$is_running = $process -> running($pid);
		echo '$process -> running() = ' . json_encode($is_running) . " (pid=$pid)\n";
		
		#process output buffer (print enabled)
		$get_output = 1; //DEBUG: 0, 1
		$output_print = 2; //DEBUG: 0, 1, 2, 3
		$output_timeout = 1; //DEBUG: (float) seconds
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
		
		#check process open status
		echo '$process -> open = ' . json_encode($process -> open) . "\n";

		#(bool, $killed=(null|1|0)) kill process
		$process_kill = 0; //DEBUG: 0, 1
		if ($process_kill){
			echo "\n- kill\n\n";
			$kill = Process::kill($process -> pid, $killed);
			echo 'Process::kill(pid=' . $process -> pid . ') = ' . json_encode($kill) . ' ($killed=' . json_encode($killed) . ")\n";
		}
		
		#(int|null) close process.
		echo "\n- close\n\n";
		$exit = $process -> close();
		echo '$process -> close() = ' . json_encode($exit) . "\n";
		echo '$process -> open = ' . json_encode($process -> open) . "\n";

		#(bool) check if process is running
		$is_running = $process -> running($pid);
		echo '$process -> running() = ' . json_encode($is_running) . " (pid=$pid)\n";

		#check if current process exists
		$exists = Process::exists($process -> pid);
		echo 'Process::exists(pid=' . $process -> pid . ') = ' . json_encode($exists) . "\n";

		#check if parent process exists
		$exists = Process::exists($process -> ppid);
		echo 'Process::exists(ppid=' . $process -> ppid . ') = ' . json_encode($exists) . "\n";

		#delay
		$delay = 2; //DEBUG: (float) seconds
		if ($delay){
			echo "\n- delay $delay second...\n\n";
			sleep($delay);
		}

		#check if current process exists
		$exists = Process::exists($process -> pid);
		echo 'Process::exists(pid=' . $process -> pid . ') = ' . json_encode($exists) . "\n";
		
		
		#test done
		$exit = $process -> exit;
		$eta = round(microtime(1) - $test_start, 4);
		echo "\n> Test done ($eta seconds, exit=$exit).\n";

		#assert
		$this -> assertTrue(true);
	}
}
