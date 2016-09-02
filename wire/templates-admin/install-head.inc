<?php namespace ProcessWire; 
if(!defined("PROCESSWIRE_INSTALL")) die(); 
if(!isset($title)) $title = 'Title';
if(!isset($formAction)) $formAction = './install.php';
if(!isset($bgTitle)) $bgTitle = "Installer";
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<title><?php echo htmlentities($title, ENT_QUOTES, "UTF-8"); ?></title>

	<meta name="robots" content="noindex, nofollow" />

	<link rel="stylesheet" type="text/css" href="wire/templates-admin/styles/main.css" />
	<link rel="stylesheet" type="text/css" href="wire/templates-admin/styles/ui.css" />
	<link rel="stylesheet" type="text/css" href="wire/templates-admin/styles/inputfields.css" />
	<link rel="stylesheet" type="text/css" href="wire/templates-admin/styles/install.css" />

	<script type="text/javascript" src="wire/modules/Jquery/JqueryCore/JqueryCore.js"></script>
	<script type="text/javascript" src="wire/templates-admin/scripts/install.js"></script>
</head>
<body>
<p id='bgtitle'><?php echo htmlentities($bgTitle, ENT_QUOTES, "UTF-8"); ?></p>
<div id="masthead" class="masthead">
	<div class="container">
		<a target='_blank' id='logo' href='http://processwire.com'><img src="wire/templates-admin/styles/images/logo.gif" alt="ProcessWire" /></a>
		<h1 id='title'><?php echo htmlentities($title, ENT_QUOTES, "UTF-8"); ?></h1>
	</div>
</div>

<div id="content" class="content">
	<div class="container">
		<form action='<?php echo htmlentities($formAction, ENT_QUOTES, "UTF-8"); ?>' method='post'>

