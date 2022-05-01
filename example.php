<?php
header('content-type: text/plain');

require_once __DIR__ . '/src/Process.php';

use xthukuh\Process;

$proc = new Process($cmd = sprintf('%s --version', Process::is_win() ? 'php' : '/usr/local/bin/php'));

$output = null;
$success = $proc -> open($background=false, $callback=function(Process $self) use (&$output){
	$self -> output($callback=function(string $buffer) use (&$output){
		if (is_null($output)) $output = '';
		$output .= $buffer;
	});
});

if ($success){
	print(sprintf("process successful: pid=%s, exit=%s, output=\n--------------------\n%s\n--------------------\n", $proc -> pid, $proc -> exit, $output));
	exit(0);
}
else {
	print(sprintf("process failure: error='%s'\n", $proc -> error));
	exit(1);
}

/*
PS C:\www\process> php example.php
process successful: pid=10956, exit=0, output=
--------------------
PHP 7.3.31 (cli) (built: Sep 21 2021 12:17:30) ( ZTS MSVC15 (Visual C++ 2017) x64 )
Copyright (c) 1997-2018 The PHP Group
Zend Engine v3.3.31, Copyright (c) 1998-2018 Zend Technologies

--------------------
PS C:\www\process>
*/