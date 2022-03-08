<?php namespace ProcessWire;

if(!defined("PROCESSWIRE")) die(); 
	
/** @var AdminThemeUikit $adminTheme */
/** @var Paths $urls */
/** @var Config $config */
/** @var WireInput $input */
/** @var Sanitizer $sanitizer */
/** @var Page $page */
/** @var string $layout */

// whether or not page tree should be used for left sidebar 
$treePaneLeft = $adminTheme->layout == 'sidenav-tree';
$treePane = strpos($adminTheme->layout, 'sidenav-tree') === 0;

// define location of panes	
$treePaneLocation = $treePaneLeft ? 'west' : 'east';
$sidePaneLocation = $treePaneLeft ? 'east' : 'west';

// URL for main pane 
$mainURL = $input->url(true);
if(strpos($mainURL, 'layout=')) {
	$mainURL = preg_replace('/([&?]layout)=([-_a-zA-Z0-9]+)/', '$1=sidenav-main', $mainURL);
} else {
	$mainURL .= (strpos($mainURL, '?') ? '&' : '?') . 'layout=sidenav-main';
}
$mainURL = $sanitizer->entities($mainURL);
$themeURL = $adminTheme->url();

// pane definition iframes
$panes = array(
	'main' => "<iframe id='pw-admin-main' name='main' class='pane ui-layout-center' " . 
		"src='$mainURL?layout=sidenav-main'></iframe>",
	'side' => "<iframe id='pw-admin-side' name='side' class='pane ui-layout-$sidePaneLocation' " . 
		"src='{$urls->admin}login/?layout=sidenav-side'></iframe>", 
	'tree' => "<iframe id='pw-admin-tree' name='tree' class='pane ui-layout-$treePaneLocation' " . 
		"src='{$urls->admin}page/?layout=sidenav-tree'></iframe>",
);
	
	
?><!DOCTYPE html> 
<html class="pw" lang="<?php echo $adminTheme->_('en');
	/* this intentionally on a separate line */ ?>">
<head>
	<title></title><?php /* this title is populated dynamically by JS */ ?>
	
	<meta http-equiv="content-type" content="text/html; charset=utf-8" />
	<meta name="robots" content="noindex, nofollow" />
	<meta name="google" content="notranslate" />
	<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	
	<link rel="stylesheet" href="<?php echo $themeURL; ?>layout/source/stable/layout-default.css">
	
	<?php $adminTheme->includeFile('_head.php', array('layout' => $layout)); ?>
	
	<style type='text/css'>
		html, body {
			width: 100%;
			height: 100%;
			padding: 0;
			margin: 0;
			overflow: auto; /* when page gets too small */
		}
		.pane {
			display: none; /* will appear when layout inits */
		}
		iframe {
			margin: 0;
			padding: 0;
		}
		.ui-layout-pane {
			padding: 0;
		}
		#pw-admin-head {
			overflow: visible;
		}
	</style>
	
	<script src="<?php echo $themeURL; ?>layout/source/stable/jquery.layout.js"></script>
	<script src="<?php echo $themeURL; ?>layout/source/stable/plugins/jquery.layout.state.js"></script>
</head>
<body class='pw-layout-sidenav-init'>	

	<?php
	if($treePane) {
		echo "<div id='pw-admin-head'>";
		$adminTheme->includeFile('_masthead.php'); 
		echo "</div>";
	}
	?>

	<div id='pw-layout-container' style='height: calc(100vh - 80px);'>
		<?php
		echo $panes['main'];
		echo $treePane ? $panes['tree'] : $panes['side'] . $panes['tree'];
		if($adminTheme->isLoggedIn) {
			$adminTheme->includeFile('_offcanvas.php'); 
		}
		?>
	</div>	
    
	<script>
		var isPresent = true; // required
		var mobileWidth = 959;
		
		function pwInitLayout() {
			var windowWidth = $(window).width();
			var sidePaneWidth = windowWidth / 4;
			var sidePaneMinWidth = 200;
			var treePaneWidth = windowWidth / 3;
			var treePaneMinWidth = 300;

			if(sidePaneWidth < sidePaneMinWidth) sidePaneWidth = sidePaneMinWidth;
			if(treePaneWidth < treePaneMinWidth) treePaneWidth = treePaneMinWidth;

			var layoutOptions = {
				resizable: true,
				slidable: true,
				closable: true,
				maskContents: true,
				applyDefaultStyles: false,
				fxName: 'none',
				stateManagement: {
					enabled: true,
					stateKeys: "west.size,east.size,west.isClosed,east.isClosed",
					autoLoad: true,
					autoSave: true
				},
				<?php
				if($treePane) {
					echo "$treePaneLocation: { size: treePaneWidth },";
				} else {
					echo "$sidePaneLocation: { size: sidePaneWidth, initClosed: false },";
					echo "$treePaneLocation: { size: treePaneWidth, initClosed: true }";
				}
				?>
			};

			// determine if panes should be open or closed by default (depending on screen width)
			if(windowWidth < mobileWidth) {
				<?php
				if($treePane) {
					echo "layoutOptions.$treePaneLocation.initClosed = true;";
				} else {
					echo "layoutOptions.$sidePaneLocation.initClosed = true;";
					echo "layoutOptions.$treePaneLocation.initClosed = true;";
				}
				?>
			}

			// initialize layout
			var layout = $('#pw-layout-container').layout(layoutOptions);

			// populate title and url from main pane to this window 
			$('#pw-admin-main').on('load', function() {
				
				var main = $('#pw-admin-main')[0].contentWindow;
				var title = main.document.title;
				var href = main.location.href;
				
				if(href.search(/^http[s]?:\/\/<?php echo preg_quote($config->httpHost); ?>/i) !== 0) {
					console.log('Invalid main frame http host: ' + href);
					return;
				}
				
				if(href.indexOf('layout=')) {
					href = href.replace(/([?&]layout)=[-_a-z0-9]+/g, ''); 
				}
				
				window.history.pushState('obj', 'newtitle', href);
				$('title').text(title);
			});

			// window resize event to detect when sidebar(s) should be hidden for mobile
			var lastWidth = 0;
			$(window).resize(function() {
				var width = $(window).width();
				if(width <= mobileWidth && (!lastWidth || lastWidth > mobileWidth)) {
					<?php echo "if(!layout.state.$sidePaneLocation.isClosed) layout.close('$sidePaneLocation');"; ?>
					<?php echo "if(!layout.state.$treePaneLocation.isClosed) layout.close('$treePaneLocation');"; ?>
				} else if(lastWidth <= mobileWidth && width > mobileWidth) {
					<?php echo "if(layout.state.$sidePaneLocation.isClosed) layout.open('$sidePaneLocation');"; ?>
				}
				lastWidth = width;
			});

			// make any links in this file direct to the main pane
			$(document).on('mouseover', 'a', ProcessWireAdminTheme.linkTargetMainMouseoverEvent);

			// update the uk-active state of top navigation, since this pane doesn't reload
			$(document).on('mousedown', 'a', function() {
				var $a = $(this);
				$('li.uk-active').removeClass('uk-active');
				$a.parents('li').each(function() {
					$(this).addClass('uk-active');
					var $a = $(this).children('a');
					var from = $a.attr('data-from');
					if(from) $('#' + from).parent('li').addClass('uk-active');
				});
			});
	
			// collapse offcanvas nav when link within it clicked, if it changes the main pane URL
			$('#offcanvas-nav').on('click', 'a', function() {
				var t, w1 = $('#pw-admin-main')[0].contentWindow.document.location.href;
				if(!t) t = setTimeout(function() {
					var w2 = $('#pw-admin-main')[0].contentWindow.document.location.href;
					if(w1 != w2) $('#offcanvas-toggle').click(); // close
					t = false;
				}, 1000); 
			}); 
			
			return layout;
		}
		
		var layout;
		
		$(document).ready(function() {
			layout = pwInitLayout();
		});
		
		/**
		 * Are we currently at mobile width?
		 *
		 */
		function isMobileWidth() {
			var width = $(window).width();
			return width <= mobileWidth;
		}
		
		/**
		 * Toggle navigation sidebar pane open/closed
		 * 
		 */
		function toggleSidebarPane() {
			layout.toggle('<?php echo $sidePaneLocation; ?>');
		}
		
		/**
		 * Toggle tree sidebar pane open/closed
		 *
		 */
		function toggleTreePane() {
			layout.toggle('<?php echo $treePaneLocation; ?>');
		}

		/**
		 * Close the tree pane
		 * 
		 */
		function closeTreePane() {
			if(!layout.state.<?php echo $treePaneLocation; ?>.isClosed) {
				layout.close('<?php echo $treePaneLocation; ?>'); 	
			}
		}
		
		/**
		 * Hide the tree pane
		 *
		 */
		function hideTreePane() {
			if(!layout.state.<?php echo $treePaneLocation; ?>.isHidden) {
				layout.hide('<?php echo $treePaneLocation; ?>');
			}
		}
		
		/**
		 * Open the tree pane
		 *
		 */
		function openTreePane() {
			if(layout.state.<?php echo $treePaneLocation; ?>.isClosed) {
				layout.open('<?php echo $treePaneLocation; ?>');
			}
		}
		
		/**
		 * Show the tree pane (if hidden)
		 *
		 */
		function showTreePane() {
			if(layout.state.<?php echo $treePaneLocation; ?>.isHidden) {
				layout.show('<?php echo $treePaneLocation; ?>');
			}
		}
		
		/**
		 * Is the tree pane currently closed? 
		 * 
		 */
		function treePaneClosed() {
			<?php echo "return layout.state.$treePaneLocation.isClosed;"; ?>
		}
		
		/**
		 * Is the tree pane currently hidden?
		 *
		 */
		function treePaneHidden() {
			<?php echo "return layout.state.$treePaneLocation.isHidden;"; ?>
		}
		
		/**
		 * Is the sidebar pane currently closed?
		 *
		 */
		function sidebarPaneClosed() {
			<?php echo "return layout.state.$sidePaneLocation.isClosed;"; ?>
		}

		/**
		 * Reload/refresh the tree pane, optionally for children of specific page ID
		 * 
		 * If page ID provided, it refreshes just children of that page ID. 
		 * If no argument provided, then it refreshes the entire pane. 
		 *
		 */
		function refreshTreePane(pageID) {
			var pane = $('#pw-admin-tree')[0].contentWindow;
			if(typeof pageID == "undefined") {
				pane.location.reload(true);
			} else if(typeof pane.pageListRefresh != "undefined") {
				pane.pageListRefresh.refreshPage(pageID);
			}
		}

	</script>

</body>
</html>