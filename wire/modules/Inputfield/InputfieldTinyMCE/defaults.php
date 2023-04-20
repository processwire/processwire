<?php namespace ProcessWire;

// This file is NOT used, it is for development reference only

die();

/** @var InputfieldTinyMCE $inputfield */
/** @var ProcessWire $wire */
$url = $wire->config->urls('InputfieldTinyMCE');
$alignElements = 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img,figure,audio,video';
$alignClasses = $inputfield->settings()->getAlignClasses();

$defaults = array(
	'skin' => 'oxide',
	'content_css' => 'wire', // $this->getContentCssUrl(), 
	'relative_urls' => false,
	'height' => 500,
	'readonly' => false,
	'remove_script_host' => true,
	'inline' => false,
	// 'plugins' => 'advlist anchor autolink charmap code fullscreen link lists preview pwimage pwlink table visualblocks visualchars wordcount',
	// 'toolbar' => 'styles bold italic pwlink pwimage blockquote hr bullist numlist table anchor charmap fullscreen code',
	'plugins' => 'anchor code link lists pwimage pwlink table',
	'toolbar' => 'styles bold italic pwlink pwimage blockquote hr bullist numlist table anchor code',
	'toolbar_location' => 'auto',
	'toolbar_sticky' => false,
	'menubar' => true,
	'statusbar' => true,
	'menu' => array(
		// All available menu items: https://www.tiny.cloud/docs/tinymce/6/available-menu-items/
		//'file' => array(
		//	'title' => 'File',
		//	'items' => 'newdocument restoredraft | preview | export print | deleteallconversations'
		//),
		'edit' => array(
			'title' => 'Edit',
			'items' => 'undo redo | cut copy paste pastetext | selectall | searchreplace'
		),
		'view' => array(
			'title' => 'View',
			'items' => 'code | visualaid visualchars visualblocks | spellchecker | preview print fullscreen | showcomments'
		),
		'insert' => array(
			'title' => 'Insert',
			'items' => 'pwimage pwlink media addcomment pageembed template codesample inserttable | charmap emoticons hr | pagebreak nonbreaking anchor tableofcontents | insertdatetime'
		),
		'format' => array(
			'title' => 'Format',
			'items' => 'bold italic underline strikethrough superscript subscript codeformat | styles blocks fontfamily fontsize align lineheight | forecolor backcolor | language | removeformat'
		),
		'tools' => array(
			'title' => 'Tools',
			'items' => 'spellchecker spellcheckerlanguage | a11ycheck code wordcount'
		),
		'table' => array(
			'title' => 'Table',
			'items' => 'inserttable | cell row column | advtablesort | tableprops deletetable'
		),
		'help' => array(
			'title' => 'Help',
			'items' => 'help'
		)
	),
	'removed_menuitems' => 'newdocument fontfamily fontsize lineheight forecolor backcolor',
	'contextmenu' => 'pwlink unlink pwimage table removeformat codesample',
	'promotion' =>  false, // hides "upgrade" button
	'directionality' => 'ltr',
	'browser_spellcheck' => false,
	'external_plugins' => array(
		'pwimage' => $url . 'plugins/pwimage.js',
		'pwlink' => $url . 'plugins/pwlink.js',
	),
	'paste_tab_spaces' => 2,
	'invalid_styles' => array(
		//'*' => 'color font-size line-height', // Global invalid styles
		'a' => 'background' // Link specific invalid styles
	),
	'invalid_elements' => 'div',
	'formats' => array(
		'alignleft' =>  array(
			'selector' => $alignElements,
			'classes' => $alignClasses['left'],
		),
		'aligncenter' => array(
			'selector' => $alignElements,
			'classes' => $alignClasses['center'],
		),
		'alignright' => array(
			'selector' => $alignElements,
			'classes' => $alignClasses['right']
		),
		'alignfull' => array(
			'selector' => $alignElements,
			'classes' => 'align_full', // not currently used
		),
		'bold' => array(
			'inline' => 'strong',
		),
		'italic' => array(
			'inline' => 'em',
		),
		'underline' => array(
			'inline' => 'u',
			//'exact' => true
		),
		'strikethrough' => array(
			'inline' => 's',
		),
		/*
		'customformat' => array(
			'inline' => 'span',
			'styles' => array(
				'color' => '#00ff00',
				'fontSize' => '20px'
			),
			'attributes' => array(
				'title' => 'My custom format'
			),
			'classes' => 'example1',
		)
		*/
	),
	'block_formats' => 'Paragraph=p; Heading 1=h1; Heading 2=h2; Heading 3=h3; Heading 4=h4; Heading 5=h5; Heading 6=h6;',
	'style_formats_merge' => false, // merge to existing rather than overwrite

	// style_formats based on TinyMCE default style formats (excluding div and alignjustify)
	'style_formats' => array(
		array(
			'title' => 'Headings',
			'items' => array(
				array('title' => 'Heading 1', 'format' => 'h1'),
				array('title' => 'Heading 2', 'format' => 'h2'),
				array('title' => 'Heading 3', 'format' => 'h3'),
				array('title' => 'Heading 4', 'format' => 'h4'),
				array('title' => 'Heading 5', 'format' => 'h5'),
				array('title' => 'Heading 6', 'format' => 'h6')
			)
		),
		array(
			'title' => 'Inline',
			'items' => array(
				array('title' => 'Bold', 'format' => 'bold'),
				array('title' => 'Italic', 'format' => 'italic'),
				array('title' => 'Underline', 'format' => 'underline'),
				array('title' => 'Strikethrough', 'format' => 'strikethrough'),
				array('title' => 'Superscript', 'format' => 'superscript'),
				array('title' => 'Subscript', 'format' => 'subscript'),
				array('title' => 'Code', 'format' => 'code')
			)
		),
		array(
			'title' => 'Blocks',
			'items' => array(
				array('title' => 'Paragraph', 'format' => 'p'),
				array('title' => 'Blockquote', 'format' => 'blockquote'),
				// array('title' => 'Div', 'format' => 'div'),
				array('title' => 'Pre', 'format' => 'pre')
			)
		),
		array(
			'title' => 'Align',
			'items' => array(
				array('title' => 'Left', 'format' => 'alignleft'),
				array('title' => 'Center', 'format' => 'aligncenter'),
				array('title' => 'Right', 'format' => 'alignright'),
				// array('title' => 'Justify', 'format' => 'alignjustify')
			)
		)
	),
	/*
	'style_formats' => array(
		array(
			'title' => 'Red text',
			'inline' => 'span',
			'styles' => array('color' => '#ff0000')
		),
		array(
			'name' => 'my-inline',
			'title' => 'My inline',
			'inline'  => 'span',
			'classes' =>  array('my-inline')
		)
	),
	*/
	// 'paste_data_images' => false, // prevent pasted data containing images from keeping them as data:lkjalkjef
	// 'paste_block_drop' => true, // prevent drag/drop of text into editor (which is not filterable), but blocks image uploads
	// 'valid_children' => '+body[style],-body[div],p[strong|a|#text]',
	// 'valid_classes' => array(
	//	  '*' => 'class1 class2 class3', // Global classes
	//	  'a' => 'class4 class5' // Link specific classes
	// ),
	// 'valid_elements' => 'a[href|target=_blank],strong/b,div[align],br',
);
		
