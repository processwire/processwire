function setupCommandSearch() {
	const searchInput = document.querySelector("#pw-masthead .pw-search-input");
	const sidebarSearchInput = document.querySelector(
		"#offcanvas-nav .pw-search-input"
	);
	let previousFocusedElement = null;
	let SearchOpen = false;
	
	// Find the closest ancestor with class "pw-search-form"
	const searchForm = searchInput.closest(".pw-search-form");
	const sidebarSearchForm = sidebarSearchInput.closest(".pw-search-form");
	
	const isMac = navigator.platform.toUpperCase().indexOf("MAC") >= 0;
	const shortcutText = isMac ? "⌘+K" : "Ctrl+K";
	const sidebarShortcutText = isMac ? "⌘+⇧+K" : "Ctrl+⇧+K";
	
	searchInput.setAttribute("placeholder", " ");
	sidebarSearchInput.setAttribute("placeholder", " ");
	
	searchForm.setAttribute("data-shortcut", shortcutText);
	sidebarSearchForm.setAttribute("data-shortcut", sidebarShortcutText);
	
	function openSearch() {
		previousFocusedElement = document.activeElement;
		searchInput.focus();
		SearchOpen = true;
		closeSidebar();
	}
	
	function closeSearch() {
		searchInput.blur();
		searchInput.value = "";
		if (previousFocusedElement) {
			previousFocusedElement.focus();
			previousFocusedElement = null;
		}
		SearchOpen = false;
	}
	
	function openSearchSidebar() {
		let sidebarToggleEL = document.getElementById("offcanvas-toggle");
		if (sidebarToggleEL) {
			let sidebarSearchInput = document.querySelector(
				"#offcanvas-nav .pw-search-input"
			);
			
			sidebarToggleEL.click();
			if (sidebarSearchInput) {
				sidebarSearchInput.focus();
				closeSearch();
			}
		}
	}
	
	function closeSidebar() {
		let sidebarToggleEL = document.querySelector(
			"body.uk-offcanvas-page .offcanvas-toggle"
		);
		if (sidebarToggleEL) {
			sidebarToggleEL.click();
			sidebarSearchInput.blur();
		}
	}
	
	// Toggle on Ctrl/Cmd + K
	document.addEventListener("keydown", (event) => {
		const isMac = navigator.platform.toUpperCase().indexOf("MAC") >= 0;
		const isCommandKey = event.metaKey;
		const isControlKey = event.ctrlKey;
		
		const modifierKey = isMac ? isCommandKey : isControlKey;
		
		if (modifierKey) {
			if (event.key === "k") {
				event.preventDefault();
				
				// Ctrl/⌘+Shift+K focus on sidebar search
				if (event.shiftKey) {
					openSearchSidebar();
				} else {
					// Ctrl/⌘+K focus on search
					if (SearchOpen) {
						closeSearch();
					} else {
						openSearch();
					}
				}
			}
			
			// close search with escape
		} else if (event.key === "Escape") {
			closeSearch();
			let sidebarToggleEL = document.querySelector(
				"body.uk-offcanvas-page .offcanvas-toggle"
			);
			if (sidebarToggleEL) {
				sidebarToggleEL.click();
			}
		}
	});
}

var AdminDarkMode = {
	
	isInit: false,
	mode: -1,
	body: null,
	a: null,
	
	init: function() {
		this.body = $('body');
		if(this.body.hasClass('light-theme')) {
			this.mode = 0;
		} else if(this.body.hasClass('dark-theme')) {
			this.mode = 1;
		}
		this.a = $('.toggle-light-dark-mode');
		this.updateLink();
		this.isInit = true;
	},
	
	get: function() {
		return this.mode;
	},
	
	getName: function(mode) {
		if(typeof mode === 'undefined') mode = this.mode;
		if(mode > 0) return 'dark';
		if(mode < 0) return 'auto';
		return 'light';
	},
	
	updateLink: function() {
		var lightIcon = 'fa-' + this.a.attr('data-icon-light');
		var darkIcon = 'fa-' + this.a.attr('data-icon-dark');
		var autoIcon = 'fa-' + this.a.attr('data-icon-auto');
		var modeName = this.getName();
		
		if(modeName != 'auto') {
			// reverse of mode name for link
			modeName = modeName === 'dark' ? 'light' : 'dark';
		}
		
		var text = this.a.attr('data-label-' + modeName);
		var icon = 'fa-' + this.a.attr('data-icon-' + modeName);
		var $span = this.a.children('span');
		var $icon = this.a.children('i');
		
		$span.text(text);
		$icon.removeClass(lightIcon + ' ' + darkIcon + ' ' + autoIcon).addClass(icon);
	},
	
	set: function(mode) {
		this.body.removeClass(this.getName() + '-theme');
		this.body.addClass(this.getName(mode) + '-theme');
		this.mode = mode;
		this.updateLink();
		// $(document).trigger('admin-color-change'); 
	},
	
	setLight: function() {
		this.set(0);
	},
	
	setDark: function() {
		this.set(1);
	},
	
	setAuto: function() {
		this.set(-1);
	},
	
	save: function() {
		$.post(ProcessWire.config.urls.admin, {set_admin_dark_mode: this.mode}, function(data) {
			// console.log(data);	
		});
	},
	
	toggle: function() {
		var newMode = this.mode > 0 ? 0 : 1;
		this.set(newMode);
		this.updateLink();
	},
	
	toggleDialog: function() {
		
		var oldMode = this.mode;
		var newMode = (oldMode < 0 ? oldMode : (oldMode > 0 ? 0 : 1));
		var newModeName = this.getName(newMode);
		var dialogHtml = $('#light-dark-mode-dialog').html();
		var attr = 'data-name="' + newModeName + '"';
		
		dialogHtml = dialogHtml.replace(attr, attr + ' checked');
		var dialog = UIkit.modal.dialog(dialogHtml, { });
		var $panel = $(dialog.panel);
		
		$panel.css('transition', 'none');
		this.set(newMode);
		
		$panel.find('input').on('click', function() {
			AdminDarkMode.set(parseInt($(this).val()));
		});
		
		$panel.find('button').on('click', function() {
			if($(this).hasClass('uk-button-primary')) {
				AdminDarkMode.save(); // Ok
			} else {
				AdminDarkMode.set(oldMode); // Cancel
			}
		});
		
		$panel.find('input[value=' + newMode + ']');
		
		return false;
	}, 
	
};

// Call the setup function when the DOM is loaded
// document.addEventListener("DOMContentLoaded", setupCommandSearch);

$(document).ready(function () {
	if($("#pw-masthead .pw-search-input").length) {
		setupCommandSearch();
	}
	$(".pw-notices").insertAfter("#pw-mastheads");
	if(!$('body').hasClass('pw-no-dark-mode')) AdminDarkMode.init();
});
