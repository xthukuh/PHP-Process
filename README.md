# xthukuh\Process

This is a simple php ``proc_open`` wrapper class. Supports running background process on windows/linux, get child process pid buffer output among other functions.

# Package

Minimum requirements:
- PHP >= 5.3.0
- [Composer](https://getcomposer.org/download/)

Include the package within your project requirements:
- ``composer install xthukuh/process``
- ``composer dump-autoload``

# Usage

After the package has been installed...
- ``use xthukuh\Process``;
- Read inline documentation: [Process](../../tree/main/src/Process.php).
- Example implementation: [ProcessTest](../../tree/main/tests/Feature/ProcessTest.php).

```php
<?php

namespace App;

use xthukuh\Process

## example process command (platform specific ping implementation)
$cmd = Process::is_win() ? "ping 0 -n 5" : "ping 0 -c 5";

## create process instance
$proc = new Process($cmd, $options=[]);

## accessible props (set before open is called)
$proc -> id;       //(string) process unique id (static::uid())
$proc -> key;      //(string) process unique key (md5(cmd . cwd))
$proc -> cmd;      //(string) process command
$proc -> cwd;      //(string) process working directory
$proc -> options;  //(array) process options

## open/run process
$success = $proc -> open($background=false, $callback=function(Process $self){
	
	//$self === $proc;

	//..
	
	## get output buffer
	$self -> output($callback=function(string $buffer){
		//..
		#return false; //cancel output buffer listener. 
	}, $print=0, $len=1024); //(null)
	#$output = $self -> output(null, $print=1);
	#$output = $self -> output(null, $print=2);
	#$output = $self -> output(null, $print=3);
	#$output = $self -> output();
	//$output - (string) stdout fgets output buffer
	
	//..

	#return false;   //cancel/terminate process
	#return true;    //keep open (close manually)
	#return;         //(any) close automatically
});
#$success = $proc -> open();
#$success = $proc -> open($background=true);
//$success - (bool) process opened successfully

## accessible props (set after open is called)
$proc -> ccmd;    //(string) proc_open command line in use (cmd change when running in background)
$proc -> descriptor_spec;  //(array) proc_open descriptor spec in use
$proc -> env_vars;         //(array) proc_open env_vars in use
$proc -> other_options;    //(array) proc_open other_options in use
$proc -> process; //(resource) proc_open result resource
$proc -> pipes;   //(array) proc_open pipes [0 => stdin, 1 => stdout, 2 => stderr, ...]
$proc -> error;   //(string) set on failure ($success === false)
$proc -> ppid;    //(int) proc_get_status pid.
$proc -> cpid;    //(int|null) child process pid (null when not running in background)
$proc -> pid;     //(int) current process pid. (ppid when not running in background, cpid otherwise)
$proc -> exit;    //(int|null) process close exit (proc_close) result code
$proc -> open;        //(bool) process open status (true: after proc_open, false: after proc_close)
$proc -> background;  //(bool) process running in background.

//instance methods
$proc -> open(bool $background, $callback); //(bool) process opened successfully
$proc -> status(int &$pid, int &$running); //(array) proc_get_status ($running = (0|1))
$proc -> running(int &$pid); //(bool) process running status ($pid = running pid)
$proc -> output($callback, int $print, int $len); //(string|null) process stdout buffer
$proc -> close(bool $kill, int $kill_pid=null); //(int) process close exit (proc_close) result code
$proc -> pipe(int $index=null); //(resource|null) get process stream resource pipe (pipes[$index])
$proc -> shutdown(); //(void) ($this -> close(1)) register_shutdown_function before open callback.

//static methods
Process::uid(); //(string) - e.g. 626e83fec4f1cd6a
Process::is_win(string &$uname); //(bool) - check if platform is windows ($uname = php_uname('s'))
Process::child(int $pid, array &$pids); //(int|null) get child process pid from parent pid ($pids = child pid array)
Process::exists(int $pid); //(bool) check if pid is running
Process::kill(int $pid, int &$killed); //(bool) kill pid process ($killed = (null = failure |0 = process not found |1 = process was found))
Process::timed_out(float $timeout, float $start, float &$elapsed); //(bool)
Process::ffgets($pipe, int $len, float $timeout); //(string|false)
Process::seekable($pipe, array &$meta); //(bool) ($meta = stream_get_meta_data($pipe))
Process::ob_end(bool $clean, bool $implicit_flush); //(void)
Process::buffer($pipe, $callback, array $options, int &$abort, string &$error);
```

**NOTE: [Read Inline documentation to find out more about each method and its parameters.](../../tree/main/src/Process.php)**

#

# Project

You can fork and add improvements.

- ``git clone https://github.com/xthukuh/process.git``
- ``composer install``
- ``./vendor/bin/phpunit tests --testdox``

#

### 💖 By [Thuku Wanjiku](https://github.com/xthukuh). _**Enjoy!**_