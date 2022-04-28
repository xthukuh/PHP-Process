<?php

namespace xthukuh;

use Exception;

class Process
{
	/**
	 * Default options.
	 * 
	 * - https://www.php.net/manual/en/function.proc-open.php
	 * 
	 * @var array
	 */
	protected $default_options = [
		'cwd' => null,              //string proc_open working directory (proc_open: $cwd).
		'descriptor_spec' => null,  //array file descriptors (proc_open: $descriptorspec) (See $default_descriptor_spec doc).
		'env_vars' => null,         //array environment variables (proc_open: $env_vars).
		'other_options' => null,    //array other options (proc_open: $other_options).
	];

	/**
	 * Default descriptor spec (proc_open: descriptor_spec).
	 * 
	 * @var array
	 */
	protected $default_descriptor_spec = [
		0 => ['pipe', 'r'], //stdin
		1 => ['pipe', 'w'], //stdout
		2 => ['pipe', 'w'], //stderr
	];

	/**
	 * Unique Id.
	 * 
	 * @var string
	 */
	private $_id;

	/**
	 * Process working directory.
	 * 
	 * @var string
	 */
	private $_cwd;

	/**
	 * Process command.
	 * 
	 * @var string
	 */
	private $_cmd;

	/**
	 * Process current command line.
	 * 
	 * @var string
	 */
	private $_ccmd;

	/**
	 * Process options (See protected $default_options doc).
	 * 
	 * - Note: When running in background, the option 'descriptor_spec'
	 * - is ignored and '$default_descriptor_spec' used instead.
	 * 
	 * @var array
	 */
	private $_options;

	/**
	 * Process file descriptors (See protected $default_descriptor_spec doc).
	 * 
	 * @var array
	 */
	private $_descriptor_spec;

	/**
	 * Process environment variables. (See protected $default_options doc).
	 * 
	 * @var array
	 */
	private $_env_vars;

	/**
	 * Process other options. (See protected $default_options doc).
	 * 
	 * @var array
	 */
	private $_other_options;

	/**
	 * Process resource (proc_open result).
	 * 
	 * @var resource
	 */
	private $_process;

	/**
	 * Resource pipes (proc_open: $pipes).
	 * 
	 * @var array
	 */
	private $_pipes;

	/**
	 * Process error.
	 * 
	 * @var string
	 */
	private $_error;

	/**
	 * Open status.
	 * 
	 * @var bool
	 */
	private $_open;

	/**
	 * Run in background mode.
	 * 
	 * - 0 = Disabled.
	 * - 1 = Enabled without output.
	 * - 2 = Enabled with output.
	 * 
	 * @var int
	 */
	private $_background;

	/**
	 * Process parent pid.
	 * 
	 * @var int
	 */
	private $_ppid;

	/**
	 * Process child pid.
	 * 
	 * @var int
	 */
	private $_cpid;

	/**
	 * Process current pid.
	 * 
	 * - The parent pid (self -> ppid) is used except when running in
	 *   background where child pid (self -> cpid) is used.
	 * 
	 * @var int
	 */
	private $_pid;

	/**
	 * Process close exit result code (proc_close).
	 * 
	 * @var int
	 */
	private $_exit;

	/**
	 * New instance.
	 * 
	 * @param  string  $cmd      - Process command.
	 * @param  array   $options  - Process options.
	 * @return self
	 */
	public function __construct(string $cmd=null, array $options=null){
		$this -> _id = static::uid();
		$this -> _cmd = is_string($cmd) && ($cmd = trim(str_replace(urldecode('%C2%A0'), ' ', $cmd))) ? $cmd : null;
		if (is_array($options) && !empty($options)){
			$opts = [];
			if (isset($options[$key = 'interactive']) && $options[$key]) $opts[$key] = true;
			if (isset($options[$key = 'cwd']) && is_string($val = $options[$key]) && ($val = trim($val)) && file_exists($val) && is_dir($val)) $opts[$key] = $val;
			if (isset($options[$key = 'descriptor_spec']) && is_array($val = $options[$key])){
				$tmp = [];
				for ($i = 0; $i < 3; $i ++){
					if (isset($val[$i]) && is_array($val[$i]) && count($val[$i]) >= 2) $tmp[$i] = $val[$i];
				}
				if (!empty($tmp)) $opts[$key] = array_replace($this -> default_descriptor_spec, $tmp);
				unset($tmp, $i);
			}
			if (isset($options[$key = 'env_vars']) && is_array($val = $options[$key]) && !empty($val)) $opts[$key] = $val;
			if (isset($options[$key = 'other_options']) && is_array($val = $options[$key]) && !empty($val)) $opts[$key] = $val;
			if (!empty($opts)) $this -> _options = array_replace($this -> default_options, $opts);
			unset($key, $val, $opts);
		}
		unset($cmd, $options);
		$this -> reset();
	}

	/**
	 * Property getter.
	 * 
	 * i.e.
	 * - $instance -> pid === $this -> _pid
	 * - $instance -> _pid === $this -> _pid
	 * 
	 * @param  string  $name
	 * @return mixed
	 */
	public function __get(string $name){
		if (property_exists($this, $prop = "$name")) return $this -> {$prop};
		if (property_exists($this, $prop = "_$name")) return $this -> {$prop};
		throw new Exception("Property name '$name' does not exist.");
	}

	/**
	 * Reset instance.
	 * 
	 * @return void
	 */
	private function reset(){
		if ($this -> _open) $this -> close(1);
		$this -> _cwd = null;
		$this -> _ccmd = null;
		$this -> _process = null;
		$this -> _descriptor_spec = null;
		$this -> _env_vars = null;
		$this -> _other_options = null;
		$this -> _pipes = null;
		$this -> _error = null;
		$this -> _open = false;
		$this -> _background = 0;
		$this -> _ppid = null;
		$this -> _cpid = null;
		$this -> _pid = null;
		$this -> _exit = null;
	}

	/**
	 * Open process (proc_open).
	 * 
	 * - Run in background ($background) modes:
	 *   > 0 = disabled.
	 * 	 > 1 = enabled with output - cmd format:
	 *     self -> _ccmd = "start /b %cmd 2>&1" - Windows platform
	 *     self -> _ccmd = "%cmd 2>&1 & echo $!" - Other platform
	 *   > 2 = enabled no output - cmd format:
	 *     self -> _ccmd = "start /b %cmd > nul 2>&1" - Windows platform
	 *     self -> _ccmd = "%cmd > /dev/null 2>&1 & echo $!" - Other platform
	 * 
	 * - Note: using (start /b, > nul, 2>&1, > /dev/null, & echo &!) in your command when background is enabled 
	 *     may cause unexpected behavior.
	 * 
	 * After successful open:
	 * - Shutdown listener is registered to gracefully close open process.
	 * - Callback, if callable, is called with instance as argument ($callback(self)):
	 *   > If callback result = false: Process is terminated.
	 *   > If callback result = true: Process is kept open and
	 *     the close method is called manually (or through the shutdown handler).
	 *   > If callback result <> boolean: Process is closed. If running in background,
	 *     child background process is not closed and can be killed manually using the (static::kill(self -> cpid)).
	 * 
	 * @param  int       $background  - Run in background mode (see doc).
	 * @param  callable  $callback    - Closure/Method (argument: [$this]) (see doc).
	 * @return bool
	 */
	public function open(int $background=null, $callback=null){
		$callback = is_callable($callback) ? $callback : null;
		$background = in_array($background = (int) $background, [1, 2]) ? $background : 0;
		$cmd = null;
		
		$this -> reset();
		
		$_failure = function($err, $reset=1) use (&$background, &$cmd){
			if ($reset) $this -> reset();
			$this -> _error = sprintf('Open%s process %s. (cmd: %s)', $background ? ' background' : '', $err, $cmd);
			return false;
		};
		
		if (!($cmd = $this -> _cmd)) return $_failure('cmd is undefined', 0);
		
		$is_win = static::is_win();
		if ($background === 1) $cmd = sprintf($is_win ? 'start /b %s 2>&1' : '%s 2>&1 & echo $!', $cmd);
		elseif ($background === 2) $cmd = sprintf($is_win ? 'start /b %s > nul 2>&1 &' : '%s > /dev/null 2>&1 & echo $!', $cmd);
		$this -> _ccmd = $cmd;
		
		$options = is_array($options = $this -> _options) ? $options : $this -> default_options;
		$this -> _cwd = isset($options[$key = 'cwd']) && ($val = trim($options[$key])) && file_exists($val) && is_dir($val) ? $val : getcwd();
		$this -> _descriptor_spec = !$background && isset($options[$key = 'descriptor_spec']) && is_array($val = $options[$key]) && count($val) === 3 ? $val : $this -> default_descriptor_spec;
		$this -> _env_vars = isset($options[$key = 'env_vars']) && is_array($val = $options[$key]) && !empty($val) ? $val : null;
		$this -> _other_options = isset($options[$key = 'other_options']) && is_array($val = $options[$key]) && !empty($val) ? $val : null;
		
		//DEBUG:
		//$this -> _descriptor_spec = [0 => ['pipe', 'r']];
		//$this -> _descriptor_spec[1] = ['file', 'nul', 'w'];
		//$this -> _descriptor_spec[2] = ['file', 'nul', 'w'];
		
		print_r(['$this -> _descriptor_spec' => $this -> _descriptor_spec]);
		//DEBUG:

		$this -> _process = proc_open(
			$this -> _ccmd,
			$this -> _descriptor_spec,
			$this -> _pipes,
			$this -> _cwd,
			$this -> _env_vars,
			$this -> _other_options
		);
		if (!is_resource($this -> _process)) return $_failure('failure');

		$this -> _open = true;
		
		print_r(['count pipes' => count($this -> _pipes)]);
		
		if (!(is_array($this -> _pipes) && count($this -> _pipes) >= 3)) return $_failure('has invalid resource pipes');
		
		$meta = stream_get_meta_data($this -> _pipes[1]);
		print_r(['$meta' => $meta]);
		
		//DEBUG:
		/*
		stream_set_blocking($this -> _pipes[1], 0);
		stream_set_blocking($this -> _pipes[2], 0);
		//if ($background === 2) fclose($this -> _pipes[0]);
		*/
		
		if (!(!empty($this -> status($pid)) && $pid)) return $_failure('get status pid failure');

		$this -> _ppid = $pid;
		$this -> _cpid = static::child($pid);

		if ($background){
			if (!($pid = $this -> _cpid)) return $_failure('[' . $pid . '] get child pid failure');
			$this -> _background = $background;
		}
		
		$this -> _pid = $pid;
		register_shutdown_function([$this, 'shutdown']);

		$res = null;
		if ($callback){
			try {
				$res = $callback($this);
			}
			catch (Exception $e){
				return $_failure('callback exception: ' . $e -> getMessage());
			}
		}

		if ($res === false) $this -> close(1);
		elseif ($res !== true) $this -> close();

		return true;
	}

	/**
	 * Close process (proc_terminate, proc_close).
	 * 
	 * - If running in background, proc_terminate() is used.
	 * 
	 * @param  bool  $kill      - Kill running process.
	 * @param  int   $kill_pid  - Kill process pid.
	 * @return int|null         - Exit result code (proc_close).
	 */
	public function close(bool $kill=false, int $kill_pid=null){
		$this -> status($pid, $running);
		if ($kill){
			$kill_pid = is_integer($kill_pid) && $kill_pid > 0 ? $kill_pid : $pid;
			if (!(is_integer($kill_pid) && $kill_pid > 0)) $kill_pid = $this -> _pid;
			static::kill($kill_pid);
		}
		if ($this -> _background && $running && is_resource($this -> _process)) proc_terminate($this -> _process);
		if (is_array($this -> _pipes)){
			foreach ($this -> _pipes as &$pipe){
				if (is_resource($pipe)) fclose($pipe);
			}
		}
		$exit = is_resource($this -> _process) ? ($this -> _exit = proc_close($this -> _process)) : null;
		$this -> _process = null;
		$this -> _pipes = null;
		$this -> _open = false;
		return $exit;
	}

	/**
	 * Process shutdown handler.
	 * 
	 * @return void
	 */
	public function shutdown(){
		if ($this -> _open) $this -> close(1);
	}

	/**
	 * Process is running.
	 * 
	 * @param  int  $pid  - Running pid.
	 * @return bool
	 */
	public function running(int &$pid=null){
		$pid = $val = null;
		if ($this -> _open && is_integer($val = $this -> _ppid) && static::exists($val)) $pid = $val;
		elseif (is_integer($val = $this -> _cpid) && static::exists($val)) $pid = $val;
		unset($val);
		return !is_null($pid);
	}

	/**
	 * Get process status (proc_get_status).
	 * 
	 * @param  int  $pid      - Process pid.
	 * @param  int  $running  - Process running (1|0).
	 * @return array
	 */
	public function status(int &$pid=null, int &$running=null){
		$status = is_resource($this -> _process) && is_array($status = proc_get_status($this -> _process)) ? $status : [];
		$pid = isset($status['pid']) && is_integer($pid = $status['pid']) && $pid > 0 ? $pid : null;
		$running = (int) (isset($status['running']) && $status['running']);
		return $status;
	}

	/**
	 * Get output buffer.
	 * 
	 * - Note: If callback is callable, output buffer is not returned but instead used as the callback argument.
	 * - See static::buffer() doc.
	 * 
	 * @param  callable  $callback  - Closure/Method (argument: [string $buffer]) buffer callback.
	 * @param  int       $print     - Print output buffer mode.
	 * @param  int       $len       - Output buffer read (fgets) length.
	 * @return string|null
	 */
	public function output($callback=null, int $print=0, int $len=null){
		if (!(is_resource($this -> _process) && is_array($this -> _pipes) && isset($this -> _pipes[1]))) return null;
		$output = null;
		$callback = is_callable($callback) ? $callback : null;
		static::buffer($this -> _pipes[1], function($buffer) use (&$callback, &$output){
			if (is_null($output)) $output = '';
			if ($callback) return $callback($buffer);
			else $output .= $buffer;
		}, [
			'print' => $print,
			'length' => $len,
			'fgets_timeout' => $this -> _background === 2 ? 0.5 : 0,
		]);
		return $output;
	}

	/**
	 * Get unique ID (uniqid).
	 * 
	 * @return string
	 */
	public static function uid(){
		$m = microtime(1);
		return sprintf('%8x%05x', $n = floor($m), ($m - $n) * (10 ** 10));
	}

	/**
	 * Check if platform is Windows.
	 * 
	 * @param  string  $uname
	 * @return bool
	 */
	public static function is_win(string &$uname=null){
		return stripos($uname = php_uname('s'), 'win') > -1;
	}

	/**
	 * Get child process pid from parent pid.
	 * 
	 * @param  int    $pid   - Parent process pid.
	 * @param  array  $pids  - Child process pids.
	 * @return int|null
	 */
	public static function child(int $pid=null, array &$pids=null){
		$pids = [];
		if (!(is_integer($pid) && ($pid > 0))) return null;
		$seen = 0;
		$cmd = sprintf(($is_win = static::is_win()) ? 'wmic process get parentprocessid,processid | find "%s"' : 'ps afx --ppid %s', $pid);
		$pids = array_values(array_filter(array_map(function($val) use (&$is_win, &$cmd){
			if (!$is_win){
				if (strpos($val, $cmd) !== false) return null;
				if (!preg_match('/^\s*([0-9]+)\s*/', $val, $val)) return null;
				$val = $val[1];
			}
			else $val = trim($val);
			return $val && is_numeric($val) && ($val = (int) $val) > 0 ? $val : null;
		}, explode($is_win ? ' ' : "\n", $out = trim(shell_exec($cmd)))), function($val) use (&$pid, &$seen){
			if (!$val) return false;
			if ($val === $pid){
				$seen = 1;
				return false;
			}
			return $seen;
		}));
		unset($seen, $cmd, $is_win);
		return !empty($pids) ? $pids[0] : null;
	}

	/**
	 * Check if pid process exists.
	 * 
	 * @param  int  $pid
	 * @return bool
	 */
	public static function exists(int $pid=null){
		if (!(is_integer($pid) && ($pid > 0))) return false;
		return strpos(shell_exec(sprintf(static::is_win() ? 'tasklist /FI "PID eq %d" 2>&1' : 'ps -p %d -opid=,cmd= 2>&1', $pid)), (string) $pid) !== false;
	}

	/**
	 * Kill pid process.
	 * 
	 * @param  int  $pid
	 * @param  int  $killed  (null = error, 0 = process not found, 1 = process killed)
	 * @return bool
	 */
	public static function kill(int $pid=null, int &$killed=null){
		$killed = null;
		if (!(is_integer($pid) && ($pid > 0))) return false;
		$cmd = sprintf(($is_win = static::is_win()) ? 'taskkill /F /T /PID %s 2>&1' : 'kill -s 9 %s 2>&1', $pid);
		$out = exec($cmd);
		$killed = $out === false ? null : ((int)($is_win ? !(stripos($out, 'no tasks') !== false || stripos($out, 'not found') !== false) : stripos($out, 'no such process') === false));
		unset($cmd, $is_win, $out);
		return !is_null($killed);
	}

	/**
	 * Checks timeout from start time.
	 * 
	 * @param  float  $timeout  - Timeout seconds.
	 * @param  float  $start    - Start time seconds (microtime(1)).
	 * @param  float  $elapsed  - Seconds elapsed (microtime(1) - $start).
	 * @return bool
	 */
	static public function timed_out(float $timeout=null, float $start=null, float &$elapsed=null){
		$timeout = is_numeric($timeout) && ($timeout = (float) $timeout) >= 0 ? $timeout : 0;
		$start = is_numeric($start) ? (float) $start : 0;
		$elapsed = microtime(1) - $start;
		return $elapsed >= $timeout;
	}

	/**
	 * Stream resource fgets (if not feof) with timeout.
	 * 
	 * @param  resource  $pipe     - Stream resource.
	 * @param  int       $len      - Read length (default: 1024).
	 * @param  float     $timeout  - Socket timeout (default: ini_get('default_socket_timeout'))
	 * @return string|bool
	 */
	public static function ffgets($pipe, int $len=null, float $timeout=null){
		static $_safe_feof, $_safe_fgets;
		if (!is_resource($pipe)) return false;
		$len = is_integer($len) && $len > 0 ? $len : 1024;
		$timeout = is_numeric($timeout) && ($timeout = (float) $timeout) >= 0 ? $timeout : (float) ini_get('default_socket_timeout');
		if (!$_safe_feof) $_safe_feof = function($fp, &$start=null){
			$start = microtime(1);
			return is_resource($fp) ? feof($fp) : true;
		};
		if (!$_safe_fgets) $_safe_fgets = function($fp, $len, &$start=null){
			$start = microtime(1);
			return is_resource($fp) ? fgets($fp, $len) : false;
		};
		$val = false;
		$start = null;
		$t_start = microtime(1);
		$eta = null;
		while (!$_safe_feof($pipe, $start) && (!$timeout || !static::timed_out($timeout, $start, $eta))){
			
			//DEBUG:
			if (is_resource($pipe)){
				$c = fgetc($pipe);
				print("fgetc: " . json_encode($c) . "\n"); //DEBUG:
				$val = fgets($pipe, $len);
				print("fgets: " . json_encode($val) . "\n"); //DEBUG:
			}

			/*
			print("safe_fgets: timeout: $timeout, eta: $eta)\n"); //DEBUG:
			while (($val = $_safe_fgets($pipe, $len, $start)) !== true || !static::timed_out(!$timeout ? 1 : $timeout, $start, $eta)){
				print("safe_fgets: " . json_encode($val) . " (timeout: $timeout, eta: $eta)\n"); //DEBUG:
				break;
			}
			*/
			//$val = is_resource($pipe) ? fgets($pipe, $len) : false;
			break;
		}

		//DEBUG:
		$eta = round(microtime(1) - $t_start, 4);
		print("fgets: " . json_encode($val) . " (timeout: $timeout, eta: $eta)\n");
		
		return is_string($val) ? $val : false;
	}

	/**
	 * Check if resource is seekable.
	 * 
	 * @param  resource  $pipe  - Stream resource.
	 * @param  array     $meta  - Meta data (stream_get_meta_data: [seekable, ])
	 * @return bool
	 */
	public static function seekable($pipe=null, array &$meta=null){
		$meta = is_resource($pipe) && is_array($meta = stream_get_meta_data($pipe)) ? $meta : [];
		return isset($meta['seekable']) && $meta['seekable'];
	}

	/**
	 * End output buffer.
	 * 
	 * @param  bool  $clean           - If true, use ob_end_clean() otherwise (false) use ob_end_flush().
	 * @param  bool  $implicit_flush  - Enable ob_implicit_flush flag.
	 * @return void
	 */
	public static function ob_end(bool $clean=true, bool $implicit_flush=true){
		while (ob_get_level()) ($clean ? ob_end_clean() : ob_end_flush());
		ob_implicit_flush($implicit_flush);
	}

	/**
	 * Buffer listener (fgets).
	 * 
	 * - Callback is called with argument: [string $buffer]
	 * 
	 * - Config options: $options = [
	 *     'print' => 0,           - int print output buffer mode (0 = disabled, 1 = print only, 2 = ob_end_flush, ob_implicit_flush, print, 3 = ob_end_clean, ob_implicit_flush, print).
	 *     'pid' => null,          - int process pid (if read fails, aborts if is not running).
	 *     'seek' => null,         - int fseek offset.
	 *     'length' => 1024,       - int fgets length.
	 *     'delay_ms' => 500,      - int read loop delay milliseconds (used on read retry or seekable resource).
	 *     'fgets_timeout' => 5,   - float fgets socket timeout (seconds).
	 *     'retry_timeout' => 10,  - float read retry timeout (seconds).
	 *   ];
	 * 
	 * - Abort reasons:
	 *   $abort = null - Read failure.
	 *   $abort = -3   - Read retry timed out.
	 *   $abort = -2   - Process closed (if options pid is provided).
	 *   $abort = -1   - Connection aborted.
	 *   $abort = 0    - Read (fgets) returned false.
	 *   $abort = 1    - Read (fgets) returned empty string.
	 *   $abort = 2    - Callback returned false.
	 * 
	 * @param  mixed     $pipe      - Stream resource handle | String file path.
	 * @param  callable  $callback  - Closure/Method buffer callback.
	 * @param  array     $options   - Buffer options (see config options above).
	 * @param  int       $abort     - Buffer abort cause (see abort reasons above).
	 * @param  string    $error     - Error message (set when result is false).
	 * @return bool
	 */
	public static function buffer($pipe=null, $callback=null, array $options=null, int &$abort=null, string &$error=null){
		static $default_options;
		$abort = null;
		$error = null;
		$fopen = 0;
		if (is_string($pipe) && ($path = trim($pipe))){
			$pipe = null;
			if (file_exists($path) && is_file($path)){
				$pipe = fopen($path, 'rb');
				if (!is_resource($pipe)){
					$error = 'Buffer file open failure. (' . $path . ')';
					return false;
				}
				$fopen = 1;
			}
			unset($path);
		}
		if (!is_resource($pipe)){
			$error = 'Buffer pipe resource is invalid.';
			return false;
		}
		if (!is_callable($callback)) $callback = null;
		if (!$default_options) $default_options = [
			'print' => 0,
			'pid' => null,
			'seek' => null,
			'length' => 1024,
			'delay_ms' => 500,
			'fgets_timeout' => 5,
			'retry_timeout' => 10,
		];
		if (is_array($options) && !empty($options)){
			$opts = [];
			if (array_key_exists($key = 'print', $options) && is_integer($val = $options[$key]) && in_array($val, [1, 2, 3])) $opts[$key] = $val;
			if (array_key_exists($key = 'pid', $options) && is_integer($val = $options[$key]) && $val > 0) $opts[$key] = $val;
			if (array_key_exists($key = 'seek', $options) && is_integer($val = $options[$key]) && $val >= 0) $opts[$key] = $val;
			if (array_key_exists($key = 'length', $options) && is_integer($val = $options[$key]) && $val > 0) $opts[$key] = $val;
			if (array_key_exists($key = 'delay_ms', $options) && is_integer($val = $options[$key]) && $val > 0) $opts[$key] = $val;
			if (array_key_exists($key = 'fgets_timeout', $options) && (is_null($val = $options[$key]) || is_numeric($val = $options[$key]) && ($val = (float) $val) >= 0)) $opts[$key] = $val;
			if (array_key_exists($key = 'retry_timeout', $options) && (is_null($val = $options[$key]) || is_numeric($val = $options[$key]) && ($val = (float) $val) >= 0)) $opts[$key] = $val;
			$options = array_replace($default_options, $opts);
			unset($val, $opts);
		}
		else $options = $default_options;
		$seekable = static::seekable($pipe);
		if ($seekable && is_integer($options['seek']) && fseek($pipe, $options['seek'])){
			if ($fopen) fclose($pipe);
			$error = 'Buffer pipe resource seek (' . $options['seek'] . ') failure.';
			return false;
		}
		$fail = 0;
		$closed = 0;
		$delay_ms = $options['delay_ms'];
		$ignore_user_abort = ignore_user_abort();
		ignore_user_abort(1);
		if ($options['print'] === 2) static::ob_end(0, 1);
		elseif ($options['print'] === 3) static::ob_end(1, 1);
		while (1){
			
			//DEBUG:
			$ts = microtime(1);
			$buffer = false;
			if (is_resource($pipe)){
				if (!feof($pipe)){
					//$buffer = fgets($pipe);
					$buffer = fgets($pipe, $options['length']);
					//print("fgets buffer: " . json_encode($buffer) . "\n");
				}
			}
			$tt = round(microtime(1) - $ts, 4);
			print("buffer: " . json_encode($buffer) . " ($tt s)\n");
			//DEBUG:...

			//$buffer = static::ffgets($pipe, $options['length'], $options['fgets_timeout']);
			if (!strlen($buffer)){
				if (!$fail) $fail = microtime(1);
				elseif ($options['retry_timeout'] && self::timed_out($options['retry_timeout'], $fail)){
					$abort = -3;
					break;
				}
				if ($options['pid']){
					if ($closed){
						$abort = -2;
						break;
					}
					elseif (!self::exists($options['pid'])) $closed = 1;
				}
				else {
					$abort = $buffer === false ? 0 : 1;
					break;
				}
			}
			else {
				if ($fail) $fail = 0;
				if ($options['print']){
					print($buffer);
					if (in_array($options['print'], [2, 3])) flush();
				}
				if ($callback && $callback($buffer) === false){
					$abort = 2;
					break;
				}
			}
			if (connection_aborted() && !$ignore_user_abort){
				$abort = -2;
				break;
			}
			if (!$closed && ($fail || $seekable)) usleep($delay_ms * 1000);
		}
		if ($options['print'] === 2) static::ob_end(1, 1);
		elseif ($options['print'] === 3) static::ob_end(1, 1);
		if ($fopen) fclose($pipe);
		if (!$ignore_user_abort) ignore_user_abort(0);
		return true;
	}
}