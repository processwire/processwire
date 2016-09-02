<?php namespace ProcessWire;
?><ul id='notices' class='ui-widget'>
<?php

foreach($notices as $notice) {

	$class = 'ui-state-highlight NoticeMessage';
	$text = $notice->text; 
	$icon = '';

	if($notice instanceof NoticeError || $notice->flags & Notice::warning) {
		$class = 'ui-state-error'; 
		if($notice->flags & Notice::warning) {
			$class .= ' NoticeWarning';
			$icon = 'warning';
		} else {
			$class .= ' ui-priority-primary NoticeError';
			$icon = 'exclamation-triangle'; 
		}
	}

	if($notice->flags & Notice::debug) {
		$class .= ' ui-priority-secondary NoticeDebug';
		$icon = 'gear';
	}

	if(!$icon) $icon = 'check-square';

	if($notice->class && $config->debug) $text = "{$notice->class}: $text";

	echo "\n\t\t<li class='$class'><div class='container'><p><i class='fa fa-$icon'></i> {$text}</p></div></li>";

}

echo "\n\t</ul><!--/notices-->";

