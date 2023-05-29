/**
 * Notifications for ProcessWire
 *
 * By Avoine and Ryan Cramer
 *
 */

var Notifications = {

	options: { 				// options that may be passed to init(), these are the defaults:
		ajaxURL: './', 		// URL to poll for ajax updates
		version: 1, 		// notifications version 
		reverse: 0, 
		updateLast: 0, 
		updateDelay: 5000, 
		updateDelayFast: 1550, 
		iconMessage: 'smile-o',
		iconWarning: 'meh-o',
		iconError: 'frown-o',
		iconRemove: 'times-circle',
		classCommon: 'NoticeItem', 
		classMessage: 'NoticeMessage',
		classWarning: 'NoticeWarning',
		classError: 'NoticeError',
		classDebug: 'NoticeDebug',
		classContainer: 'container', 
		ghostDelay: 2000, 
		ghostDelayError: 4000, 
		ghostFadeSpeed: 'fast',
		ghostOpacity: 0.9,
		ghostLimit: 20, 	// max ghosts that that may be shown together
		processKey: '',		// key for session processes (NPK.ClassName.pageID.userID.windowName)
		i18n: {
			sec: 'sec',
			secs: 'secs',
			min: 'min',
			mins: 'mins',
			hour: 'hour',
			hours: 'hours',
			day: 'day',
			days: 'days',
			expires: 'expires',
			now: 'now',
			fromNow: 'from now',
			ago: 'ago'
		}
	},

	updateTimeout: null,	// setTimeout timer for update method
	renderTimeout: null,	// setTimeout timer for render method
	timerTimeout: null,		// setTimeout timer for updateTime()
	updating: false,		// are we currently updating right now?
	runtime: [],	 		// notifications added by manual API add calls
	numRender: 0,			// number of times render() has been called 
	numEmptyRequests: 0,	// number of empty ajax requests in a row, that had no new data
	ghostsActive: 0, 		// number of ghosts currently visible
	currentDelay: 0, 		// current updateDelay value
	turbo: false, 			// whether there is a lot of activity (like with progress bars)
	timeNow: 0,				// current unix timestamp
	useSession: false,		// supports use of sessionStorage?

	$menu: null, 			// <div class='NotificationMenu'>
	$bug: null, 			// <div class='NotificationBug'>
	$list: null, 			// <ul class='NotificationList'>

	/**
	 * Given number of seconds relative to now, return relative time string
	 * 
	 * @param secs
	 * @returns {string}
	 * 
	 */
	relativeTime: function(secs) {
		var str = '';
		if(secs == 0) return str;
		var i18n = Notifications.options.i18n;
		var past = secs > 0;
		secs = Math.abs(secs);
		if(secs > 1 && secs < 60) {
			str = secs + ' ' + (secs == 1 ? i18n.sec : i18n.secs);
		} else if(secs >= 60 && secs < 3600) {
			str = Math.floor(secs / 60); 
			str += ' ' + (str == 1 ? i18n.min : i18n.mins); 
		} else if(secs >= 3600 && secs < 86400) {
			str = Math.floor(secs / 3600);
			str += ' ' + (str == 1 ? i18n.hour : i18n.hours);
		} else if(secs >= 86400) {
			str = Math.floor(secs / 86400);
			str += ' ' + (str == 1 ? i18n.day : i18n.days);
		}
		str += ' ';
		if(past) {
			if(secs < 3) str = i18n.now;
			//	else str += i18n.ago;
		} else {
			if(secs < 3) str = i18n.now;
			//	else str += i18n.fromNow;
		}
		return str;
	},
	
	setTurboMode: function(turbo) {
		if(turbo) {
			// console.log('setTurboMode: true'); 
			if(Notifications.currentDelay != Notifications.options.updateDelayFast) {
				Notifications.currentDelay = Notifications.options.updateDelayFast;
				Notifications.update();
			}
		} else {
			// console.log('setTurboMode: false'); 
			Notifications.currentDelay = Notifications.options.updateDelay;
		}
	},
	
	/**
	 * Given an item, remove it if expired, otherwise update its created and expiration counters
	 * 
	 * @param $li
	 * 
	 */
	_updateItemTime: function($li, timeNow) {
		
		var $created = $li.find('small.created');
		var createdStr = '';
		
		if($created.length > 0) {
			var created = parseInt($created.attr('data-created'));
			var secs = timeNow - created;
			createdStr = Notifications.relativeTime(secs, true);
		}

		var expires = $li.attr('data-expires');
		if(expires) {
			expires = parseInt(expires);
			if(expires > 0 && expires <= timeNow) {
				$li.slideUp('fast', function() {
					Notifications._remove($li);
				});
			} else {
				if(createdStr.length > 0) createdStr += ' / ';
				var expiresStr = Notifications.options.i18n.expires  + ' ' + Notifications.relativeTime(timeNow - expires); // @todo i18n
				if(Math.abs(timeNow - expires) < 10) expiresStr = '<strong>' + expiresStr + '</strong>'; 
				createdStr += expiresStr;
			}
		}

		$created.html(createdStr); 
	},

	/**
	 * Update times for all items and remove expired items
	 * 
	 * @param timeNow
	 * 
	 */
	_updateTime: function() {
		if(Notifications.timeNow == 0) return;
		Notifications.$list.children('li').each(function() {
			Notifications._updateItemTime($(this), Notifications.timeNow);
		});
	},
	
	/**
	 * Check server for new notifications
	 *
	 */
	update: function() {

		if(Notifications.updating) { 
			// if already running, re-schedule it to run in 1 second
			clearTimeout(Notifications.updateTimeout); 
			Notifications.updateTimeout = setTimeout('Notifications.update()', Notifications.currentDelay); 
			return false;
		}

		Notifications.updating = true; 

		var rm = '';
		var $rm = Notifications.$list.find("li.removed"); // find items to be physically removed

		$rm.each(function() {
			rm += $(this).attr('id') + ',';
			$(this).remove();
		}); 

		var url = "./?Notifications=update&time=" + Notifications.options.updateLast; 
		if(rm.length) url += '&rm=' + rm;
		if(Notifications.useSession && Notifications.options.processKey.length) {
			url += '&processKey=' + Notifications.options.processKey + "." + sessionStorage.pwWindowName;
		}
		
		$.getJSON(url, function(data) {
			Notifications._update(data);
			clearTimeout(Notifications.updateTimeout);
			Notifications.updateTimeout = setTimeout('Notifications.update()', Notifications.currentDelay);
			Notifications.updating = false; 
		}); 
	},

	/**
	 * Updates .NotificationTrigger
	 *
	 * param object data Notification data from ajax request
	 * param bool Was added from runtime API? (rather than ajax)
	 *
	 */
	_update: function(data) {
		
		var timeNow = parseInt(data.time);
		var annoy = false;
		var qty = data.notifications.length;
		var alerts = [];
	
		if(qty > 0) {
			Notifications.numEmptyRequests = 0;
		} else {
			Notifications.numEmptyRequests++;
			// stop turbo mode if we've had a few empty requests in a row
			if(Notifications.numEmptyRequests > 2) Notifications.setTurboMode(false);
		}
		
		if(timeNow > 0) Notifications.timeNow = timeNow;

		Notifications.options.updateLast = Notifications.timeNow;

		for(var n = 0; n < qty; n++) {
			var notification = data.notifications[n];
			if(notification.flagNames.indexOf('alert') > -1) {
				alerts[alerts.length] = notification;	
			} else {
				Notifications._add(notification, !data.runtime);
				if (notification.flagNames.indexOf('annoy') > -1) annoy = true;
			}
			if(notification.flagNames.indexOf('no-ghost') < 0) Notifications._ghost(notification, n);
		}

		// if any notifications were marked 'annoy' open the notifications menu now
		if(annoy && !Notifications.$menu.hasClass('open')) Notifications.$bug.trigger('click');
		if(annoy) window.scrollTo(0, 0);

		// only update time if menu is closed since we already have a timer running, 
		// and that timer only runs when menu is already open	
		if(!Notifications.$menu.hasClass("open")) Notifications._updateTime(); 
		
		Notifications._updateBug();
		
		if(alerts.length) {
			for (var n = 0; n < alerts.length; n++) {
				ProcessWire.alert(alerts[n].title);
			}
		}
	},

	/**
	 * Update the notification bug / quantity counter
	 * 
	 * @private
	 * 
	 */
	_updateBug: function() {
		
		var $bug = Notifications.$bug;
		var qtyTotal = 0;
		var qtyError = 0;
		var qtyWarning = 0;
		
		Notifications.$list.children('li').each(function() {
			var $li = $(this);
			qtyTotal++;
			if($li.hasClass('NoticeError')) qtyError++;
				else if($li.hasClass('NoticeWarning')) qtyWarning++;
		});
	
		if(parseInt($bug.attr('data-qty')) == qtyTotal) {
			// no change
		} else {
			// update count and highlight
			Notifications._updateBugQty(qtyTotal);
			$bug.effect('highlight', 300);
		}
		
		if(qtyError > 0) {
			$bug.addClass(Notifications.options.classError, 'slow').removeClass(Notifications.options.classWarning, 'slow');
		} else if(qtyWarning > 0) {
			$bug.addClass(Notifications.options.classWarning, 'slow').removeClass(Notifications.options.classError, 'slow');
		} else {
			$bug.removeClass(Notifications.options.classWarning + ' ' + Notifications.options.classError, 'slow');
		}
	}, 
	
	/**
	 * Update the quantity shown in the bug
	 * 
	 */
	_updateBugQty: function(qtyTotal) {
		
		var $bug = Notifications.$bug;
		var $bugQty = $bug.children('.qty');
		var qtyText = qtyTotal > 99 ? '99+' : qtyTotal;
		
		$bug.attr('class', $bug.attr('class').replace(/qty\d+\s*/g, ''));
		$bug.addClass('qty' + qtyTotal);
		$bugQty.text(qtyText);
		
		if(qtyTotal == 0) {
			$bug.fadeOut();
		} else {
			if(!$bug.is(":visible")) $bug.fadeIn();
		}
		
		$bug.attr('data-qty', qtyTotal);
	},

	/**
	 * Add a notification (external/public API use)
	 *
	 * param object Notification to add containing these properties
	 *
	 */
	add: function(notification) {
		var qty = Notifications.runtime.length;
		notification.addClass = 'runtime';
		notification.runtime = true;
		Notifications.runtime[qty] = notification;
	}, 

	/**
	 * Render any add()'d notifications now (for public API)
	 *
	 */
	render: function() {
		Notifications.renderTimeout = setTimeout(function() { 

			var qtyError = 0;
			var qtyWarning = 0;
			var qtyMessage = 0;

			$(Notifications.runtime).each(function(n, notification) {
				if(notification.flagNames.indexOf('error') != -1) {
					qtyError++;
				} else if(notification.flagNames.indexOf('warning') != -1) {
					qtyWarning++;
				} else {
					qtyMessage++;
				}
			}); 
			
			var data = {
				qty: Notifications.runtime.length, 
				qtyNew: 0,
				qtyMessage: qtyMessage, 
				qtyWarning: qtyWarning,
				qtyError: qtyError, 
				notifications: Notifications.runtime, 
				runtime: true
			}; 
			
			Notifications._update(data, true);
			
			if(Notifications.$list.find(".NotificationProgress").length > 0) {
				Notifications.setTurboMode(true);
			}
			
		}, 250); 
		Notifications.numRender++;
	},

	/**
	 * Add a notification (internal use)
	 *
	 * @param object Notification to add
	 * @param bool highlight Whether to highlight the notification (use true for new notifications)
	 *
	 */
	_add: function(notification, highlight) {

		var exists = false;
		var open = false;
		var $li = Notifications.$list.children("#" + notification.id);
		var progressNext = parseInt(notification.progress);
		var progressPrev = 0;
		var $createdPrev = null;

		if($li.length > 0) {
			exists = true;
			highlight = false;
			open = $li.hasClass('open');
			var $progress = $li.find(".NotificationProgress"); 
			if($progress.length > 0) {
				progressPrev = parseInt($progress.text());
			}
			$createdPrev = $li.find("small.created");
			$li.empty(); // clear it out

		} else {
			$li = $("<li></li>");
		}
		
		$li.attr('id', notification.id).addClass(Notifications.options.classCommon);
		if(notification.expires > 0) {
			$li.attr('data-expires', notification.expires);
		}

		var $icon = $("<i></i>").addClass('fa fa-fw fa-' + notification.icon);
		var $title = $("<span></span>").addClass('NotificationTitle').html(notification.title);
		var $p = $("<p></p>").append($title).prepend('&nbsp;').prepend($icon);
		var $div = $("<div></div>").addClass(Notifications.options.classContainer).append($p);
		var $text = $("<div></div>").addClass('NotificationText');
		var $rm = $("<i class='NotificationRemove fa fa-" + Notifications.options.iconRemove + "'></i>");
		var addClass = '';

		if(progressNext > 0) {
			$li.prepend(Notifications._progress($title, progressNext, progressPrev));
			if(progressNext < 100) {
				$li.addClass('NotificationHasProgress', 'normal');
			}
		}

		if('addClass' in notification && notification.addClass.length > 0) {
			addClass = notification.addClass;
			$li.addClass(addClass);
		}

		if($createdPrev !== null && $createdPrev.length) {
			// keep existing created time
			$title.append('&nbsp;').append($createdPrev);
		} else if('when' in notification && notification.created > 0) {
			// insert created time
			$title.append(" <small data-created='" + notification.created + "' class='created'>" + notification.when + "</small>");
		}

		if(notification.flagNames.indexOf('debug') != -1) $li.addClass(Notifications.options.classDebug); 
		if(notification.flagNames.indexOf('error') != -1) $li.addClass(Notifications.options.classError); 
			else if(notification.flagNames.indexOf('warning') != -1) $li.addClass(Notifications.options.classWarning); 
			else if(notification.flagNames.indexOf('message') != -1) $li.addClass(Notifications.options.classMessage); 
		

		if(notification.html.length > 0) {
			
			$text.html(notification.html); 
			var $chevron = $("<i class='fa fa-chevron-circle-right'></i>"); 
			$title.append(" ").append($chevron);
			
			$title.on('click', function() {
				if($li.hasClass('open')) {
					$li.removeClass('open'); 
					$text.slideUp('fast').removeClass('open'); 
					$chevron.removeClass('fa-chevron-circle-down').addClass('fa-chevron-circle-right'); 
				} else {
					$text.slideDown('fast').addClass('open'); 
					$li.addClass('open'); 
					$chevron.removeClass('fa-chevron-circle-right').addClass('fa-chevron-circle-down');
				}
			}); 
			
			$div.append($text); 
			
			if(open || notification.flagNames.indexOf('open') != -1) {
				if(!open) {
					$title.trigger('click');
					/*
					setTimeout(function() { 
						$text.fadeIn('slow', function() {
							$li.addClass('open'); 
							$text.addClass('open'); 
						});
					}, 500); 
					*/
				} else { 
					$li.addClass('open');
					$text.show().addClass('open');
				}
			}
		}

		// click event for 'remove' link
		$rm.on('click', function() {
			Notifications._remove($li);	
		}); 

		$p.prepend($rm); 
		$li.append($div)

		if(highlight) {
			$li.hide();
			if(Notifications.options.reverse) {
				Notifications.$list.append($li);
			} else {
				Notifications.$list.prepend($li);
			}
			$li.slideDown().effect('highlight', 500); 
		} else if(exists) {
			$li.show();
		} else {
			Notifications.$list.append($li);
		}

	},

	/**
	 * Remove the notification item $li
	 * 
	 * @param $li
	 * @private
	 * 
	 */
	_remove: function($li) {
		// mark it as removed. it will be physically removed by the next update()
		$li.addClass('removed').hide();

		var qtyTotal = $li.siblings(":visible").length;

		// update the quantity shown in the bug
		Notifications._updateBugQty(qtyTotal);

		// tell the update timer to run in 1 second, so that items are removed from the server too
		clearTimeout(Notifications.updateTimeout);
		Notifications.updateTimeout = setTimeout('Notifications.update()', 1000); 
	},

	/**
	 * Get progress bar
	 * 
	 * @param $title
	 * @param progressNext
	 * @param progressPrev
	 * @returns {*|jQuery}
	 * @private
	 * 
	 */
	_progress: function($title, progressNext, progressPrev) {
		
		var $progress = $("<div></div>").addClass('NotificationProgress')
			.html("<span>" + progressNext + '%</span>').css('width', progressPrev + '%').hide();
		
		if(progressNext > progressPrev) {
			
			var duration = progressPrev == 0 ? Notifications.currentDelay / 1.4 : 1750;
			var easing = 'linear';
			
			if(progressNext == 100) {
				duration = 750;
				easing = 'swing';
			} else if(progressPrev == 0) {
				easing = 'swing';
			}
			
			if(progressNext > 0 && progressNext <= 100) {
				$progress.show().animate({
						width: progressNext + '%'
					}, {
						duration: duration,
						easing: easing,
						complete: function() {
							if(progressNext >= 100) {
								$progress.fadeOut('slow', function() {
									$title.parents(".NotificationHasProgress").removeClass('NotificationHasProgress', 'slow'); 
								});
							}
						}
					});
				
			} else if(progressNext >= 100) {
				// don't show
				if(Notifications.$list.find(".NotificationHasProgress").length == 0) Notifications.setTurboMode(false);
				
			} else {
				$progress.css('width', progressNext + '%').show();
			}
			
			$progress.height('100%');
		}
		
		$title.append(" <strong class='NotificationProgressPercent'>" + progressNext + '%</strong>');
		return $progress;
	},

	/**
	 * Add a notification ghost (subtle notification that appears than disappears)
	 *
	 * param object notification Notification to ghost
	 * param int n Index of the notification, affects when it is shown so that multiple don't appear and disappear as a group. 
	 *
	 */
	_ghost: function(notification, n) {
		
		if(notification.progress > 0 && notification.progress < 100) return;
		if(Notifications.$menu.hasClass('open')) return;

		var $icon = $('<i class="fa fa-fw fa-' + notification.icon + '"></i>'); 
		var $ghost = $("<div class='NotificationGhost'></div>").append($icon).append(' ' + $("<span>" + notification.title + "</span>").text());
		var $li = $("<li></li>").append($ghost); 
		var delay = Notifications.options.ghostDelay; 

		if(notification.flagNames.indexOf('error') > -1) {
			$ghost.addClass(Notifications.options.classError); 
			delay = Notifications.options.ghostDelayError;
			
		} else if(notification.flagNames.indexOf('warning') > -1) {
			$ghost.addClass(Notifications.options.classWarning); 
			delay = Notifications.options.ghostDelayError;
			
		} else {
			$ghost.addClass(Notifications.options.classMessage); 
		}

		Notifications.$ghosts.append($li.hide());
		Notifications.ghostsActive++;	
		
		var fadeSpeed = Notifications.options.ghostFadeSpeed;
		var opacity = Notifications.options.ghostOpacity;
		var interval = 100 * n; 
		var windowHeight = $(window).height();

		if(fadeSpeed.length == 0) interval = 200 * n; 

		setTimeout(function() { 
			
			if(fadeSpeed.length > 0) {	
				$li.fadeTo(fadeSpeed, opacity);
			} else {
				$li.show().css('opacity', opacity); 
			}
			
			var y = $li.offset().top; 
			var h = $li.height();
			if(y + h > (windowHeight / 2)) {
				Notifications.$ghosts.animate({ top: "-=" + (h+3) }, 'fast'); 
			}
			
			setTimeout(function() {
				var ghostDone = function() {
					$li.addClass('removed');
					Notifications.ghostsActive--;
					if(Notifications.ghostsActive == 0) {
						Notifications.$ghosts.children('li').remove();
					} 
				};
				if(fadeSpeed.length > 0) { 
					$li.fadeTo(fadeSpeed, 0.01, ghostDone); 
				} else {
					$li.css('opacity', 0.01); 
					ghostDone();
				}
			}, delay); 
		}, interval); 
		
		notification.ghostShown = true; 
	},

	/**
	 * Click event for notification bug element (small red counter)
	 *
	 */
	clickBug: function() {

		var $menu = Notifications.$menu;

		if($menu.hasClass('open')) {

			$menu.slideUp('fast', function() {
				$menu.removeClass('open');
				Notifications.$bug.removeClass('open'); // css only
				clearTimeout(Notifications.timerTimeout);
			}); 	

		} else {

			if(!$menu.hasClass('init')) {
				$menu.prependTo($('body')); 
				$menu.addClass('init'); 
			}
			
			$menu.slideDown('fast', function() {
				$menu.addClass('open');
				Notifications.$bug.addClass('open'); // css only
				Notifications._startTimeUpdater();
			}); 

			Notifications.$ghosts.find('li').hide();
		}

		return false;
	},

	/**
	 * Updates notification item times (created and expire)
	 * 
	 * @private
	 */
	_startTimeUpdater: function() {
		if(Notifications.timeNow > 0) Notifications.timeNow += 1; // increment by 1 seconds
		Notifications._updateTime();
		Notifications.timerTimeout = setTimeout('Notifications._startTimeUpdater()', 1000);
	},

	/**
	 * Show a notification right now (runtime, internal)
	 *
	 */
	_show: function(type, title, html, icon, href) {

		var notification = {
			id: 0,
			title: title, 
			from: '',	
			created: 0, 
			modified: 0, 
			when: 'now', 
			href: href, 
			icon: icon, 
			flags: 0, 
			flagNames: type + ' notice', 
			progress: 0, 
			html: html, 
			qty: 1
		};

		Notifications.add(notification); 

		// if notifications have already been rendered, we can show this one now
		if(Notifications.numRender > 0) {
			Notifications.render(); 
		}
		
	},

	/**
	 * Show a runtime message notification right now
	 *
	 */
	message: function(title, html, icon, href) {
		if(typeof html == "undefined") html = '';
		if(typeof icon == "undefined") icon = Notifications.options.iconMessage; 
		if(typeof href == "undefined") href = '';
		Notifications._show('message', title, html, icon, href); 
	},

	/**
	 * Show a runtime warning notification right now
	 *
	 */
	warning: function(title, html, icon, href) {
		if(typeof html == "undefined") html = '';
		if(typeof icon == "undefined") icon = Notifications.options.iconWarning; 
		if(typeof href == "undefined") href = '';
		Notifications._show('warning', title, html, icon, href); 
	},

	/**
	 * Show a runtime error notification right now
	 *
	 */
	error: function(title, html, icon, href) {
		if(typeof html == "undefined") html = '';
		if(typeof icon == "undefined") icon = Notifications.options.iconError; 
		if(typeof href == "undefined") href = '';
		Notifications._show('error', title, html, icon, href); 
	},


	/**
	 * Initialize notifications, to be called at document.ready
	 *
	 */
	init: function(options) {

		$.extend(Notifications.options, options);
		
		Notifications.currentDelay = Notifications.options.updateDelay;

		Notifications.$menu = $("#NotificationMenu"); 
		Notifications.$bug = $("#NotificationBug"); 
		Notifications.$list = $("#NotificationList"); 
		Notifications.$ghosts = $("#NotificationGhosts");
		
		if(!Notifications.$bug.length) return;

		Notifications.$menu.hide();
		Notifications.$bug.on('click', Notifications.clickBug);
		Notifications.useSession = typeof sessionStorage != "undefined";
		
		// start polling for new notifications
		Notifications.updateTimeout = setTimeout(Notifications.update, Notifications.currentDelay); 

		$("#ProcessPageSearchForm input").on('dblclick', function(e) { 
			Notifications.message(
				"ProcessWire Notifications v" + Notifications.options.version, 
				"Grab a coffee and come and visit us at the <a target='_blank' href='https://processwire.com/talk/'>ProcessWire support forums</a>.</p>", 
				'coffee fa-spin'); 
			return false;
		}); 

		// hide the notifications bug and menu when modal window opened
		$(document).on('pw-modal-opened', function() {
			if(Notifications.$bug.is(":visible")) Notifications.$bug.fadeOut().addClass('hidden-for-modal');
			if(Notifications.$menu.is(":visible")) Notifications.$menu.hide().addClass('hidden-for-modal'); 
		}).on('pw-modal-closed', function() {
			if(Notifications.$bug.hasClass('hidden-for-modal')) Notifications.$bug.fadeIn().removeClass('hidden-for-modal');
			if(Notifications.$menu.hasClass('hidden-for-modal')) Notifications.$menu.slideDown().removeClass('hidden-for-modal');
		});

		if(Notifications.useSession && Notifications.options.processKey.length) {
			if(typeof sessionStorage.pwWindowName == "undefined" || sessionStorage.pwWindowName.indexOf('PW') !== 0) {
				sessionStorage.pwWindowName = 'PW' +
				Math.floor(Math.random() * 0x10000).toString() +
				Math.floor(Math.random() * 0x10000).toString();
			}
			// for debugging:
			// $("#ProcessPageSearchQuery").val(sessionStorage.pwWindowName);
		}
	}
};
