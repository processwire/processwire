<?php namespace ProcessWire; ?>

<div id='content-body'>
	<?php
	$home = pages()->get('/');
	echo ukNav($home, [ 'depth' => 4 ])
	?>
</div>	

