<?php namespace ProcessWire; 
if(!defined("PROCESSWIRE_INSTALL")) die(); 
if(!isset($title)) $title = 'Title';
if(!isset($formAction)) $formAction = './install.php';
$title = htmlentities($title, ENT_QUOTES, "UTF-8");
$formAction = htmlentities($formAction, ENT_QUOTES, "UTF-8"); 
?>
<!DOCTYPE html>
<html class="pw" lang="en">
<head>	
	<meta http-equiv="content-type" content="text/html; charset=utf-8" />
	<meta name="robots" content="noindex, nofollow" />
	<meta name="google" content="notranslate" />
	<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">

	<title><?php echo $title; ?></title>

	<script>
		var ProcessWire = {config: {}}; 
		var config = ProcessWire.config;
	</script>

	<link type='text/css' href='wire/modules/AdminTheme/AdminThemeUikit/uikit/dist/css/uikit.pw.min.css' rel='stylesheet' />
	<link type='text/css' href='wire/templates-admin/styles/AdminTheme.css' rel='stylesheet' />
	<link type='text/css' href='wire/templates-admin/styles/font-awesome/css/font-awesome.min.css' rel='stylesheet' />
	
	<style type='text/css'>
		input, select, textarea {
			font-size: 16px;
		}
		#main, 
		#pw-mastheads .uk-container,
		#pw-footer .uk-container {
			max-width: 900px;
		}
	</style>
	
	<script type='text/javascript' src='wire/modules/Jquery/JqueryCore/JqueryCore.js'></script>
	<script type='text/javascript' src='wire/modules/Jquery/JqueryUI/JqueryUI.js'></script>
	<script type='text/javascript' src='wire/templates-admin/scripts/inputfields.js'></script>
	<script type='text/javascript' src='wire/templates-admin/scripts/main.js'></script>
	<script type='text/javascript' src='wire/modules/AdminTheme/AdminThemeUikit/uikit/dist/js/uikit.min.js'></script>
	<script type='text/javascript' src='wire/modules/AdminTheme/AdminThemeUikit/uikit/dist/js/uikit-icons.min.js'></script>
	<script type='text/javascript' src='wire/modules/AdminTheme/AdminThemeUikit/scripts/main.js'></script>	
</head>
<body class='template-admin pw-init'>

	<div id='pw-mastheads'>
		<header id='pw-masthead-mobile' class='pw-masthead uk-background-muted'>
			<div class='pw-container uk-container uk-container-center'>
				<a href='https://processwire.com' target='_blank' class='pw-logo-link'>
					<img class='pw-logo pw-logo-native' src='wire/modules/AdminTheme/AdminThemeUikit/uikit/custom/images/pw-mark.png' alt='' />
				</a>
			</div>	
		</header>	
		<ul class='pw-notices' id='notices'></ul>
	</div>	

	<!-- MAIN CONTENT -->
	<main id='main' class='pw-container uk-container uk-container-center uk-margin'>
		<div class='pw-content' id='content'>		
			<header id='pw-content-head'>
				<h1 class='uk-margin-remove-top'><?php echo $title; ?></h1>				
			</header>				
			<div id='pw-content-body'>
				<?php if($formAction) echo "<form class='uk-form InputfieldForm' action='$formAction' method='post'>"; ?>
