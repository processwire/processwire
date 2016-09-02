/**
 * jQuery Longclick Event
 * ======================
 * Press & hold mouse button "long click" special event for jQuery 1.4.x
 *
 * @license Longclick Event
 * Copyright (c) 2010 Petr Vostrel (http://petr.vostrel.cz/)
 * Dual licensed under the MIT (MIT-LICENSE.txt)
 * and GPL (GPL-LICENSE.txt) licenses.
 *
 * Version: 0.3.2
 * Updated: 2010-06-22
 * 
 */
(function($){

	/*
	 `.click( duration, handler )`

	 * Simply supply `duration` to the well-known `.click` method and you have a *long click*.
	 * This method is a shortcut for `.bind("longclick", handler)`.
	 * Returns *jQuery*.
	 */
	var
		$_fn_click= $.fn.click

	$.fn.click= function click(duration, handler){
		/* Shortcircuit ordinary click calls */
		if (!handler) return $_fn_click.apply(this, arguments)
		/* Bind long click */
		return $(this).data(_duration_, duration || null).bind(type, handler)
	}

	/*
	 `.longclick( [ duration ], [ handler ] )`

	 * If supplied, optional custom `duration` is used for target element(s).
	 * This method is a shortcut for `.click(duration, handler)` when at least `handler` is supplied
	 and for `.trigger("longclick")` if called without arguments.
	 * Returns *jQuery*.
	 */
	$.fn.longclick= function longclick(){
		var
			args= [].splice.call(arguments, 0),
			handler= args.pop(),
			duration= args.pop(),
			$this= $(this).data(_duration_, duration || null)
		return handler ? $this.click(duration, handler) : $this.trigger(type)
	}

	/*
	 Configuration
	 */
	$.longclick= {
		/*
		 * For how long (in milliseconds) mouse button must be pressed down (or touched) stationery
		 to qualify as a *long click*.
		 * False value results in using the configured default.
		 * Default `duration` is **500** and is stored in `jQuery.longclick.duration` variable.
		 */
		duration: 600
	}

	/*
	 Bindings
	 */
	$.event.special.longclick= {
		setup: function(data, namespaces){
			if (!(/iphone|ipad|ipod/i).test(navigator.userAgent)){
				/* normal technique for standard mouse-based interaction */
				$(this)
					.bind(_mousedown_, schedule)
					.bind([_mousemove_, _mouseup_, _mouseout_, _contextmenu_].join(' '), annul)
					.bind(_click_, click)
			}else{
				/* and special handling for touch-based interaction on iPhone-compatibile devices */
				touch_enabled(this)
					.bind(_touchstart_, schedule)
					.bind([_touchend_, _touchmove_, _touchcancel_].join(' '), annul)
					.bind(_click_, click)
					.css({ WebkitUserSelect: 'none' })
			}
		},
		teardown: function(namespaces){
			$(this).unbind(namespace)
		}
	}

	/*
	 Commit subset of touch events to trigger jQuery events of same names
	 */
	function touch_enabled(element){
		$.each('touchstart touchmove touchend touchcancel'.split(/ /), function bind(ix, it){
			element.addEventListener(it, function trigger_jquery_event(event){ $(element).trigger(it) }, false);
		});
		return $(element);
	}

	/*
	 Handlers
	 */
	function schedule(event){
		/* Check the timer isn't already running and drop if so */
		if ($(this).data(_timer_)) return;
		/* Catch in closure the `this` reference and `arguments` for later */
		var
			element= this,
			args= arguments
		/* Flag as "not fired" and schedule the trigger */
		$(this)
			.data(_fired_, false)
			.data(_timer_, setTimeout(scheduled, $(this).data(_duration_) || $.longclick.duration))

		function scheduled(){
			/* Flag as "fired" and rejoin the default event flow */
			$(element).data(_fired_, true)
			event.type= type
			jQuery.event.handle.apply(element, args)
			//jQuery.event.dispatch.apply(element,args);
		}
	}
	function annul(event){
		/* Annul the scheduled trigger */
		$(this).data(_timer_, clearTimeout($(this).data(_timer_)) || null)
	}
	function click(event){
		/* Prevent `click` event to be fired after button release once `longclick` was fired */
		if ($(this).data(_fired_)) return event.stopImmediatePropagation() || false
	}

	/*
	 Frequent primitives and shortcuts
	 */
	var
		type= 'longclick',
		namespace= '.' + type,

	/* Event strings */
		_mousedown_= 'mousedown'+namespace, _click_= 'click'+namespace,
		_mousemove_= 'mousemove'+namespace, _mouseup_= 'mouseup'+namespace,
		_mouseout_= 'mouseout'+namespace, _contextmenu_= 'contextmenu'+namespace,
		_touchstart_= 'touchstart'+namespace, _touchend_= 'touchend'+namespace,
		_touchmove_= 'touchmove'+namespace, _touchcancel_= 'touchcancel'+namespace,

	/* Storage keys */
		_duration_= 'duration'+namespace, _timer_= 'timer'+namespace, _fired_= 'fired'+namespace

})(jQuery);