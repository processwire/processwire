<?php namespace ProcessWire; if(!defined("PROCESSWIRE")) die(); ?>

	<p><a id="debug_toggle" href="#debug"><i class='fa fa-cog'></i> <?php echo __('Debug Mode Tools', __FILE__); ?></a></p>

	<div id="debug">

		<div class="container">
			<h3><a href='#'><?php echo __('Pages Loaded', __FILE__); ?></a></h3>
			<div>
			<table>
			<thead>
			<tr>
			<th>ID</th>
			<th>Path</th>
			<th>Title</th>
			</tr>
			</thead>
			<tbody>
			<?php 
			foreach($pages->getCache() as $p) {
				echo "\n<tr><td>$p->id</td><td>$p->path</td><td>$p->title</td></tr>";
			}
			?>
			</tbody>
			</table>
			</div>
		</div>

		<div class="container">
			<h3><a href='#'><?php echo __('API Variables', __FILE__); ?></a></h3>
			<div>
			<table>
			<thead>
			<tr>
			<th>Name</th>
			<th>Class</th>
			</tr>
			</thead>
			<tbody>
			<?php 
			foreach($fuel as $key => $value) {
				if(!is_object($value)) continue; 
				echo "\n<tr><td><a target='_blank' href='http://processwire.com/api/variables/$key/'>\$$key</a></td>" . 
					"<td>" . get_class($value) . "</td></tr>"; 
			}
			?>
			</tbody>
			</table>
			</div>
		</div>

		<div class="container">
			<h3><a href='#'><?php echo __('Session', __FILE__); ?></a></h3>
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
			foreach($session as $key => $value) {
				if(is_object($value)) $value = (string) $value; 
				if(is_array($value)) $value = print_r($value, true); 
				echo "<tr><td>$key</td><td><pre>" . $sanitizer->entities($value) . "</pre></td></tr>";
			} 
			?>
			</tbody>
			</table>
			</div>
		</div>

		<div class="container">
			<h3><a href='#'><?php echo __('Modules Loaded', __FILE__); ?></a></h3>
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
			<?php
			$numLoaded = 0;
			$numSkipped = 0;
			foreach($modules as $module) {
				if($module instanceof ModulePlaceholder) {
					$numSkipped++;
					continue; 
				}
				$numLoaded++;
				$info = $modules->getModuleInfo($module, array('verbose' => false));
				echo "<tr>";
				echo "<td>$info[name]</td>"; 
				echo "<td>$info[version]</td>";
				echo "<td>$info[title]</td>";
				echo "</tr>";
			}
			?>
			</tbody>
			</table>
			<p class='description'><?php echo 
				sprintf(__('%d modules loaded', __FILE__), $numLoaded) . ' / ' . 
				sprintf(__('%d not loaded', __FILE__), $numSkipped); 
			?></p>
			</div>
		</div>

		<div class="container">
			<h3><a href='#'><?php echo __('Hooks', __FILE__); ?></a></h3>
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
			<?php 
			$hooks = array_merge($this->wire()->getHooks('*'), Wire::$allLocalHooks); 
			ksort($hooks); 
			foreach($hooks as $key => $hook) {
				$suffix = $hook['options']['type'] == 'method' ? '()' : '';
				$toObject = !empty($hook['toObject']) ? $hook['toObject'] : '';
				$toMethod = $hook['toMethod'];
				if(is_callable($toMethod)) $toMethod = 'anonymous function';
				echo "<tr>"; 
				echo "<td>" . ($hook['options']['before'] ? 'before ' : '') . ($hook['options']['after'] ? 'after' : '') . "</td>";
				echo "<td>" . ($hook['options']['fromClass'] ? $hook['options']['fromClass'] . '::' : '') . "$hook[method]$suffix</td>";
				echo "<td>" . ($toObject ? "$toObject::$toMethod" : $toMethod) . "()</td>";
				echo "<td>" . ($hook['options']['allInstances'] || $hook['options']['fromClass'] ? "class " : "instance ") . $hook['options']['type'] . "</td>";
				echo "<td>" . $hook['options']['priority'] . "</td>";
				echo "</tr>";
			}
			?>
			</tbody>
			</table>
			</div>

		</div>

		<div class="container">
			<h3><a href='#'><?php echo __('PDO Queries ($database)', __FILE__); ?></a></h3>
			<div>
			<table>
			<tbody>
			<?php foreach(WireDatabasePDO::getQueryLog() as $n => $sql) echo "\n<tr><th>$n</th><td>$sql</td></tr>"; ?>
			</tbody>
			</table>
			</div>
		</div>
		
		<div class="container">
			<h3><a href='#'><?php echo __('Timers', __FILE__); ?></a></h3>
			<div>
				<table>
					<tbody>
					<?php 
					$timers = Debug::getSavedTimers();
					foreach($timers as $name => $timer) echo "\n<tr><th>$name</th><td>$timer</td></tr>"; ?>
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

		<?php if($db->isInstantiated()): ?>
		<div class="container">
			<h3><a href='#'><?php echo __('MySQLi Queries ($db) - Deprecated', __FILE__); ?></a></h3>
			<div>
			<?php
			$queries = Database::getQueryLog();
			if(count($queries)): ?>
			<p>Mysqli was instantiated and queries were made. These should ideally be converted to PDO ($database). Here are the queries:</p>
			<table>
			<tbody>
			<?php foreach($queries as $n => $sql) echo "\n<tr><th>$n</th><td>$sql</td></tr>"; ?>
			</tbody>
			</table>
			<?php else: ?>
			<p>Mysqli was instantiated but no queries executed. Here are the callers that should be converted to PDO:</p>
			<ul><?php foreach($db->getCallers() as $n => $caller) echo "<li>" . ($n+1) . ". $caller</li>"; ?></ul>
			<?php
			endif; 
			unset($queries);
			?>
			</div>
		</div>
		<?php endif; ?>

		<div class="container">
			<h3><a href='#'><?php echo __('User', __FILE__); ?> (<?php echo $user->name;?>)</a></h3>
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

		<?php 
		foreach(array('get', 'post', 'cookie') as $type): 
			$i = $input->$type; 
			if(!count($i)) continue; 
			?>
			<div class="container">
				<h3><a href='#'>$input-><?php echo $type; ?></a></h3>
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
				?>
				</tbody>
				</table>
				</div>
			</div>

		<?php endforeach; ?>


	</div>

	<script type="text/javascript">
		$(document).ready(function() {
            $("#debug").accordion({
                header: 'h3',
                heightStyle: 'content'
            }).hide();
            $("#debug_toggle").click(function() {
                if ($('#debug').is(":hidden"))
                    $("#debug").slideDown();
                else
                    $("#debug").slideUp();
                var n = $(document).height();
                $('html, body').animate({ scrollTop: n },'50');
                return false;
            });
        });
	</script>

