<?php

include("./_head.php"); ?>

<div id='content'>

	<?php 
	
	$maxDepth = 4; 
	renderNavTree($pages->get('/'), $maxDepth); 
	// see the _init.php for the renderNavTree function
	
	?>

</div>

<?php include("./_foot.php"); ?>
