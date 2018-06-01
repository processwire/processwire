<?php namespace ProcessWire;

// look for a GET variable named 'q' and sanitize it
$q = input()->get('q');

// sanitize to text, which removes markup, newlines, too long, etc.
$q = sanitizer()->text($q);

// did $q have anything in it after sanitizing to text?
if($q) {

	// Make the search query appear in the top-right search box.
	// Always entity encode any user input that also gets output
	echo '<input id="search-query" value="' . sanitizer()->entities($q) . '">';

	// Sanitize for placement within a selector string. This is important for any 
	// values that you plan to bundle in a selector string like we are doing here.
	// It quotes them when necessary, and removes characters that might cause issues.
	$q = sanitizer()->selectorValue($q);

	// Search the title and body fields for our query text.
	// Limit the results to 50 pages. The has_parent!=2 excludes irrelevant admin
	// pages from the search, for when an admin user performs a search. 
	$selector = "title|body~=$q, limit=50, has_parent!=2";

	// Find pages that match the selector
	$matches = pages()->find($selector);
	
} else {
	$matches = array();
}

// unset the variable that we no longer need, since it can contain user input
unset($q);

?>
<div id='content-body'>
	<?php
	// did we find any matches?
	if(count($matches)) {
		// yes we did, render them
		echo ukAlert("Found $matches->count page(s)", "default", "search");
		echo ukDescriptionListPages($matches);
	} else {
		// we didn't find any
		echo ukAlert("Sorry, no results were found.", "danger", "warning");
	}
	?>
</div>
