<?php

// Output navigation for any children below the bodycopy.
// This navigation cycles through the page's children and prints
// a link and summary: 

if($page->numChildren) {

	echo "<ul class='nav' role='navigation'>";

	foreach($page->children as $child) {
		echo "<li><p><a href='{$child->url}'>{$child->title}</a><br />";
		echo "<span class='summary'>{$child->summary}</span></p></li>"; 
	}

	echo "</ul>";
}

?>

			</div><!--/bodycopy-->

		</div><!--/container-->

	</div><!--/content-->

	<div id="footer" class="footer" role="contentinfo">
		<div class="container">
			<p>Powered by <a href='http://processwire.com'>ProcessWire Open Source CMS/CMF</a></p>
		</div>
	</div>

	<?php 

	// If the page is editable, then output a link that takes us straight to the page edit screen:
	if($page->editable()) {
		echo "<a class='nav' id='editpage' href='{$config->urls->admin}page/edit/?id={$page->id}'>Edit</a>"; 
	}

	?>

</body>
</html>
