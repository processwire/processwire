$(document).ready(function() {

	var options = {
		selectStartLabel: ProcessWire.config.ProcessPageEditLink.selectStartLabel,
		selectSelectLabel: ProcessWire.config.ProcessPageEditLink.selectStartLabel,
		langID: ProcessWire.config.ProcessPageEditLink.langID
		// openPageIDs: config.ProcessPageEditLink.openPageIDs
		};
	var options2 = {
		selectStartLabel: options.selectStartLabel,
		selectSelectLabel: options.selectStartLabel,
		langID: options.langID,
		rootPageID: ProcessWire.config.ProcessPageEditLink.pageID
		};

	var selectedPageData = {
		id: 0,
		title: '', 
		url: ''
	};

	var $fileSelect = $("#link_page_file"); 
	var $anchorSelect = $("#link_page_anchor");
	var $linkPageURL = $("#link_page_url_input"); 
	
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
		if(ProcessWire.config.ProcessPageEditLink.urlType == 0) return path;

		function slashesToRelative(url) {
			url = url.replace(/\//g, '../'); 
			url = url.replace(/[^.\/]/g, ''); 
			return url;
		}

		if(path === ProcessWire.config.ProcessPageEditLink.pageUrl) {
			// account for the link to self
			path = './'; 
			if(!ProcessWire.config.ProcessPageEditLink.slashUrls) path += ProcessWire.config.ProcessPageEditLink.pageName;

		} else if(path.indexOf(ProcessWire.config.ProcessPageEditLink.pageUrl) === 0) { 
			// linking to child of current page
			path = path.substring(ProcessWire.config.ProcessPageEditLink.pageUrl.length); 
			if(!ProcessWire.config.ProcessPageEditLink.slashUrls) path = ProcessWire.config.ProcessPageEditLink.pageName + path;

		} else if(ProcessWire.config.ProcessPageEditLink.pageUrl.indexOf(path) === 0) {
			// linking to a parent of the current page
			var url = ProcessWire.config.ProcessPageEditLink.pageUrl.substring(path.length); 
			if(url.indexOf('/') != -1) {
				url = slashesToRelative(url); 
			} else {
				url = './';
			}
			path = url;
		} else if(path.indexOf(ProcessWire.config.ProcessPageEditLink.rootParentUrl) === 0) {
			// linking to a sibling or other page in same branch (but not a child)
			var url = path.substring(ProcessWire.config.ProcessPageEditLink.rootParentUrl.length); 
			var url2 = url;
			url = slashesToRelative(url) + url2; 	
			path = url;
			
		} else if(ProcessWire.config.ProcessPageEditLink.urlType == 2) { // 2=relative for all
			// page in a different tree than current
			// traverse back to root
			var url = ProcessWire.config.ProcessPageEditLink.pageUrl.substring(config.urls.root.length); 
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
			$linkPageURL.val(selectedPageData.url).change();
			populateFileSelect(selectedPageData); // was: if($fileSelect.is(":visible")) { ... }
		}

		$(this).parents(".InputfieldInteger").children(".InputfieldHeader").click() // to close the field
			.parent().find('.PageListSelectHeader').removeClass('hidden').show(); // to open the pagelist select header so it can be re-used if the field is opened again
		
	}
	
	$("#link_page_id").ProcessPageList(options).hide().bind('pageSelected', pageSelected);
	$("#child_page_id").ProcessPageList(options2).hide().bind('pageSelected', pageSelected); 

	$fileSelect.change(function() {
		var $t = $(this);
		var src = $t.val();
		if(src.length) $linkPageURL.val(src).change();
	}); 
	
	if($anchorSelect.length) {
		var anchorPreviousValue = $anchorSelect.val();
		$anchorSelect.change(function () {
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
			$linkPageURL.change();
		});
		// de-select anchor when URL is changed to something other than an ahcor
		// $linkPageURL.change(function() {
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
		
		var $linkRel = $("#link_rel"); 
		if($linkRel.length && $linkRel.val().length) {
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
		var val = $.trim($this.val());
		var dotpos = val.indexOf('.');
		var slashespos = val.indexOf('//');
		var hasScheme = slashespos > -1 && slashespos < dotpos;
		var slashpos = (slashespos > -1 ? val.indexOf('/', slashespos + 2) : val.indexOf('/'));

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
			if (slashpos == -1) slashpos = val.length;
			var httpHost = (slashespos > -1 ? val.substring(slashespos + 2, slashpos) : val.substring(0, slashpos));
			$this.attr('data-httphost', httpHost);
		} else {
			$this.removeAttr('data-httphost'); 
		}
		// console.log('httpHost=' + $this.attr('data-httphost'));

		function icon() {
			return $this.closest('.Inputfield').children('.InputfieldHeader').children('i').eq(0);
		}

		var external = false;
		var httpHost = $this.attr('data-httphost');
		if(httpHost && httpHost.length) {
			external = true; 
			for(var n = 0; n < ProcessWire.config.httpHosts; n++) {
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
		
		if(external) {
			if (!$this.hasClass('external-link')) {
				icon().removeClass(allIcons).addClass(extLinkIcon);
				$this.addClass('external-link');
				var extLinkTarget = ProcessWire.config.ProcessPageEditLink.extLinkTarget;
				if (extLinkTarget.length > 0) {
					$("#link_target").val(extLinkTarget);
				}
				var extLinkRel = ProcessWire.config.ProcessPageEditLink.extLinkRel;
				if (extLinkRel.length > 0) {
					$("#link_rel").val(extLinkRel);
				}
				var extLinkClass = ProcessWire.config.ProcessPageEditLink.extLinkClass;
				if (extLinkClass.length > 0) {
					extLinkClass = extLinkClass.split(' ');
					for (var n = 0; n < extLinkClass.length; n++) {
						$("#link_class_" + extLinkClass[n]).attr('checked', 'checked');
					}
				}
			}
		} else {
			$this.removeClass('external-link');
			if($this.hasClass('email')) {
				if (!icon().hasClass(emailIcon)) icon().removeClass(allIcons).addClass(emailIcon);
			} else if($this.hasClass('anchor')) {
				if (!icon().hasClass(anchorIcon)) icon().removeClass(allIcons).addClass(anchorIcon);
			} else if(!$this.hasClass(primaryIcon)) {
				icon().removeClass(allIcons).addClass(primaryIcon);
			}
		}
		updateLinkPreview();
	}
	
	var urlKeydownTimer = null;
	$linkPageURL.focus().keydown(function(event) {
		if(urlKeydownTimer) clearTimeout(urlKeydownTimer); 
		urlKeydownTimer = setTimeout(function() { urlKeydown(); }, 500); 
	});
	
	$linkPageURL.change(function() {
		var val = $(this).val();
		if($anchorSelect.length) {
			if(val.substring(0, 1) == '#') {
				var found = '';
				$anchorSelect.children('option').each(function () {
					if ($(this).attr('value') == val) found = val;
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
		$linkPageURL.change();
	}, 250); 
	
	$(":input").change(updateLinkPreview);
	$("#link_title").keydown(function(event) { updateLinkPreview(); }); 

	// when header is clicked, open up the pageList right away
	$(".InputfieldInteger .InputfieldHeader").click(function() {

		var $t = $(this);
		var $toggle = $t.parent().find(".PageListSelectActionToggle");
		var $pageSelectHeader = $toggle.parents('.PageListSelectHeader'); 

		if($pageSelectHeader.is(".hidden")) {
			// we previously hid the pageSelectHeader since it's not necessary in this context
			// so, we can assume the field is already open, and is now being closed
			return true; 
		}

		// hide the pageSelectHeader since it's extra visual baggage here we don't need
		$pageSelectHeader.addClass('hidden').hide();

		// automatically open the PageListSelect
		setTimeout(function() { $toggle.click(); }, 250); 
		return true; 
	});

	$('#ProcessPageEditLinkForm').WireTabs({
		items: $(".WireTab"), 
		id: 'PageEditLinkTabs'
	});

	setTimeout(function() {
		$('#link_page_url_input').focus();
	}, 250); 
}); 
