$(document).ready(function() {
	
	if(!ProcessWire.config.ProcessPageEditLink) return;
	
	var cfg = ProcessWire.config.ProcessPageEditLink;

	var options = {
		selectStartLabel: cfg.selectStartLabel,
		selectSelectLabel: cfg.selectStartLabel,
		langID: cfg.langID
		// openPageIDs: config.ProcessPageEditLink.openPageIDs
	};
	
	var options2 = {
		selectStartLabel: options.selectStartLabel,
		selectSelectLabel: options.selectStartLabel,
		langID: options.langID,
		rootPageID: cfg.pageID
	};

	var selectedPageData = {
		id: 0,
		title: '', 
		url: ''
	};

	var $fileSelect = $("#link_page_file"); 
	var $anchorSelect = $("#link_page_anchor");
	var $linkPageURL = $("#link_page_url_input");
	var $linkText = $("#link_text");

	$linkPageURL.val($("#link_page_url").val()); // copy from hidden 

	function populateFileSelect(selectedPageData) {
		// populate the files field
		var $wrap = $("#wrap_link_page_file"); 
		$.getJSON("./files?id=" + selectedPageData.id, function(data) {
			$fileSelect.empty();	
			$fileSelect.append("<option></option>"); 
			$.each(data, function(key, val) {
				var $option = $("<option value='" + key + "'>" + val + "</option>"); 		
				$fileSelect.append($option);
			});
			$wrap.find("p.notes strong").text(selectedPageData.url);
			if($fileSelect.is(":visible")) {
				$wrap.children().effect('highlight', {}, 500);
				$fileSelect.effect('bounce', {}, 50);
			}
		}); 
	}

	function absoluteToRelativePath(path) {
		if(cfg.urlType == 0) return path;

		function slashesToRelative(url) {
			url = url.replace(/\//g, '../'); 
			url = url.replace(/[^.\/]/g, ''); 
			return url;
		}
		
		var url;

		if(path === cfg.pageUrl) {
			// account for the link to self
			path = './'; 
			if(!cfg.slashUrls) path += cfg.pageName;

		} else if(path.indexOf(cfg.pageUrl) === 0) { 
			// linking to child of current page
			path = path.substring(cfg.pageUrl.length); 
			if(!cfg.slashUrls) path = cfg.pageName + path;

		} else if(cfg.pageUrl.indexOf(path) === 0) {
			// linking to a parent of the current page
			url = cfg.pageUrl.substring(path.length); 
			if(url.indexOf('/') != -1) {
				url = slashesToRelative(url); 
			} else {
				url = './';
			}
			path = url;
		} else if(path.indexOf(cfg.rootParentUrl) === 0) {
			// linking to a sibling or other page in same branch (but not a child)
			url = path.substring(cfg.rootParentUrl.length); 
			var url2 = url;
			url = slashesToRelative(url) + url2; 	
			path = url;
			
		} else if(cfg.urlType == 2) { // 2=relative for all
			// page in a different tree than current
			// traverse back to root
			url = cfg.pageUrl.substring(ProcessWire.config.urls.root.length); 
			url = slashesToRelative(url); 
			path = path.substring(ProcessWire.config.urls.root.length); 
			path = url + path; 
		}
		return path; 
	}

	function pageSelected(event, data) {

		if(data.url && data.url.length) {
			selectedPageData = data;
			selectedPageData.url = ProcessWire.config.urls.root + data.url.substring(1);
			selectedPageData.url = absoluteToRelativePath(selectedPageData.url); 
			$linkPageURL.val(selectedPageData.url).trigger('change');
			populateFileSelect(selectedPageData); // was: if($fileSelect.is(":visible")) { ... }
		}

		$(this).parents(".InputfieldInteger").children(".InputfieldHeader").trigger('click') // to close the field
			.parent().find('.PageListSelectHeader').removeClass('hidden').show(); // to open the pagelist select header so it can be re-used if the field is opened again
		
	}
	
	$("#link_page_id").ProcessPageList(options).hide().on('pageSelected', pageSelected);
	$("#child_page_id").ProcessPageList(options2).hide().on('pageSelected', pageSelected); 

	$fileSelect.on('change', function() {
		var $t = $(this);
		var src = $t.val();
		if(src.length) $linkPageURL.val(src).trigger('change');
	}); 
	
	if($anchorSelect.length) {
		var anchorPreviousValue = $anchorSelect.val();
		$anchorSelect.on('change', function() {
			var val = $(this).val();
			if(val.length) {
				// populated anchor value
				$linkPageURL.val(val); 
				anchorPreviousValue = val;
			} else {
				// empty value
				// make URL field blank only if present value is the same as a previously selected anchor value
				if($linkPageURL.val() == anchorPreviousValue) $linkPageURL.val('');
			}
			$linkPageURL.trigger('change');
		});
		// de-select anchor when URL is changed to something other than an ahcor
		// $linkPageURL.on('change', function() {
		// }); 
	}

	// auto-insert scheme/protocol when not present and domain is detected
	
	function updateLinkPreview() {
		
		if(!$linkPageURL.val().length) {
			$("#link_markup").text('');
			return;
		}
		
		var $link = $("<a />");
		$link.attr('href', $linkPageURL.val()); 
	
		var $linkTitle = $("#link_title"); 
		if($linkTitle.length && $linkTitle.val().length) {
			var val = $("<div />").text($linkTitle.val()).html();
			$link.attr('title', val); 
		}
		
		if(cfg.noLinkTextEdit) {
			// link text editing disabled
		} else if($linkText.length && $linkText.val().length) {
			$link.text($linkText.val());
		}

		var $linkRel = $("#link_rel"); 
		if($linkRel.length && $linkRel.val() && $linkRel.val().length) {
			$link.attr('rel', $linkRel.val()); 
		}
		
		var $linkTarget = $("#link_target"); 
		if($linkTarget.length && $linkTarget.val().length) {
			$link.attr('target', $linkTarget.val()); 
		}

		var $linkClass = $("#wrap_link_class").find('input:checked');
		if($linkClass.length) {
			$linkClass.each(function() {
				$link.addClass($(this).val()); 
			});
		}
		
		$("#link_markup").text($link[0].outerHTML);
	}
	
	function urlKeydown() {
		
		var $this = $linkPageURL;
		var val = ProcessWire.trim($this.val());
		var dotpos = val.indexOf('.');
		var slashespos = val.indexOf('//');
		var hasScheme = slashespos > -1 && slashespos < dotpos;
		var slashpos = (slashespos > -1 ? val.indexOf('/', slashespos + 2) : val.indexOf('/'));
		var httpHost;
		var n;

		if(dotpos > -1 && val.indexOf('..') == -1 && val.indexOf('./') == -1 && (
			(slashpos > dotpos && !hasScheme) ||
			(slashpos == -1 && dotpos > 1 && val.match(/^[a-z][-a-z.0-9]+\.[a-z]{2,}($|\/)/i))
			)) {
			// no scheme present and matched: [www.]domain.com or [www.]domain.com/path/...
			var domain = val.substring(0, (slashpos > 0 ? slashpos : val.length)); 
			hasScheme = true;

			// avoid adding scheme if we already added it before and user removed it
			if ($this.attr('data-ignore') == domain) {
				// do nothing
			} else {
				$this.val('http://' + val);
				$this.closest('.InputfieldContent').find('.notes').text('http://' + val); 
				$this.attr('data-ignore', domain);
			}
		} else if(dotpos > 0 && 
			val.indexOf('@') > 0 && 
			val.indexOf(':') == -1 && 
			val.match(/^[^@]+@[-.a-z0-9]{2,}\.[a-z]{2,}$/i)) {
			// email address
			$this.val('mailto:' + val); 
			$this.addClass('email');
		} else if(val.indexOf('@') == -1 && $this.hasClass('email')) {
			$this.removeClass('email');
		}
		
		if(val.substring(0, 1) == '#') {
			$this.addClass('anchor'); 
		} else if($this.hasClass('anchor')) {
			$this.removeClass('anchor'); 
		}
		
		if(hasScheme) {
			if(slashpos == -1) slashpos = val.length;
			httpHost = (slashespos > -1 ? val.substring(slashespos + 2, slashpos) : val.substring(0, slashpos));
			$this.attr('data-httphost', httpHost);
		} else {
			$this.removeAttr('data-httphost'); 
		}
		// console.log('httpHost=' + $this.attr('data-httphost'));

		function icon() {
			return $this.closest('.Inputfield').children('.InputfieldHeader').children('i').eq(0);
		}

		var external = false;
		httpHost = $this.attr('data-httphost');
		if(httpHost && httpHost.length) {
			external = true; 
			for(n = 0; n < ProcessWire.config.httpHosts; n++) {
				if(ProcessWire.config.httpHosts[n] == httpHost) {
					external = false;
					break;
				}
			}
		}

		var primaryIcon = 'fa-external-link-square';	
		var extLinkIcon = 'fa-external-link';
		var emailIcon = 'fa-envelope-o';
		var anchorIcon = 'fa-flag-o';
		var allIcons = primaryIcon + ' ' + extLinkIcon + ' ' + emailIcon + ' ' + anchorIcon;
		var extLinkClass = cfg.extLinkClass;
		var extLinkClassAll = extLinkClass.replace(' ', '_'); 
		var extLinkClasses = extLinkClass.indexOf(' ') > -1 ? extLinkClass.split(' ') : [ extLinkClass ];
		var extLinkRel = cfg.extLinkRel;
		var extLinkTarget = cfg.extLinkTarget;
		
		if(external) {
			if(!$this.hasClass('external-link')) {
				icon().removeClass(allIcons).addClass(extLinkIcon);
				$this.addClass('external-link');
				if(extLinkTarget.length > 0) $("#link_target").val(extLinkTarget);
				if(extLinkRel.length > 0) $("#link_rel").val(extLinkRel);
				if(extLinkClasses.length > 0) {
					if(extLinkClasses.length > 1) {
						$("#link_class_" + extLinkClassAll).prop('checked', true); // all classes in 1 option
					}
					for(n = 0; n < extLinkClasses.length; n++) {
						$("#link_class_" + extLinkClasses[n]).prop('checked', true);
					}
				}
			}
		} else {
			if($this.hasClass('external-link')) {
				// was previously an external link but no longer is
				$this.removeClass('external-link');
				if(extLinkRel.length) $('#link_rel').val('');
				if(extLinkTarget.length) $('#link_target').val('');
				$("#link_class_" + extLinkClassAll).prop('checked', false); // all classes in 1 option
				for(n = 0; n < extLinkClasses.length; n++) {
					$("#link_class_" + extLinkClasses[n]).prop('checked', false);
				}
			}
			var $icon = icon();
			if($this.hasClass('email')) {
				if(!$icon.hasClass(emailIcon)) $icon.removeClass(allIcons).addClass(emailIcon);
			} else if($this.hasClass('anchor')) {
				if(!$icon.hasClass(anchorIcon)) $icon.removeClass(allIcons).addClass(anchorIcon);
			} else if(!$this.hasClass(primaryIcon)) {
				$icon.removeClass(allIcons).addClass(primaryIcon);
			}
		}
		updateLinkPreview();
	}
	
	var urlKeydownTimer = null;
	$linkPageURL.trigger('focus').on('keydown', function(event) {
		if(urlKeydownTimer) clearTimeout(urlKeydownTimer); 
		urlKeydownTimer = setTimeout(function() { urlKeydown(); }, 500); 
	});
	
	$linkPageURL.on('change', function() {
		var val = $(this).val();
		if($anchorSelect.length) {
			if(val.substring(0, 1) == '#') {
				var found = '';
				$anchorSelect.children('option').each(function() {
					if($(this).attr('value') == val) found = val;
				});
				$anchorSelect.val(found);
			} else if($anchorSelect.val().length) {
				$anchorSelect.val(''); 
			}
		}	
		$("#link_page_url").val(val); // legacy
		urlKeydown(); 
	}); 
	
	setTimeout(function() {
		$linkPageURL.trigger('change');
		$linkText.trigger('change');
	}, 250); 
	
	$(":input").on('change', updateLinkPreview);
	$("#link_title").on('keydown', function(event) { updateLinkPreview(); });
	$linkText.on('keyup', function(event) { updateLinkPreview(); });

	// when header is clicked, open up the pageList right away
	$(".InputfieldInteger .InputfieldHeader").on('click', function() {

		var $t = $(this);
		var $toggle = $t.parent().find(".PageListSelectActionToggle");
		var $pageSelectHeader = $toggle.parents('.PageListSelectHeader'); 

		if($pageSelectHeader.is('.hidden')) {
			// we previously hid the pageSelectHeader since it's not necessary in this context
			// so, we can assume the field is already open, and is now being closed
			return true; 
		}

		// hide the pageSelectHeader since it's extra visual baggage here we don't need
		$pageSelectHeader.addClass('hidden').hide();

		// automatically open the PageListSelect
		setTimeout(function() { $toggle.trigger('click'); }, 250); 
		return true; 
	});

	var $form = $('#ProcessPageEditLinkForm'); 
	if($form.length) {
		$form.WireTabs({
			items: $(".WireTab"),
			id: 'PageEditLinkTabs'
		});
	}

	setTimeout(function() {
		$('#link_page_url_input').trigger('focus');
	}, 250); 
}); 
