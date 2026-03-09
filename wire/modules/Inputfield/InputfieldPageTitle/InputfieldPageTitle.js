/**
 * Convert a title/headline to an ASCII URL name
 * 
 * 1. Convert accented characters to the ASCII equivalent. 
 * 2. Convert non -_a-z0-9. to blank. 
 * 3. Replace multiple dashes with single dash. 
 *
 */

function InputfieldPageTitle($nameField) {
	var $titleField = $(".InputfieldPageTitle:not(.InputfieldPageTitleCustom) input[type=text]");

	$(".InputfieldPageName .LanguageSupport input[type=text]").each(function() {
		// if language support enabled and any of the page names contains something
		// then prevent title from populating name fields
		if($(this).val().length > 0) $(this).addClass('InputfieldPageNameNoUpdate');
	});

	if($("#ProcessPageAdd").length > 0) {

		var titleKeyup = function() {
			// var val = $(this).val().substring(0, 128); 
			var val = $(this).val(); // @adrian
			var id = $(this).attr('id').replace(/Inputfield_title_*/, 'Inputfield__pw_page_name');
			$nameField = $("#" + id);
			if($nameField.hasClass('InputfieldPageNameNoUpdate')) return;
			if($nameField.length) $nameField.val(val).trigger('blur');
		}

		$titleField.on('keyup change', titleKeyup);

		$('.InputfieldPageName input').on('change', function() {
			// if they happen to change the name field on their own, then disable 
			if($(this).val() != $(this).attr('data-prev')) $(this).addClass('InputfieldPageNameNoUpdate');
		}).each(function() {
			$(this).attr('data-prev', $(this).val());
		});
	}
}

function InputfieldPageTitleCustom($titleField) {

	var $nameInput = jQuery('input[name="' + $titleField.attr('data-name-field') + '"]');
	if(!$nameInput.length || $nameInput.val().length) return;
	
	var delimiter = $titleField.attr('data-name-delimiter');
	var $titleInput = $titleField.find('input').eq(0); 
	var replacements = ProcessWire.config.InputfieldPageTitle.replacements;
	
	function titleToName(title, strict) {
		var name = '';
		var lastc = '';
		var r = '';
		var c = '';
		if(typeof strict === "undefined") strict = false;
		for(var n = 0; n < title.length; n++) {
			c = title.substring(n, n+1);
			if(c.match(/^[a-zA-Z0-9]$/g)) {
				if(delimiter.length && strict) c = c.toLowerCase();
			} else if(c === delimiter) {
				c = delimiter;
			} else if(typeof replacements[c] !== "undefined") {
				c = replacements[c]; 
			} else if(delimiter.length && name.length) {
				c = delimiter;
			} else {
				c = '';
			}
			if((c === '_' || c === '-') && c !== delimiter) c = delimiter;
			if(strict && (c === delimiter && lastc === delimiter)) continue;
			lastc = c;
			name += c;
		}
		if(strict && name.length && name.substring(-1) === delimiter) {
			name = name.substring(0, name.length - 1);
		}
		return name;
	}
	
	$titleInput.on('keyup change', function() {
		if($nameInput.hasClass('InputfieldPageTitleDone')) return;
		var title = $(this).val();
		var name = titleToName(title, true);
		$nameInput.val(name).trigger('blur');
	});
	
	$nameInput.attr('data-prev', $nameInput.val());
	$nameInput.on('change', function() {
			var val = jQuery(this).val();
			if(val.length) val = titleToName(val, false);
			if(val.length && val != jQuery(this).attr('data-prev')) {
				// jQuery(this).val(val);	
				jQuery(this).addClass('InputfieldPageTitleDone');
			}
	});
	$nameInput.on('keyup', function() {
		var val = jQuery(this).val();
		if(val.length) val = titleToName(val, false);
		jQuery(this).val(val);	
	}); 
}

jQuery(document).ready(function() {
	var $nameField = jQuery("#Inputfield__pw_page_name"); 
	// check if namefield exists, because pages like homepage don't have one and
	// no need to continue if it already has a value	
	if($nameField.length && !$nameField.val().length) {
		InputfieldPageTitle($nameField);
	} else {
		jQuery('.InputfieldPageTitleCustom').each(function() {
			InputfieldPageTitleCustom(jQuery(this));
		});
	}
}); 
