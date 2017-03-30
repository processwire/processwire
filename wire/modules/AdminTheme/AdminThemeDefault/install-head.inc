<?php namespace ProcessWire; 
if(!defined("PROCESSWIRE_INSTALL")) die(); 
if(!isset($title)) $title = 'Title';
if(!isset($formAction)) $formAction = './install.php';
?><!DOCTYPE html>
<html class="pw" lang="en"> 
<head>
	<meta http-equiv="content-type" content="text/html; charset=utf-8" />
	<meta name="robots" content="noindex, nofollow" />
	<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">

	<title><?php echo htmlentities($title, ENT_QUOTES, "UTF-8"); ?></title>

	<link id='colors' type='text/css' href='wire/modules/AdminTheme/AdminThemeDefault/styles/main-classic.css' rel='stylesheet' />
	<link type='text/css' href='wire/modules/AdminTheme/AdminThemeDefault/styles/install.css' rel='stylesheet' />
	<link type='text/css' href='wire/templates-admin/styles/font-awesome/css/font-awesome.min.css' rel='stylesheet' />
	<script type='text/javascript' src='wire/modules/Jquery/JqueryCore/JqueryCore.js'></script>
	<script type='text/javascript' src='wire/modules/Jquery/JqueryUI/JqueryUI.js'></script>
	<script type='text/javascript' src='wire/templates-admin/scripts/main.js'></script>
	<script type='text/javascript' src='wire/modules/AdminTheme/AdminThemeDefault/scripts/main.js'></script>

</head>
<body>

	<div id="masthead" class="pw-masthead ui-helper-clearfix">
		<div class="pw-container">
			<a id='logo' target='_blank' href='https://processwire.com'><img width='144' src="wire/modules/AdminTheme/AdminThemeDefault/styles/images/logo.png" alt="ProcessWire" /></a>
		</div>
	</div><!--/#masthead-->

	<div id='breadcrumbs'>
		<div class='pw-container'>
			<ul class='nav'>
				<li class='title'><?php echo htmlentities($title, ENT_QUOTES, "UTF-8"); ?></li>
				<li class='support'><a target='_blank' href='https://processwire.com/talk/'><i class='fa fa-comments'></i> Need help?</a></li>
			</ul>
		</div>
	</div><!--/#breadcrumbs-->

	<div id="content" class="pw-content">
		<div class="pw-container">
			<?php if($formAction): ?>
			<form action='<?php echo htmlentities($formAction, ENT_QUOTES, "UTF-8"); ?>' method='post'>
			<?php endif; ?>

