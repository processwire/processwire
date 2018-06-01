<?php namespace ProcessWire;

// this template is very much like the basic-page template except that it
// demonstrates making the headline, body and sidebar fields editable on the
// front-end, using the <edit> tags

?>

<h1 id='content-head'>
	<edit field='headline'><?=page()->headline?></edit>
</h1>

<div id='content-body'>
	<edit field='body'><?=page()->body?></edit>
	<?=ukDescriptionListPages(page()->children)?> 
</div>

<aside id='sidebar'>
	<?=ukNav(page()->rootParent, "depth=3, class=uk-margin-medium-bottom")?>
	<edit field='sidebar'><?=page()->sidebar?></edit>
</aside>
