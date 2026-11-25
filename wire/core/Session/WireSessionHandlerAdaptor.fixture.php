<?php namespace ProcessWire;

/** Isolated CLI fixture: never boots a site or changes its session handler. */
if(PHP_SAPI !== 'cli' || !in_array('--session-adaptor-fixture', $_SERVER['argv'] ?? [], true)) return;
require_once(dirname(__DIR__) . '/ProcessWire.php');
require_once(__DIR__ . '/WireSessionHandler.php');

error_reporting(E_ALL);
set_error_handler(function($severity, $message, $file, $line) {
	throw new \ErrorException($message, 0, $severity, $file, $line);
});
require_once(__DIR__ . '/WireSessionHandlerAdaptor.php');
ini_set('session.use_cookies', '0');
ini_set('session.cache_limiter', '');
ini_set('session.use_strict_mode', '1');
ini_set('session.gc_probability', '0');
$handler = new class extends WireSessionHandler {
	public $data = [];
	public $reads = 0;
	public function read($id) { $this->reads++; return $this->data[$id] ?? ''; }
	public function write($id, $data) { $this->data[$id] = $data; return true; }
	public function destroy($id) { unset($this->data[$id]); return true; }
	public function gc($seconds) { return 0; }
};
$adaptor = new WireSessionHandlerAdaptor($handler);
$result = [];
$result['Registering adaptor succeeds'] = session_set_save_handler($adaptor, false);
$result['New session starts'] = session_start();
$first = session_id();
$result['Native generator returns a valid ID'] = strlen($first) >= 22 && preg_match('/\A[A-Za-z0-9,-]+\z/', $first) === 1;
$_SESSION['probe'] = 'retained';
session_write_close();
$handler->reads = 0;
session_id($first);
session_start();
$result['Existing session keeps its ID'] = session_id() === $first;
$result['Existing session retains data'] = ($_SESSION['probe'] ?? '') === 'retained';
$result['Existing session is read once'] = $handler->reads === 1;
$result['Session ID regeneration succeeds'] = session_regenerate_id(true);
$second = session_id();
$result['Regeneration creates a different ID'] = $second !== $first;
$result['Regeneration retains session data'] = ($_SESSION['probe'] ?? '') === 'retained';
session_write_close();
$unknown = bin2hex(random_bytes(16));
session_id($unknown);
session_start();
$result['Strict mode rejects an unknown ID'] = session_id() !== $unknown;
session_write_close();
echo json_encode($result);
