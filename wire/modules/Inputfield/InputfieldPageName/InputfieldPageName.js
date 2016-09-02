
var InputfieldPageName = {
	sanitize: function(name) {

		// replace leading and trailing whitespace 
		name = jQuery.trim(name);
		name = name.toLowerCase();  

		var srch;
		for(srch in ProcessWire.config.InputfieldPageName.replacements) {
			var repl = ProcessWire.config.InputfieldPageName.replacements[srch];
			if(name.indexOf(srch) > -1) {
                if(srch == '.') srch = '\\.';
				var re = new RegExp(srch, 'g'); 
				name = name.replace(re, repl); 
			}
		}

		// replace all types of quotes with nothing
		name = name.replace(/['"\u0022\u0027\u00AB\u00BB\u2018\u2019\u201A\u201B\u201C\u201D\u201E\u201F\u2039\u203A\u300C\u300D\u300E\u300F\u301D\u301E\u301F\uFE41\uFE42\uFE43\uFE44\uFF02\uFF07\uFF62\uFF63]/g, '');
        
		// replace invalid with dash
		if(ProcessWire.config.InputfieldPageName.charset == 'UTF8') {
			// use UTF8 whitelist
			var whitelist = ProcessWire.config.InputfieldPageName.whitelist;	
			if(whitelist.length) {
				// sanitize using whitelist
				var newName = '';
				for(var n = 0; n < name.length; n++) {
					var c = name.substring(n, n+1);
					if(whitelist.indexOf(c) == -1) c = '-';
					newName += c;
				}
				name = newName;
			} else {
				// no whitelist, allow any
			}
		} else {
			// use ascii as whitelist
			name = name.replace(/[^-_.a-z0-9 ]/g, '-');
		}
	
		// convert whitespace to dash
		name = name.replace(/\s+/g, '-') 
	
		// convert multiple dashes or dots to single
		name = name.replace(/--+/g, '-'); 
	
		// convert multiple dots to single
		name = name.replace(/\.\.+/g, '.'); 
	
		// remove ugly combinations next to each other
		name = name.replace(/(\.-|-\.)/g, '-'); 
	
		// remove leading or trailing dashes, underscores and dots
		name = name.replace(/(^[-_.]+|[-_.]+$)/g, ''); 

		// make sure it's not too long
		// if(name.length > 128) name = name.substring(0, 128); 
		if(name.length > 128) name = $.trim(name).substring(0, 128).split("-").slice(0, -1).join(" "); // @adrian
	
		return name;
	},

	updatePreview: function($t, value) {
		var $previewPath = $('#' + $t.attr('id') + '_path'); 
		var slash = parseInt($previewPath.attr('data-slashUrls')) > 0 ? '/' : '';
		$previewPath.find("strong").text((value.length > 0 ? value + slash : ''))
	}
};

jQuery(document).ready(function($) {
	
	$(document).on("keyup", ".InputfieldPageName input[type=text]", function() {
		var value = InputfieldPageName.sanitize($(this).val());
		InputfieldPageName.updatePreview($(this), value); 
		
	}).on("blur", ".InputfieldPageName input[type=text]", function() {
		var value = InputfieldPageName.sanitize($(this).val());
		$(this).val(value); 
		InputfieldPageName.updatePreview($(this), value); 
	});
	$(document).on("reloaded", ".InputfieldPageName", function() {
		$(this).find("input[type=text]").keyup();
	});
	$(".InputfieldPageName").find("input[type=text]").keyup();
}); 
