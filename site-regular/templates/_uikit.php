<?php namespace ProcessWire;

/**
 * _uikit.php
 * 
 * ProcessWire library of Uikit 3 functions 
 * 
 * This library is not meant to replace use of Uikit markup, instead it is meant to provide
 * helpers to create blocks of markup you may re-use a lot in various parts of a site. The
 * functions present so far are those that were helpful for this particular blog site profile.
 * 
 * Most of the functions have an $options argument that lets you specify various options to
 * alter the default behaviors. The $options can be specified either as an associative array,
 * a selector string like "foo=bar, baz=foo" or a Uikit data attribute string like 
 * "foo: bar; baz: foo". If your values need to contain commas or semicolons, use an 
 * associative array rather than a string. Boolean options can also be expressed as just the 
 * property name by itself, which is assumed to be "property=true", whether used in an array,
 * or a string. 
 * 
 * All of the functions have detailed phpdoc comments, so please see the definition for 
 * details on how to use any of them. Below is a summary of the current list of functions. 
 * 
 * ukNav()
 * ukNavbar()
 * ukNavbarNav()
 * ukBreadcrumb()
 * ukAlert()
 * ukAlertSuccess()
 * ukAlertPrimary()
 * ukAlertWarning()
 * ukAlertDanger()
 * ukHeading()
 * ukHeading1()
 * ukHeading2()
 * ukHeading3()
 * ukHeading4()
 * ukIcon()
 * ukPagination()
 * ukDescriptionListPages()
 * ukBlogPost()
 * ukBlogPosts()
 * ukComment()
 * ukComments()
 * ukCommentForm()
 * 
 * 
 */


/**
 * Given a group of pages, render a <ul.uk-nav> navigation,
 *
 * This method can be used to render single-level or nested navigation lists.
 * To render nested navigation lists pass the starting Page object to the $items
 * argument and specify the 'depth' option as some number greater than 0.
 *
 * Optionally assign a `ukNavHeader` property to any page to show a
 * header (with the text in the property) above that page in the navigation.
 * Or if the `ukNavHeader` property is boolean true, then the page itself
 * will become a header item in the navigation.
 *
 * Optionally assign a `ukNavDivider` property with boolean true to any page
 * to show a divider before that page in the navigation.
 *
 * @param Page|PageArray|array $items
 * @param array|string $options Options to modify default behavior:
 *  - `ul` (bool): Specify false to return just the list items without the wrapping `<ul>` (default=true).
 *  - `type` (string): May be either "default" or "primary" (default="default").
 *  - `depth` (int): Maximum allowed depth for nested navigation (default=0).
 *  - `accordion` (bool): If paired with depth, use open/close accordion effect for subnav (default=false). 
 *  - `class` (string): Any additional class names to add to the `<ul>` (default='').
 *  - `header` (string|Page|bool): Nav header string, Page object, or boolean true to use Parent for header (default='').
 *  - `heading` (string|Page|bool): Alias that may be used instead of "header", but refers to the same thing.
 *  - `divider` (bool): Specify true to show a divider between root level items (default=auto).
 *  - `attr` (string): A string of additional tag attributes to add to the `<ul>` (default='').
 *  - `fields` (array): Any additional fields you want to display for each item.
 *  - `markup` (string): Optional markup to use inside item instead of default.
 *  - `openParent` (Page|int): When Page specified, only this parent will be open. (default=null)
 *  - `openParents` (array|PageArray): Same as openParent but multiple. Specify array of page IDs or a PageArray. (default=[])
 *  - `allowParents` (array|PageArray): If using depth, only allow these parent IDs, names or Page instances. (default=[])
 *  - `blockParents` (array|PageArray): If using depth, do not consider these IDs, names or Page instances as parents. (default=[])
 *  - `repeatParent` (bool|int): Show parent link again as first item in subnav? false=no, true=yes sub, 1=yes all (default=false).
 *  - `maxItems` (int): Max items to render in list (default=0, no max).
 *  - `maxNote` (string): Note shown at bottom of list when maxItems reached. (default='%d more not shown')
 *  - `maxLink` (bool): When max items reached, link the maxNote to the parent of items? (default=true)
 *  - `noNavQty` (int): If children in subnav exceed this amount, don't render subnav for them at all (default=0, disabled).
 * @return string
 *
 */
function ukNav($items, $options = array()) {

	static $depth = 0;

	$defaults = array(
		'ul' => true, // specify false to return only the <li> items with no <ul>
		'type' => 'default', // specify either "default" or "primary"
		'depth' => 0, // max depth allowed for nested lists
		'accordion' => false, // if paired with 'depth', makes nav use an accordion effect
		'class' => '', // any additional class names to add         
		'header' => '', // Navigation header text/html (or Page object) to show at top, or boolean true to use parent for header
		'heading' => '', // alias of header, for consistency with ukHeading()
		'divider' => !$depth, // show a divider between root level items? (null=auto)
		'attr' => '', // any additional attributes to add to the <ul>
		'fields' => array(), // any additional fields to show (array of field names)
		'markup' => '', // optional markup to use inside item instead of default 
		'itemLabel' => 'title|name', // field to use for item label/link, or pipe-separated list, or {var} string. 
		'allowParents' => array(), // if using depth, only show subnav for these pages (optional)
		'blockParents' => array(), // if using depth, do not show subnav for these pages (optional)
		'openParents' => array(), // when array of Page IDs, or PageArray specified, only open parents in this list
		'openParent' => 0, // when Page or id (int) specified, only this parent will be open
		'repeatParent' => false, // repeat parent link again as first item in subnav? (false=no, true=yes, 1=yes+root level too)
		'maxItems' => 0, // max items to render in a list (0=no max)
		'maxNote' => __('%d more (not shown)', __FILE__), // note shown at bottom of list when maxItems reached
		'maxLink' => true, // when max items exceeded, link the maxNote to the parent of those items?
		'noNavQty' => 0, // if children in nested subnav exceed this amount, don't render subnav for them at all (0=disabled)
	);

	// options that should not go past root depth
	if($depth) unset($options['divider'], $options['heading'], $options['header']);

	// combine defaults with custom options
	$options = _ukNavOptions(_ukMergeOptions($defaults, $options));
	$header = $options['heading'] ? $options['heading'] : $options['header'];
	$divider = "<li class='uk-nav-divider'></li>";
	$page = $items->wire('page'); // current page
	$class = $depth ? rtrim("uk-nav-sub $options[class]") : rtrim("uk-nav uk-nav-$options[type] $options[class]");
	$attr = rtrim("class='$class' $options[attr]");
	$out = $options['ul'] ? "<ul $attr>" : "";
	$itemsParent = null;
	$repeatParent = new NullPage();
	
	// if given a Page rather than a PageArray, use children as the items and use Page as the header
	if($items instanceof Page) {
		if(empty($header)) $header = $items;
		$itemsParent = $items;
		$items = $items->children();
	}

	// if we have a PageArray, get the total items an convert to regular arary
	if($items instanceof PageArray) {
		$totalItems = $items->getTotal();
		$itemsArray = $items->getArray();
	} else {
		$totalItems = count($items);
		$itemsArray = $items;
	}

	// if there are no items to render, exit now
	if(!count($items)) return '';

	// determine parent for items
	if(!$itemsParent) $itemsParent = reset($itemsArray)->parent();

	// determine if we should repeat the parent item in subnav 
	if(($options['repeatParent'] === true && $depth) || $options['repeatParent'] === 1) {
		$repeatParent = $itemsParent;
		array_unshift($itemsArray, $repeatParent);
	}

	// if header option is boolean true, it means show parent as header
	if($header === true) $header = $itemsParent;

	// cycle through all the items
	foreach($itemsArray as $n => $item) {

		$classes = array();
		$hasChildren = $item->hasChildren;
		$isParent = $hasChildren && $item->id != $repeatParent->id && _ukNavIsParent($item, $depth, $options);
		
		// determine if we should show a uk-nav-header
		if(!$header) $header = $item->get('ukNavHeader');

		if($header === true) {
			// boolean true $item pulled from item.ukNavHeader indicates item should represent a header
			$classes[] = 'uk-nav-header';
			$header = false;
			if($options['divider']) $out .= $divider;

		} else if($header) {
			// string or Page $header indicates header is a new item prepended to list
			$headerClass = 'uk-nav-header';
			if($header instanceof Page) {
				if($page->id == $header->id) $headerClass .= ' uk-active';
				$header = "<a href='$header->url'>$header->title</a>";
			}
			$out .= "<li class='$headerClass'>$header</li>";
			if($options['divider']) $out .= $divider;
			$header = false;
		}

		// determine if we should show a uk-nav-divider
		if(($options['divider'] && $n) || $item->get('ukNavDivider')) {
			$out .= $divider;
		}

		// determine additional classes
		if($item->id == $page->id) $classes[] = 'uk-active';
		if($isParent) $classes[] = 'uk-parent';
		if($repeatParent->id === $item->id) $classes[] = 'pw-uk-nav-parent';

		// open the list item
		$out .= count($classes) ? "<li class='" . implode(' ', $classes) . "'>" : "<li>";

		// get the link markup
		if(empty($options['markup'])) {
			$itemLabel = $item->get($options['itemLabel']);
			$out .= "<a href='$item->url'>$itemLabel</a>";
		} else {
			$out .= $item->getMarkup($options['markup']);
		}

		// markup for any additional fields specified
		foreach($options['fields'] as $name) {
			$value = $item->get($name);
			if(!$value) $value = "$name";
			if($value) $out .= "<div class='pw-field-$name'>$value</div>";
		}

		// see if we are working with a nested list and go recursive if so
		if($isParent) {
			$selector = "start=0,";
			// limit quantity of items if the maxItems option is in use
			if($options['maxItems'] && $options['maxItems'] < $hasChildren) {
				$selector .= "limit=$options[maxItems],";
			}
			// adjust depth and render children recursively
			$depth++;
			$out .= ukNav($item->children(trim($selector, ',')), $options);
			$depth--;
		}

		// close the list item
		$out .= "</li>";

		// if we've exceeded the max items we can display, end list with a note about it
		if($options['maxItems'] && $n+1 >= $options['maxItems'] && strlen($options['maxNote'])) {
			$o = sprintf($options['maxNote'], $totalItems - $n);
			if($options['maxLink']) $o = "<a href='{$item->parent->url}'>$o</a>";
			$out .= "<li class='pw-uk-nav-max'>$o</li>";
			break;
		}
	}

	if($options['ul']) $out .= "</ul>";

	return $out;
}

/**
 * Internal use: prep some options for ukNav function
 * 
 * @param array $options
 * @return array
 * 
 */
function _ukNavOptions(array $options) {
	
	// accordion mode requires some adjustments
	if($options['accordion']) {
		$options['class'] = trim("$options[class] uk-nav-parent-icon");
		$options['attr'] = trim("$options[attr] uk-nav");
	}
	
	$openParents = array();
	
	// if certain parents specified to be open, normalize to array of Page IDs
	if(!empty($options['openParents'])) {
		if($options['openParents'] instanceof PageArray) {
			foreach($options['openParents'] as $item) {
				$openParents[] = $item->id;
				// include the item's parents too (supported only when PageArray specified)
				foreach($item->parents() as $parent) $openParents[] = $parent->id;
			}
		} else if(is_array($options['openParents'])) {
			$openParents = $options['openParents'];
		}
	}

	// if openParent option in use, add it to openParents array
	if($options['openParent']) {
		if($options['openParent'] instanceof Page) {
			$openParents[] = $options['openParent']->id;
		} else if(is_int($options['openParent'])) {
			$openParents[] = $options['openParent'];
		}
	}

	// stuff back in options 
	$options['openParents'] = $openParents;
	
	if(!is_array($options['blockParents'])) $options['blockParents'] = array($options['blockParents']);
	if(!is_array($options['allowParents'])) $options['allowParents'] = array($options['allowParents']);
	
	return $options;
}

/**
 * Internal use: determine if item is allowed as a parent in ukNav()
 * 
 * @param Page $item
 * @param int $depth
 * @param array $options
 * @return bool
 * 
 */
function _ukNavIsParent(Page $item, $depth, array $options) {
	$isParent = true;
	if(!$options['depth'] || !$item->hasChildren) {
		$isParent = false;
	} else if($depth >= $options['depth']) {
		$isParent = false;
	} else if($options['noNavQty'] && $item->hasChildren > $options['noNavQty']) {
		$isParent = false;
	} else if(!empty($options['openParents']) && !in_array($item->id, $options['openParents'])) {
		$isParent = false;
	} else if(count($options['allowParents'])) {
		$isParent = false;
		foreach($options['allowParents'] as $p) {
			if($p !== $item && $item->id !== $p && $item->name !== $p) continue;
			$isParent = true;
			break;
		}
	} else if(count($options['blockParents'])) {
		foreach($options['blockParents'] as $p) {
			if($p !== $item && $item->id !== $p && $item->name !== $p) continue;
			$isParent = false;
			break;
		}
	}
	return $isParent;
}

/**
 * Return a Uikit Navbar 
 * 
 * This renders a simple navbar. See also the ukNavbarNav() method which lets you provide the wrapping
 * markup so that you can create more complex navbars with multiple alignments and such.
 * 
 * @param PageArray $items Items to appear in the navbar
 * @param array|string $options Options to modify default behavior: 
 *  - `align` (string): Alignment type: left, right or center (default=left). 
 *  - `class` (string): Any additional class name(s) to apply to the navbar container (default='').
 *  - `dropdown` (array): Array of page paths, page IDs, or template names where dropdown is allowed (default=all).
 * @return string
 * 
 */
function ukNavbar(PageArray $items, $options = array()) {
	
	$defaults = array(
		'align' => 'left', 
		'class' => '', 
		'dropdown' => null, 
	);
	
	$options = _ukMergeOptions($defaults, $options); 
	if($options['class']) $options['class'] = " $options[class]";
	
	$out = 
		"<nav class='uk-navbar-container$options[class]' uk-navbar>" . 
			"<div class='uk-navbar-$options[align]'>" . 
				ukNavbarNav($items, $options) . 
			"</div>" . 
		"</nav>";
	
	return $out; 
}

/**
 * Generates the uikit <ul.uk-navbar-nav> list
 *
 * Use this rather than the ukNavbar() function when you want to provide your own
 * wrapping markup. This function just returns the <ul.uk-navbar-nav> navigation list.
 * 
 * Example usage:
 * ~~~~~~
 * <nav class="uk-navbar-container" uk-navbar>
 *   <div class='uk-navbar-center'>
 *     <?
 *     $items = $pages->get('/')->children;
 *     echo ukNavbarNav($items)
 *     ?>
 *   </div>
 * </nav>
 * ~~~~~~
 * 
 * @param PageArray $items Items to display in the navbar
 * @param array|string $options Options to modify default behavior: 
 *  - `dropdown` (array): Array of page paths, page IDs, or template names where dropdown is allowed (default=all).
 * @return string
 * 
 */
function ukNavbarNav(PageArray $items, $options = array()) {
	
	if(!$items->count) return '';
	
	$defaults = array(
		'dropdown' => null, // array of page paths, page IDs, or template names where dropdown is allowed (null=allow all)
	);

	$options = _ukMergeOptions($defaults, $options);
	$page = $items->wire('page');
	$activeItems = $page->parents("id>1")->and($page);
	$out = "<ul class='uk-navbar-nav'>";
	$liActive = "<li class='uk-active'>";
	$li = "<li>";
	
	foreach($items as $item) {

		$out .= $activeItems->has($item) ? $liActive : $li;
		$out .= "<a href='$item->url'>$item->title</a>";
		
		// determine whether dropdown should be used for this $item
		$useDropdown = false;
		if($options['dropdown'] === null) {
			$useDropdown = $item->hasChildren && $item->id > 1;
		} else if($item->hasChildren && is_array($options['dropdown'])) {
			foreach($options['dropdown'] as $s) {
				if($item->template->name === $s || $page->path === $s || $page->id === $s) {
					$useDropdown = true;
					break;
				}
			}
		}

		if($useDropdown) {
			$out .= "<div class='uk-navbar-dropdown'>";
			$out .= "<ul class='uk-nav uk-navbar-dropdown-nav'>";
			foreach($item->children as $child) {
				$out .= $activeItems->has($child) ? $liActive : $li;
				$out .= "<a href='$child->url'>$child->title</a></li>";
			}
			$out .= "</ul></div>";
		}
		
		$out .= "</li>";
	}
	
	$out .= "</ul>";
	
	return $out; 
}

/**
 * Render a Uikit breadcrumb list from the given items
 * 
 * @param Page|PageArray|null $items
 * @param array|string $options Additional options to modify default behavior: 
 *  - `class` (string): Additional class name(s) to apply to the <ul.uk-breadcrumb>.
 *  - `appendCurrent` (bool): Append current page as non-linked item at the end? (default=false). 
 * @return string
 * 
 */
function ukBreadcrumb($items = null, $options = array()) {
	
	$defaults = array(
		'class' => '',
		'appendCurrent' => false, 
	);
	
	if($items === null) $items = page();
	if($items instanceof Page) $items = $items->parents();
	if(!$items->count) return '';
	
	$options = _ukMergeOptions($defaults, $options);
	$class = trim("uk-breadcrumb $options[class]"); 
	$out = "<ul class='$class'>";
	
	foreach($items as $item) {
		$out .= "<li><a href='$item->url'>$item->title</a></li>";
	}	
	
	if($options['appendCurrent']) {
		$page = $items->wire('page'); 
		$out .= "<li><span>$page->title</span></li>";
	}
	
	$out .= "</ul>";
	
	return $out; 
}

/**
 * Render a uikit alert box
 * 
 * @param string $html Text/html to display in the alert box
 * @param string $type Specify one of: success, primary, warning, danger or leave blank for none. 
 * @param string $icon Optionally specify a uikit icon name to appear in the alert box. 
 * @return string
 * 
 */
function ukAlert($html, $type = '', $icon = '') {
	$out = $type ? "<div class='uk-alert-$type uk-alert'>" : "<div uk-alert>";
	if($icon) $out .= ukIcon($icon) . ' ';
	$out .= $html . "<a class='uk-alert-close' uk-close></a></div>";
	return $out; 
}

/**
 * Render a success alert, shortcut for ukAlert('message', 'success'); 
 * 
 * @param string $html
 * @param string $icon
 * @return string
 * 
 */ 
function ukAlertSuccess($html, $icon = '') {
	return ukAlert($html, 'success', $icon); 
}

/**
 * Render a primary alert, shortcut for ukAlert('message', 'primary');
 *
 * @param string $html
 * @param string $icon
 * @return string
 *
 */ 
function ukAlertPrimary($html, $icon = '') {
	return ukAlert($html, 'primary', $icon);
}

/**
 * Render a warning alert, shortcut for ukAlert('message', 'warning');
 *
 * @param string $html
 * @param string $icon
 * @return string
 *
 */ 
function ukAlertWarning($html, $icon = '') {
	return ukAlert($html, 'warning', $icon);
}

/**
 * Render a danger alert, shortcut for ukAlert('message', 'danger');
 *
 * @param string $html
 * @param string $icon
 * @return string
 *
 */ 
function ukAlertDanger($html, $icon = '') {
	return ukAlert($html, 'danger', $icon);
}

/**
 * Render a heading tag
 * 
 * @param string $text Heading text
 * @param int $type Heading type: 1, 2, 3, 4, 5, or 6
 * @param array|string $options Options to modify default behavior:
 *  - `primary` (bool): Use the uk-heading-primary class? (default=false)
 *  - `divider` (bool): Use the uk-heading-divider class? (default=false)
 *  - `bullet` (bool): Use the uk-heading-bullet class? (default=false)
 *  - `line` (bool|string): Use the uk-heading-line style? Specify "left" (or true), "right" or "center" (default=false). 
 *  - `icon` (string): Icon name to display before heading (default='').
 *  - `class` (string): Any additional class names you want to add. 
 * @return string
 * 
 */
function ukHeading($text, $type = 1, $options = array()) {
	$defaults = array(
		'primary' => false, 
		'divider' => false, 
		'bullet' => false, 
		'line' => false, // left, right, center 
		'icon' => '', // icon to display before heading
		'class' => '', 
	);
	$options = _ukMergeOptions($defaults, $options); 
	$classes = array();
	if($options['icon']) $text = ukIcon($options['icon']) . " $text";
	if($options['class']) $classes = explode(' ', $options['class']); 
	if($options['primary']) $classes[] = 'uk-heading-primary';
	if($options['divider']) $classes[] = 'uk-heading-divider';
	if($options['bullet']) $classes[] = 'uk-heading-bullet';
	if($options['line']) {
		$classes[] = 'uk-heading-line';
		$text = "<span>$text</span>";
		if($options['line'] == 'center') $classes[] = 'uk-text-center';
		if($options['line'] == 'right') $classes[] = 'uk-heading-right';
	}
	if(count($classes)) {
		$class = " class='" . implode(' ', $classes) . "'";
	} else {
		$class = '';
	}
	
	return "<h$type$class>$text</h$type>";
}

/**
 * Render an h1 heading
 * 
 * @param string $text Heading text
 * @param array|string $options See ukHeading() function for options
 * @return string
 * 
 */
function ukHeading1($text, $options = array()){
	return ukHeading($text, 1, $options); 
}

/**
 * Render an h2 heading
 *
 * @param string $text Heading text
 * @param array|string $options See ukHeading() function for options
 * @return string
 *
 */
function ukHeading2($text, $options = array()){
	return ukHeading($text, 2, $options);
}

/**
 * Render an h3 heading
 *
 * @param string $text Heading text
 * @param array|string $options See ukHeading() function for options
 * @return string
 *
 */
function ukHeading3($text, $options = array()){
	return ukHeading($text, 3, $options);
}

/**
 * Render an h4 heading
 *
 * @param string $text Heading text
 * @param array $options See ukHeading() function for options
 * @return string
 *
 */
function ukHeading4($text, $options = array()){
	return ukHeading($text, 4, $options);
}

/**
 * Render a Uikit icon
 * 
 * @param string $name Name of icon to render 
 * @param array|string|float $options Options to modify default behavior, or specify float for 'ratio' option. 
 *  - `href` (string): URL to make icon link to (default='').
 *  - `button` (bool): Make the icon a ui-icon-button with background color? (default=false)
 *  - `ratio` (float|int): Icon size ratio (default=1). 
 *  - `class` (string): Any additional class names to apply to the icon (default=''). 
 * @return string
 * 
 */
function ukIcon($name, $options = array()) {
	
	$defaults = array(
		'href' => '', 
		'button' => false, 
		'ratio' => 0,
		'class' => '', 
	);
	
	if(is_float($options)) {
		$defaults['ratio'] = $options;
		$options = array();
	}
	
	$options = _ukMergeOptions($defaults, $options);
	
	if($options['button']) $options['class'] .= ' uk-icon-button';
	if($options['ratio']) $name .= "; ratio: $options[ratio]";
	
	$out = $options['href'] ? "<a href='$options[href]' " : "<span ";
	$out .= "uk-icon='icon: $name'";
	$out .= $options['class'] ? " class='$options[class]'>" : ">";
	$out .= $options['href'] ? "</a>" : "</span>";
	
	return $out;
}

/**
 * Render Uikit pagination for a PageArray
 *
 * @param PageArray $items Current result set of paginated pages
 * @param array|string $options Options to modify default behavior:
 *  - `center` (bool): Show centered pagination? (default=false)
 *  - `next` (string): Next label (default="Next").
 *  - `previous` (string): Previous label (default="Previous").
 * @return string
 *
 */
function ukPagination(PageArray $items, $options = array()) {

	$page = $items->wire('page');

	if(!$page->template->allowPageNum) {
		return ukAlert('This template needs page numbers enabled to support pagination', 'danger');
	}

	if(!$items->getLimit() || $items->getTotal() <= $items->getLimit()) return '';

	$next = isset($options['next']) ? $options['next'] : __('Next');
	$previous = isset($options['previous']) ? $options['previous'] : __('Previous');

	// customize the MarkupPagerNav to output in Uikit-style pagination links
	$defaults = array(
		'center' => false,
		'nextItemLabel' => trim("$next <span uk-pagination-next></span>"),
		'nextItemClass' => '',
		'previousItemLabel' => trim("<span uk-pagination-previous></span> $previous"),
		'previousItemClass' => '',
		'lastItemClass' => '',
		'currentItemClass' => 'uk-active',
		'separatorItemLabel' => '<span>&hellip;</span>',
		'separatorItemClass' => 'uk-disabled',
		'listMarkup' => "<ul class='uk-pagination'>{out}</ul>",
		'itemMarkup' => "<li class='{class}'>{out}</li>",
		'linkMarkup' => "<a href='{url}'>{out}</a>",
		'currentLinkMarkup' => "<span><a href='{url}'>{out}</a></span>"
	);

	$options = _ukMergeOptions($defaults, $options);

	if($options['center']) {
		$options['listMarkup'] = str_replace('uk-pagination', 'uk-pagination uk-flex-center', $options['listMarkup']);
	}

	/** @var MarkupPagerNav $pager */
	$pager = $items->wire('modules')->get('MarkupPagerNav');
	$pager->setBaseUrl($page->url);

	return $pager->render($items, $options);
}

/**
 * Render a uikit description list navigation
 *
 * @param PageArray $items
 * @param array|string $options
 * @return string
 *
 */
function ukDescriptionListPages(PageArray $items, $options = array()) {

	if(!$items->count) return '';

	$defaults = array(
		'dt' => 'title',    // field to use for <dt> element
		'dd' => 'summary',  // field to use for <dd> element
		'divider' => true,  // show a divider between items?
		'link' => true,     // link the <dt> items to the target page?
		'attr' => '',       // any additional attributes to add
		'class' => '',      // any additional class names to add
	);

	$options = _ukMergeOptions($defaults, $options);
	$attr = $options['attr'];
	$class = 'uk-description-list ';
	if($options['divider']) $class .= 'uk-description-list-divider ';
	$class = rtrim("$class $options[class]");
	$attr = rtrim("class='$class' $attr");
	$out = "<dl $attr>";

	foreach($items as $item) {
		$dt = $item->get($options['dt']);
		$dd = $item->get($options['dd']);
		if($options['link']) $dt = "<a href='$item->url'>$dt</a>";
		$out .= "<dt>$dt</dt>";
		$out .= "<dd>$dd</dd>";
	}

	$out .= "</dl>";

	return $out;
}


/*****************************************************************************************
 * ProcessWire/Uikit functions for blog support
 *
 */

/**
 * Render a blog post using Uikit “article” component
 * 
 * @param Page $page Blog post
 * @param array|string $options Options to modify default behavior
 *  - `summarize` (bool): Display blog post summary rather than full post? (default=auto-detect).
 *  - `metaIcon` (string): Icon to use for blog meta info in header (default=info).
 *  - `moreIcon` (string): Icon to use for more link in summarized blog post (default=more). 
 *  - `categoryIcon` (string): Icon to use for identification of categories in blog header (default=hashtag). 
 *  - `bylineText` (string): Template for byline (default=“Posted by %1$s on %2$s”).
 * @return string
 * 
 */
function ukBlogPost(Page $page, $options = array()) {
	
	$defaults = array(
		'summarize' => null, // Display blog post summary rather than full post? (null=auto-detect)
		'metaIcon' => 'info',
		'moreIcon' => 'arrow-right',
		'moreText' => __('Read more'), 
		'categoryIcon' => 'hashtag',
		'bylineText' => __('Posted by %1$s on %2$s'), 
	);

	$options = _ukMergeOptions($defaults, $options);
	$title = $page->title;
	$date = $page->get('date|createdStr');
	$name = $page->createdUser->name; 
	$body = $page->get('body');
	$metaIcon = ukIcon($options['metaIcon']);
	$moreIcon = ukIcon($options['moreIcon']);
	$categoryIcon = ukIcon($options['categoryIcon']);
	$n = $page->get('comments')->count();
	$numComments = $n ? "<a href='$page->url#comments'>" . ukIcon('comments') . " $n</a>" : "";
	
	if($options['summarize'] === null) {
		// auto-detect: summarize if current page is not the same as the blog post
		$options['summarize'] = page()->id != $page->id;
	}
	
	$categories = $page->get('categories')->each($categoryIcon . 
		"<a class='uk-button uk-button-text' href='{url}'>{title}</a> "
	);

	if($options['summarize']) {
		// link to post in title, and use just the first paragraph in teaser mode
		$title = "<a href='$page->url'>$title</a>";
		$body = explode('</p>', $body); 
		$body = reset($body) . ' ';
		$body .= "<a href='$page->url'>$options[moreText] $moreIcon</a></p>";
		$class = 'blog-post-summary';
	} else {
		$class = 'blog-post-full';
	}

	if($options['summarize']) {
		$heading = "<h2 class='uk-margin-remove'>$title</h2>";
	} else {
		$heading = "<h1 class='uk-article-title uk-margin-remove'>$title</h1>";
	}
	
	$byline = sprintf($options['bylineText'], $name, $date); 
	
	// return the blog post article markup
	return "
		<article class='uk-article blog-post $class'>
			$heading
			<p class='uk-margin-small'>
				<span class='uk-article-meta'>
					$metaIcon
					$byline
				</span>
				<span class='categories'>
					$categories
				</span>
				<span class='num-comments uk-margin-small-left uk-text-muted'>
					$numComments
				</span>
			</p>
			$body
		</article>
		<hr>
	";	
}

/**
 * Render multiple blog posts summarized
 * 
 * @param PageArray $posts
 * @param array|string $options See the ukBlogPost() method for options, plus: 
 *  - `paginate` (bool): Use pagination when applicable? (default=true)
 * @return string
 * 
 */
function ukBlogPosts(PageArray $posts, $options = array()) {
	if(!$posts->count) {
		if(input()->pageNum > 1) {
			// redirect to first pagination if accessed at an out-of-bounds pagination
			session()->redirect(page()->url);
		}
		return '';
	}
	$defaults = array(
		'paginate' => false
	);
	$options = _ukMergeOptions($defaults, $options);
	$out = "<div class='blog-posts'>";
	foreach($posts as $post) {
		$out .= ukBlogPost($post, $options); 
	}
	if($options['paginate'] && $posts->getTotal() > $posts->count()) {
		$out .= ukPagination($posts);
	}
	$out .= "</div>";
	return $out; 
}

/*****************************************************************************************
 * ProcessWire/Uikit functions for rendering comments and comment forms
 *
 * Note: comment threads (depth), stars and votes are not yet supported in here.
 *
 */

/**
 * Render a ProcessWire comment using Uikit markup
 * 
 * @param Comment $comment
 * @return string
 *
 */
function ukComment(Comment $comment) {

	$text = $comment->getFormatted('text');
	$cite = $comment->getFormatted('cite');
	$website = $comment->getFormatted('website');
	$field = $comment->getField();
	$page = $comment->getPage();
	$classes = array();
	$metas = array();
	$gravatar = '';
	$replies = '';

	if($field->get('useGravatar')) {
		$img = $comment->gravatar($field->get('useGravatar'), $field->get('useGravatarImageset'));
		if($img) $gravatar = "<div class='uk-width-auto'><img class='uk-comment-avatar' src='$img' alt='$cite'></div>";
	}

	if($website) $cite = "<a href='$website' rel='nofollow' target='_blank'>$cite</a>";
	$created = wireDate('relative', $comment->created);

	if($field->get('usePermalink')) {
		$permalink = $page->httpUrl;
		$urlSegmentStr = $this->wire('input')->urlSegmentStr;
		if($urlSegmentStr) $permalink .= rtrim($permalink, '/') . $urlSegmentStr . '/';
		$permalink .= '#Comment' . $comment->id;
		$permalink = "<a href='$permalink'>" . __('Permalink') . "</a>";
		$metas[] = "<li>$permalink</li>";
	}

	$classes = implode(' ', $classes);
	$metas = implode('', $metas);

	$out = "
		<article id='Comment$comment->id' class='$classes uk-comment uk-comment-primary' data-comment='$comment->id'>
			<header class='uk-comment-header uk-grid-medium uk-flex-middle' uk-grid>
				$gravatar				
				<div class='uk-width-expand'>
					<h4 class='uk-comment-title uk-margin-remove'>$cite</h4>
					<ul class='uk-comment-meta uk-subnav uk-subnav-divider uk-margin-remove-top'>
						<li>$created</li>
						$metas
					</ul>
				</div>
			</header>
			<div class='uk-comment-body'>
				$text
			</div>
		</article>
		$replies
	";

	return $out;
}

/**
 * Render a list of ProcessWire comments using Uikit markup
 * 
 * Note: does not currently support threaded comments (comment depth). 
 * Use ProcessWire’s built-in comments rendering for that purpose. 
 *
 * @param CommentArray $comments
 * @param array|string $options Options to modify default behavior
 *  - `id` (string): HTML id attribute of the comments list (default='comments').
 * @return string
 *
 */
function ukComments(CommentArray $comments, $options = array()) {

	$defaults = array(
		'id' => 'comments',
	);

	if(!count($comments)) return '';
	$options = _ukMergeOptions($defaults, $options);

	$out = "<ul id='$options[id]' class='uk-comment-list'>";
	
	foreach($comments as $comment) {
		$out .= "<li class='uk-margin'>" . ukComment($comment) . "</li>";
	}
	
	$out .= "</ul>";

	return $out;
}

/**
 * Render a comment posting form
 *
 * @param CommentArray $comments
 * @param array $options See `CommentForm` class for all options.
 * @return string
 *
 */
function ukCommentForm(CommentArray $comments, array $options = array()) {

	$defaults = array(
		'headline' => "",
		'successMessage' =>
			__('Thank you, your comment has been posted.'),
		'pendingMessage' =>
			__('Your comment has been submitted and will appear once approved by the moderator.'),
		'errorMessage' =>
			__('Your comment was not saved due to one or more errors.') . ' ' .
			__('Please check that you have completed all fields before submitting again.'),
	);

	$options = _ukMergeOptions($defaults, $options);
	$options['successMessage'] = ukAlertSuccess($options['successMessage'], 'check');
	$options['pendingMessage'] = ukAlertSuccess($options['pendingMessage'], 'check');
	$options['errorMessage'] = ukAlertDanger($options['errorMessage'], 'warning');

	if(!isset($options['attrs']) || !isset($options['attrs']['class'])) {
		$options['attrs'] = array('class' => 'uk-comment uk-comment-primary');
	}

	$adjustments = array(
		"<input type='text'" => "<input type='text' class='uk-input'",
		"<p class='CommentForm" => "<p class='uk-margin-remove-top CommentForm",
		"<textarea " => "<textarea class='uk-textarea' ",
		"<button " => "<button class='uk-button uk-button-primary' ",
		"<label " => "<label class='uk-form-label' ",
	);

	$out = $comments->renderForm($options);
	$out = str_replace(array_keys($adjustments), array_values($adjustments), $out);

	return $out;
}

/*****************************************************************************************
 * ProcessWire/Uikit internal support functions 
 *
 */

/**
 * Prepare and merge an $options argument for one of the Uikit markup functions
 *
 * - This converts PW selector strings or Uikit data attribute strings to associative arrays.
 * - This converts non-associative attributes to associative boolean attributes.
 * - This merges $defaults with $options.
 *
 * @param array $defaults
 * @param array|string $options
 * @return array
 * @internal
 *
 */
function _ukMergeOptions(array $defaults, $options) {

	// allow for ProcessWire selector style strings
	// allow for Uikit data attribute strings
	if(is_string($options)) {
		$options = str_replace(';', ',', $options);
		$o = explode(',', $options);
		$options = array();
		foreach($o as $value) {
			if(strpos($value, '=')) {
				// key=value
				list($key, $value) = explode('=', $value, 2);
			} else if(strpos($value, ':')) {
				// key: value 
				list($key, $value) = explode(':', $value, 2);
			} else {
				// boolean
				$key = $value;
				$value = true;
			}
			$key = trim($key);
			if(is_string($value)) {
				$value = trim($value);
				// convert boolean strings to real booleans
				$v = strtolower($value);
				if($v === 'false') $value = false;
				if($v === 'true') $value = true;
			}
			$options[$key] = $value;
		}
	} 

	if(!is_array($options)) {
		$options = array();
	}

	foreach($options as $key => $value) {
		if(is_int($key) && is_string($value)) {
			// non-associative options convert to boolean attribute
			$defaults[$value] = true;
		}
	}

	return array_merge($defaults, $options);
}

