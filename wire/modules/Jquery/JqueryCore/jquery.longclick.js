/**
 * jQuery Longclick Event
 * ======================
 * Press & hold mouse button "long click" special event for jQuery 1.4.x
 *
 * @license Longclick Event
 * Copyright (c) 2014 Pascal GANAYE
 * Largely based from from a work Petr Vostrel (http://petr.vostrel.cz/)
 * Dual licensed under the MIT (MIT-LICENSE.txt)
 * and GPL (GPL-LICENSE.txt) licenses.
 *
 * Version: 0.4.0
 * Updated: 2014-10-13
 */

 (function ($) {
    /** `.longclick([ handler ], [ duration ])`
     * If supplied, optional custom `duration` is used for target element(s).
     * This method is a shortcut for `.trigger("longclick")` if called without arguments.
     * Returns *jQuery*.
     */
    $.fn.longclick = function longclick() {
        var handler = arguments[0],
            duration = arguments[1],
            $this = $(this).data(_duration_, duration || null);
        /* Bind long click */
        return handler ? $this.on(type, handler) : $this.trigger(type);
    };

    /**
     *  Configuration
     */
    $.longclick = {
        /**
         * For how long (in milliseconds) mouse button must be pressed down (or touched) stationery
         * to qualify as a *long click*.
         * False value results in using the configured default.
         * Default `duration` is **600** and is stored in `jQuery.longclick.duration` variable.
         */
        duration: 600
    };

    /**
     * Bindings
     */
    $.event.special.longclick = {
        setup: function (data, namespaces) {
            if (!(/iphone|ipad|ipod/i).test(navigator.userAgent)) {
                /* normal technique for standard mouse-based interaction */
                $(this)
                    .on(_mousedown_, schedule)
                    .on([_mouseup_, _mouseout_, _contextmenu_].join(' '), cancel)
                    .on(_click_, click);
            } else {
                /* and special handling for touch-based interaction on iPhone-compatibile devices */
                touch_enabled(this)
                    .on(_touchstart_, schedule)
                    .on([_touchend_, _touchcancel_].join(' '), cancel)
                    .on(_click_, click)
                    .css({WebkitUserSelect: 'none'});
            }
        },
        teardown: function (namespaces) {
            $(this).off(namespace);
        }
    };

    /**
     * Commit subset of touch events to trigger jQuery events of same names
     */
    function touch_enabled(element) {
        $.each('touchstart touchend touchcancel'.split(/ /), function bind(ix, it) {
            element.addEventListener(it, function trigger_jquery_event(event) {
                $(element).trigger(it);
            }, false);
        });
        return $(element);
    }

    /**
     * Handlers
     */
    function schedule(event) {
        /* Check the timer isn't already running and drop if so */
        if ($(this).data(_timer_)) return;
        /* Catch in closure the `this` reference and `arguments` for later */
        var element = this;
        var args = arguments;

            function scheduled() {
                /* Flag as "fired" and rejoin the default event flow */
                $(element).data(_fired_, true)
                    .data(_timer_, null);
                event.type = type;
                var f = (jQuery.event.dispatch || jQuery.event.handle);
                f.apply(element, args);
            }

        /* Flag as "not fired" and schedule the trigger */
        $(this)
            .data(_fired_, false)
            .data(_timer_, setTimeout(scheduled, $(this).data(_duration_) || $.longclick.duration));

    }

    function cancel(event) {
        var timer = $(this).data(_timer_);
        if (timer) {
            /* cancel the scheduled trigger */
            $(this).data(_timer_, clearTimeout(timer) || null);
        }
    }

    function click(event) {
        /* Prevent `click` event to be fired after button release once `longclick` was fired */
        if ($(this).data(_fired_)) return event.stopImmediatePropagation() || false;
    }

    /*
     * Frequent primitives and shortcuts
     */
    var type = 'longclick',
        namespace = '.' + type,

        /* Event strings */
        _mousedown_ = 'mousedown' + namespace,
        _click_ = 'click' + namespace,
        _mouseup_ = 'mouseup' + namespace,
        _mouseout_ = 'mouseout' + namespace,
        _contextmenu_ = 'contextmenu' + namespace,
        _touchstart_ = 'touchstart' + namespace,
        _touchend_ = 'touchend' + namespace,
        _touchcancel_ = 'touchcancel' + namespace,

        /* Storage keys */
        _duration_ = 'duration' + namespace,
        _timer_ = 'timer' + namespace,
        _fired_ = 'fired' + namespace;

})(jQuery);
