<?php namespace ProcessWire;

/** @var ProcessWire $wire */
/** @var Config $config */
/** @var Pages $pages */
/** @var Sanitizer $sanitizer */
/** @var Session $session */
/** @var WireInput $input */
/** @var Fuel $fuel */
/** @var Modules $modules */
/** @var WireDatabasePDO $database */
/** @var DatabaseMysqli $db */
/** @var User $user */
/** @var Page $page */
/** @var WireCache $cache */
/** @var WireClassLoader $classLoader */

if(!defined("PROCESSWIRE")) die();
Debug::saveTimer('all');
Debug::saveTimer("page.$page.render");
$debugToolsLabel = __('Debug Mode Tools', __FILE__);

echo 
	"<p><a class='pw-panel' data-panel-id='debug' data-panel-width='75%' " . 
	"data-tab-text='$debugToolsLabel' data-tab-icon='bug' data-tab-offset='200' " . 
	"id='debug_toggle' href='#debug'><i class='fa fa-bug'></i> $debugToolsLabel</a></p>";

?>

<div id="debug">
	
<?php foreach($config->debugTools as $tool): ?>

	<?php if($tool == 'pages'): ?>
		<?php
		$o = '';
		$oc = 0;
		$oct = $pages->loader()->getTotalPagesLoaded();
		foreach($pages->getCache() as $p) {
			$oc++;
			$parts = explode('/', trim($p->path, '/'));
			$name = array_pop($parts);
			$path = implode('/', $parts) . "/$name/";
			$path = '/' . ltrim($path, '/');
			$path = str_replace("/$name/", "/<b>$name</b>/", $path);
			$o .= "\n<tr>" .
				"<td>$p->id</td>" .
				"<td>$path</td>" .
				"<td>" . wireClassName($p) . "</td>" .
				($config->debug === Config::debugVerbose ? "<td>" . $sanitizer->entities($p->_debug_loader) . "</td>" : "") .
				"</tr>";
		}
		$occ = $oct > $oc ? "$oc/$oct" : $oc;
		?>
		<div class="container">
			<h3><a href='#'><?php echo __('Pages Loaded', __FILE__) . " <span class='ui-priority-secondary'>($occ)</span>"; ?></a></h3>
			<div>
			<table>
			<thead>
			<tr>
			<th>ID</th>
			<th>Path</th>
			<th>Type</th>
			<?php if($config->debug === Config::debugVerbose) echo "<th>Loader</th>"; ?>
			</tr>
			</thead>
			<tbody>
			<?php 
			echo $o; 
			if($oct > $oc) echo "<p>A total of <strong>$oct</strong> pages were loaded, <strong>$oc</strong> of which are in memory:</p>";
			unset($o, $oc, $oct, $occ); 
			?> 
			</tbody>
			</table>
			</div>
		</div>
	<?php endif; ?>

	<?php if($tool == 'api'): ?>
		<?php
		$o = '';
		$oc = 0;
		$apiVars = array();
		foreach($fuel as $key => $value) {
			if(!is_object($value)) continue;
			$apiVars[$key] = $value; 
		}
		ksort($apiVars);
		foreach($apiVars as $key => $value) {
			$oc++;
			$o .= "\n" . 
				"<tr>" . 
				"<td><a target='_blank' href='https://processwire.com/api/ref/$key/'>\$$key</a></td>" .
				"<td>" . get_class($value) . "</td>" . 
				"</tr>";
		}
		unset($apiVars);
		?>
		<div class="container">
			<h3><a href='#'><?php echo __('API Variables', __FILE__) . " <span class='ui-priority-secondary'>($oc)</span>"; ?></a></h3>
			<div>
			<table>
			<thead>
			<tr>
			<th>Name</th>
			<th>Class</th>
			</tr>
			</thead>
			<tbody>
			<?php echo $o; unset($o, $oc); ?>
			</tbody>
			</table>
			</div>
		</div>
	<?php endif; ?>

	<?php if($tool == 'session'): ?>
		<?php
		$o = ''; $oc = 0;
		foreach($session as $key => $value) {
			if(is_object($value)) {
				if(method_exists($value, '__toString')) {
					$value = (string) $value;
				} else if(method_exists($value, '__debugInfo')) {
					$value = print_r($value, true);
				} else {
					$value = get_class($value);
				}
			} else if(is_array($value)) {
				$value = print_r($value, true);
			}
			$oc++;
			$o .= "<tr><td>$key</td><td><pre>" . $sanitizer->entities($value) . "</pre></td></tr>";
		} 
		?>
		<div class="container">
			<h3><a href='#'><?php echo __('Session', __FILE__) . " <span class='ui-priority-secondary'>($oc)</span>"; ?></a></h3>
			<div>
			<table>
			<thead>
			<tr>
			<th>Key</th>
			<th>Value</th>
			</tr>
			</thead>
			<tbody>
			<?php echo $o; unset($o, $oc); ?>
			</tbody>
			</table>
			</div>
		</div>
	<?php endif; ?>

	<?php if($tool == 'modules'): ?>
		<?php
		$o = '';
		$numLoaded = 0;
		$numSkipped = 0;
		foreach($modules as $module) {
			if($module instanceof ModulePlaceholder) {
				$numSkipped++;
				continue;
			}
			$numLoaded++;
			$info = $modules->getModuleInfo($module, array('verbose' => false));
			$o .= 
				"<tr>" . 
				"<td>$info[name]</td>" . 
				"<td>$info[version]</td>" . 
				"<td>$info[title]</td>" . 
				"</tr>";
		}
		
		?>
		<div class="container">
			<h3><a href='#'><?php echo __('Modules Loaded', __FILE__) . " <span class='ui-priority-secondary'>($numLoaded/$numSkipped)</span>"; ?></a></h3>
			<div>
			<table>
			<thead>
			<tr>
			<th>Class</th>
			<th>Version</th>
			<th>Title</th>
			</tr>
			</thead>
			<tbody>
			<?php echo $o; unset($o); ?>
			</tbody>
			</table>
			<p class='description'><?php echo 
				sprintf(__('%d modules loaded', __FILE__), $numLoaded) . ' / ' . 
				sprintf(__('%d not loaded', __FILE__), $numSkipped); 
				unset($numLoaded, $numSkipped);
			?></p>
			</div>
		</div>
	<?php endif; ?>

	<?php if($tool == 'hooks'): ?>
		<?php
		$o = '';
		$oc = 0;
		$hooks = array_merge($this->wire()->getHooks('*'), $this->wire('hooks')->getAllLocalHooks());
		$hooksSorted = array();
		foreach($hooks as $hook) {
			$whenKey = $hook['options']['before'] ? '0' : '1';
			$sortKey = $hook['options']['fromClass'] . ":$hook[method]:$whenKey:" . $hook['options']['priority'];
			$hooksSorted[$sortKey] = $hook;
			$oc++;
		}
		ksort($hooksSorted);
		foreach($hooksSorted as $hook) {
			$suffix = $hook['options']['type'] == 'method' ? '()' : '';
			$toObject = !empty($hook['toObject']) ? $hook['toObject'] : '';
			$toMethod = $hook['toMethod'];
			if(is_callable($toMethod)) $toMethod = 'anonymous function';
			$o .= 
				"<tr>" . 
				"<td>" . ($hook['options']['before'] ? 'before ' : '') . ($hook['options']['after'] ? 'after' : '') . "</td>" . 
				"<td>" . ($hook['options']['fromClass'] ? $hook['options']['fromClass'] . '::' : '') . "$hook[method]$suffix</td>" . 
				"<td>" . ($toObject ? wireClassName($toObject) . "::$toMethod" : $toMethod) . "()</td>" .
				"<td>" . ($hook['options']['allInstances'] || $hook['options']['fromClass'] ? "class " : "instance ") . $hook['options']['type'] . "</td>" . 
				"<td>" . $hook['options']['priority'] . "</td>" . 
				"</tr>";
		}
		
		?>
		<div class="container">
			<h3><a href='#'><?php echo __('Hooks', __FILE__) . " <span class='ui-priority-secondary'>($oc)</span>"; ?></a></h3>
			<div>
			<table>
			<thead>
			<tr>
			<th>When</th>
			<th>Method::object</th>
			<th>Visited by</th>
			<th>Type</th>
			<th>Priority</th>
			</tr>
			</thead>
			<tbody>
			<?php echo $o; unset($o, $oc); ?>
			</tbody>
			</table>
			</div>
		</div>
	<?php endif; ?>

	<?php if($tool == 'database' && $database): ?>
		<?php
		$o = '';
		$oc = 0;
		foreach($database->queryLog() as $n => $sql) {
			$oc++;
			$sql = $sanitizer->entities1($sql);
			$o .= "\n<tr><th>$n</th><td>$sql</td></tr>";
		}
		?>
		<div class="container">
			<h3><a href='#'><?php echo __('PDO Queries ($database)', __FILE__) . " <span class='ui-priority-secondary'>($oc)</span>"; ?></a></h3>
			<div>
			<table>
			<tbody>
			<?php echo $o; unset($o, $oc); ?>
			</tbody>
			</table>
			</div>
		</div>
	<?php endif; ?>

	<?php if($tool == 'timers'): ?>
		<?php
		$o = ''; $oc = 0;
		$timers = Debug::getSavedTimers();
		$timer1 = '';
		foreach($timers as $name => $timer) {
			if($timer1 === '') $timer1 = $timer;
			$o .= "<tr><th>$name</th><td>$timer</td></tr>";
			$oc++;
		}
		?>
		<div class="container">
			<h3><a href='#'><?php echo __('Timers', __FILE__) . " <span class='ui-priority-secondary'>($oc) {$timer1}s</span>"; ?></a></h3>
			<div>
				<table>
					<tbody>
					<?php echo $o; unset($o, $oc, $timers, $name, $timer, $timer1); ?>
					</tbody>
				</table>
				<p class='description'>To add more timers here&hellip;</p>
				<pre>
Debug::timer('timer-name'); // start timer, you make up the name
execute_some_code(); // any amount of code you want to time
Debug::saveTimer('timer-name', 'optional notes'); // stop and save timer
				</pre>
			</div>
		</div>
	<?php endif; ?>

	<?php if($tool == 'db' && $db->isInstantiated()): ?>
		<?php
		$queries = $database->queryLog();
		$oc = count($queries);
		?>
		<div class="container">
			<h3><a href='#'><?php echo __('MySQLi Queries ($db) - Deprecated', __FILE__) . " <span class='ui-priority-secondary'>($oc)</span>"; ?></a></h3>
			<div>
			<?php
			if(count($queries)): ?>
			<p>Mysqli was instantiated and queries were made. These should ideally be converted to PDO ($database). Here are the queries:</p>
			<table>
			<tbody>
			<?php 
			foreach($queries as $n => $sql) {
				$sql = $sanitizer->entities1($sql);
				echo "\n<tr><th>$n</th><td>$sql</td></tr>";
			}
			?>
			</tbody>
			</table>
			<?php else: ?>
			<p>Mysqli was instantiated but no queries executed. Here are the callers that should be converted to PDO:</p>
			<ul><?php foreach($db->getCallers() as $n => $caller) echo "<li>" . ($n+1) . ". $caller</li>"; ?></ul>
			<?php
			endif; 
			unset($queries, $oc, $sql);
			?>
			</div>
		</div>
	<?php endif; ?>

	<?php if($tool == 'user'): ?>
		<div class="container">
			<h3><a href='#'><?php echo __('User', __FILE__); ?> <span class='ui-priority-secondary'>(<?php echo $user->name;?>)</span></a></h3>
			<div>
			<h4><?php echo __('Current User Roles', __FILE__); ?></h4>
			<ol>
			<?php foreach($user->roles as $role) echo "\n<li>{$role->name}</li>"; ?>
			</ol>
			<h4><?php echo __('Current User Permissions', __FILE__); ?></h4>
			<ol>
			<?php foreach($user->getPermissions() as $permission) echo "\n<li>{$permission->name}</li>"; ?>
			</ol>
			<h4><?php echo __('Current User Permissions on this page', __FILE__); ?></h4>
			<ol>
			<?php foreach($user->getPermissions($page) as $permission) echo "\n<li>{$permission->name}</li>"; ?>
			</ol>
			</div>
		</div>
	<?php endif; ?>

	<?php if($tool == 'input'):
		foreach(array('get', 'post', 'cookie') as $type): 
			$i = $input->$type; 
			$oc = count($i);
			if(!$oc) continue; 
			?>
			<div class="container">
				<h3><a href='#'>$input-><?php echo $type . " <span class='ui-priority-secondary'>($oc)</span>"; ?></a></h3>
				<div>
				<table>
				<thead>
				<tr>
				<th>Key</th>
				<th>Value</th>
				</tr>
				</thead>
				<tbody>
				<?php 
				foreach($i as $key => $value) {
					if(is_array($value)) $value = print_r($value, true); 
					echo "<tr><td>" . $sanitizer->entities($key) . "</td><td><pre>" . $sanitizer->entities($value) . "</pre></td></tr>";
				} 
				unset($oc, $i);
				?>
				</tbody>
				</table>
				</div>
			</div>
		<?php endforeach; ?>
	<?php endif; ?>
	
	<?php if($tool == 'cache'): ?>
		<?php
		$o = '';	
		$oc = 0;
		foreach($cache->getInfo(true, array(), array('FileCompiler', 'Modules', 'Permissions.'), array('name', 'expires', 'size')) as $info) {
			$oc++;
			$o .= "<table class=''><thead><tr><th colspan='2'>";
			$o .= $sanitizer->entities($info['name']) . "</th></tr></thead><tbody>";
			foreach($info as $key => $value) {
				if($key === 'name' || $key === 'type') continue;
				if($key === 'size') $value = wireBytesStr($value);
				$key = $sanitizer->entities($key);
				$value = $sanitizer->entities($value);
				$o .= "<tr><th width='30%'>$key</th><td>$value</td></tr>";
			}
			$o .= "</tbody></table><br />";
		}
		?>
		<div class="container">
			<h3><a href='#'><?php echo __('Cache') . " <span class='ui-priority-secondary'>($oc)</span>"; ?></a></h3>
			<div>
				<p class='detail'><?php echo $cache->getCacheModule()->className(); ?></p>
				<?php echo $o; unset($o, $oc); ?>
			</div>
		</div>
	<?php endif; ?>
	
	<?php if($tool == 'autoload'): ?>
		<?php
		$oc = 0;
		$o = "<table class=''><thead><th>Class</th><th>File/Details</th><tbody>";
		foreach($classLoader->getDebugLog() as $className => $classFile) {
			$oc++;
			$className = $sanitizer->entities($className);
			$classFile = $sanitizer->entities($classFile);
			$o .= "<tr><th width='40%'>$className</th><td>$classFile</td></tr>";
		}
		$o .= "</tbody></table><br />";
		?>
		<div class="container">
			<h3><a href='#'><?php echo __('Autoload') . " <span class='ui-priority-secondary'>($oc)</span>"; ?></a></h3>
			<div>
				<?php echo $o; unset($o, $oc); ?>
			</div>
		</div>
	<?php endif; ?>	
	
	<?php if($tool == 'lazyload' && $config->useLazyLoading): ?>
		<?php
		$o = "<table class=''><thead><th>Class</th><th>Loaded</th><th>Not Loaded</th><tbody>";
		$numTotal = 0;
		$numLoadedTotal = 0;
		$numNotLoadedTotal = 0;
		foreach(array('fields', 'templates', 'fieldgroups') as $key) {
			/** @var WireSaveableItems $var */
			$var = $wire->$key;
			if(empty($var)) continue;
			$debugInfo = $var->__debugInfo();
			$numLoaded = count($debugInfo['loaded']); 
			$numNotLoaded = count($debugInfo['notLoaded']);
			$numEither = $numLoaded + $numNotLoaded;
			$numTotal += $numEither;
			$numLoadedTotal += $numLoaded;
			$numNotLoadedTotal += $numNotLoaded;
			sort($debugInfo['loaded']);
			sort($debugInfo['notLoaded']); 
			$o .= 
				"<tr>" . 
				"<td>" . $var->className()  . "&nbsp;<span class='detail'>($numLoaded/$numNotLoaded/$numEither)</span></td>" . 
				"<td>" . implode("<br />", $debugInfo['loaded']) . "</td>" . 
				"<td>" . implode("<br />", $debugInfo['notLoaded']) . "</td>" . 
				"</tr>";
		}
		$o .= "</tbody></table><br />";
		?>
		<div class="container">
			<h3><a href='#'><?php echo __('Lazy load') . " <span class='ui-priority-secondary'>($numLoadedTotal/$numNotLoadedTotal/$numTotal)</span>"; ?></a></h3>
			<div>
				<?php echo $o; unset($o, $var, $debugInfo, $numLoaded, $numNotLoaded, $numEither, $numTotal, $numLoadedTotal, $numNotLoadedTotal); ?>
			</div>
		</div>
	<?php endif; ?>


<?php endforeach; ?>
	
<p class='detail' style='margin: 0; padding: 0.25em 1em;'>
	<?php echo __('Do not leave debug mode enabled in live/production environments (see $config->debug in file /site/config.php).'); ?>
</p>
	
</div><!--/#debug-->

<script type="text/javascript">
	$(document).ready(function() {
		var $debug = $("#debug");
		function initDebugAccordion() {
			$debug.accordion({
				active: false,
				header: 'h3',
				collapsible: true,
				heightStyle: 'content',
				classes: {
					'ui-accordion-header': 'ui-helper-reset ui-corner-top'
				}
			});
		}
		if(typeof pwPanels == "undefined" || $('body').hasClass('touch')) {
			initDebugAccordion();
			$debug.hide();
			$("#debug_toggle").on('click', function() {
				if($debug.is(":hidden")) {
					$debug.slideDown();
				} else {
					$debug.slideUp();
				}
				$('html, body').animate({ scrollTop: $(document).height() },'50');
				return false;
			}); 
		} else {
			$debug.on('pw-panel-init', function() {
				console.log('pw-panel-init');
				initDebugAccordion();
			});
		}
	}); 
</script>
