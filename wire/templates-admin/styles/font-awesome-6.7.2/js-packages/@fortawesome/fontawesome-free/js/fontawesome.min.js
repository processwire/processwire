/*!
 * Font Awesome Free 6.7.2 by @fontawesome - https://fontawesome.com
 * License - https://fontawesome.com/license/free (Icons: CC BY 4.0, Fonts: SIL OFL 1.1, Code: MIT License)
 * Copyright 2024 Fonticons, Inc.
 */
(() => {
    function R(t, e, a) {
        var n;
        (e = "symbol" == typeof (n = ((t, e) => {
            if ("object" != typeof t || !t) return t;
            var a = t[Symbol.toPrimitive];
            if (void 0 === a) return ("string" === e ? String : Number)(t);
            if ("object" != typeof (a = a.call(t, e || "default"))) return a;
            throw new TypeError("@@toPrimitive must return a primitive value.");
        })(e, "string")) ? n : n + "") in t ? Object.defineProperty(t, e, {
            value: a,
            enumerable: !0,
            configurable: !0,
            writable: !0
        }) : t[e] = a;
    }
    function L(e, t) {
        var a, n = Object.keys(e);
        return Object.getOwnPropertySymbols && (a = Object.getOwnPropertySymbols(e), 
        t && (a = a.filter(function(t) {
            return Object.getOwnPropertyDescriptor(e, t).enumerable;
        })), n.push.apply(n, a)), n;
    }
    function b(e) {
        for (var t = 1; t < arguments.length; t++) {
            var a = null != arguments[t] ? arguments[t] : {};
            t % 2 ? L(Object(a), !0).forEach(function(t) {
                R(e, t, a[t]);
            }) : Object.getOwnPropertyDescriptors ? Object.defineProperties(e, Object.getOwnPropertyDescriptors(a)) : L(Object(a)).forEach(function(t) {
                Object.defineProperty(e, t, Object.getOwnPropertyDescriptor(a, t));
            });
        }
        return e;
    }
    let T = {}, Y = {}, W = null, H = {
        mark: t = () => {},
        measure: t
    };
    try {
        "undefined" != typeof window && (T = window), "undefined" != typeof document && (Y = document), 
        "undefined" != typeof MutationObserver && (W = MutationObserver), "undefined" != typeof performance && (H = performance);
    } catch (t) {}
    var {
        userAgent: t = ""
    } = T.navigator || {};
    let e = T, g = Y, _ = W;
    var a = H;
    let U = !!e.document, f = !!g.documentElement && !!g.head && "function" == typeof g.addEventListener && "function" == typeof g.createElement, B = ~t.indexOf("MSIE") || ~t.indexOf("Trident/");
    var t = {
        classic: {
            fa: "solid",
            fas: "solid",
            "fa-solid": "solid",
            far: "regular",
            "fa-regular": "regular",
            fal: "light",
            "fa-light": "light",
            fat: "thin",
            "fa-thin": "thin",
            fab: "brands",
            "fa-brands": "brands"
        },
        duotone: {
            fa: "solid",
            fad: "solid",
            "fa-solid": "solid",
            "fa-duotone": "solid",
            fadr: "regular",
            "fa-regular": "regular",
            fadl: "light",
            "fa-light": "light",
            fadt: "thin",
            "fa-thin": "thin"
        },
        sharp: {
            fa: "solid",
            fass: "solid",
            "fa-solid": "solid",
            fasr: "regular",
            "fa-regular": "regular",
            fasl: "light",
            "fa-light": "light",
            fast: "thin",
            "fa-thin": "thin"
        },
        "sharp-duotone": {
            fa: "solid",
            fasds: "solid",
            "fa-solid": "solid",
            fasdr: "regular",
            "fa-regular": "regular",
            fasdl: "light",
            "fa-light": "light",
            fasdt: "thin",
            "fa-thin": "thin"
        }
    }, X = [ "fa-classic", "fa-duotone", "fa-sharp", "fa-sharp-duotone" ], l = "classic", u = "duotone", q = [ l, u, "sharp", "sharp-duotone" ], V = new Map([ [ "classic", {
        defaultShortPrefixId: "fas",
        defaultStyleId: "solid",
        styleIds: [ "solid", "regular", "light", "thin", "brands" ],
        futureStyleIds: [],
        defaultFontWeight: 900
    } ], [ "sharp", {
        defaultShortPrefixId: "fass",
        defaultStyleId: "solid",
        styleIds: [ "solid", "regular", "light", "thin" ],
        futureStyleIds: [],
        defaultFontWeight: 900
    } ], [ "duotone", {
        defaultShortPrefixId: "fad",
        defaultStyleId: "solid",
        styleIds: [ "solid", "regular", "light", "thin" ],
        futureStyleIds: [],
        defaultFontWeight: 900
    } ], [ "sharp-duotone", {
        defaultShortPrefixId: "fasds",
        defaultStyleId: "solid",
        styleIds: [ "solid", "regular", "light", "thin" ],
        futureStyleIds: [],
        defaultFontWeight: 900
    } ] ]), G = [ "fak", "fa-kit", "fakd", "fa-kit-duotone" ], r = {
        fak: "kit",
        "fa-kit": "kit"
    }, n = {
        fakd: "kit-duotone",
        "fa-kit-duotone": "kit-duotone"
    }, K = [ "fak", "fakd" ], J = {
        kit: "fak"
    }, i = {
        "kit-duotone": "fakd"
    }, o = {
        GROUP: "duotone-group",
        SWAP_OPACITY: "swap-opacity",
        PRIMARY: "primary",
        SECONDARY: "secondary"
    }, Q = [ "fak", "fa-kit", "fakd", "fa-kit-duotone" ], Z = {
        classic: {
            fab: "fa-brands",
            fad: "fa-duotone",
            fal: "fa-light",
            far: "fa-regular",
            fas: "fa-solid",
            fat: "fa-thin"
        },
        duotone: {
            fadr: "fa-regular",
            fadl: "fa-light",
            fadt: "fa-thin"
        },
        sharp: {
            fass: "fa-solid",
            fasr: "fa-regular",
            fasl: "fa-light",
            fast: "fa-thin"
        },
        "sharp-duotone": {
            fasds: "fa-solid",
            fasdr: "fa-regular",
            fasdl: "fa-light",
            fasdt: "fa-thin"
        }
    }, $ = [ "fa", "fas", "far", "fal", "fat", "fad", "fadr", "fadl", "fadt", "fab", "fass", "fasr", "fasl", "fast", "fasds", "fasdr", "fasdl", "fasdt", "fa-classic", "fa-duotone", "fa-sharp", "fa-sharp-duotone", "fa-solid", "fa-regular", "fa-light", "fa-thin", "fa-duotone", "fa-brands" ], s = (c = [ 1, 2, 3, 4, 5, 6, 7, 8, 9, 10 ]).concat([ 11, 12, 13, 14, 15, 16, 17, 18, 19, 20 ]), o = [ ...Object.keys({
        classic: [ "fas", "far", "fal", "fat", "fad" ],
        duotone: [ "fadr", "fadl", "fadt" ],
        sharp: [ "fass", "fasr", "fasl", "fast" ],
        "sharp-duotone": [ "fasds", "fasdr", "fasdl", "fasdt" ]
    }), "solid", "regular", "light", "thin", "duotone", "brands", "2xs", "xs", "sm", "lg", "xl", "2xl", "beat", "border", "fade", "beat-fade", "bounce", "flip-both", "flip-horizontal", "flip-vertical", "flip", "fw", "inverse", "layers-counter", "layers-text", "layers", "li", "pull-left", "pull-right", "pulse", "rotate-180", "rotate-270", "rotate-90", "rotate-by", "shake", "spin-pulse", "spin-reverse", "spin", "stack-1x", "stack-2x", "stack", "ul", o.GROUP, o.SWAP_OPACITY, o.PRIMARY, o.SECONDARY ].concat(c.map(t => "".concat(t, "x"))).concat(s.map(t => "w-".concat(t))), c = "___FONT_AWESOME___";
    let tt = 16, et = "svg-inline--fa", y = "data-fa-i2svg", at = "data-fa-pseudo-element", nt = "data-fa-pseudo-element-pending", rt = "data-prefix", it = "data-icon", ot = "fontawesome-i2svg", st = "async", lt = [ "HTML", "HEAD", "STYLE", "SCRIPT" ], ft = (() => {
        try {
            return !1;
        } catch (t) {
            return !1;
        }
    })();
    function d(t) {
        return new Proxy(t, {
            get(t, e) {
                return e in t ? t[e] : t[l];
            }
        });
    }
    (s = b({}, t))[l] = b(b(b(b({}, {
        "fa-duotone": "duotone"
    }), t[l]), r), n);
    let ct = d(s), ut = ((t = b({}, {
        classic: {
            solid: "fas",
            regular: "far",
            light: "fal",
            thin: "fat",
            brands: "fab"
        },
        duotone: {
            solid: "fad",
            regular: "fadr",
            light: "fadl",
            thin: "fadt"
        },
        sharp: {
            solid: "fass",
            regular: "fasr",
            light: "fasl",
            thin: "fast"
        },
        "sharp-duotone": {
            solid: "fasds",
            regular: "fasdr",
            light: "fasdl",
            thin: "fasdt"
        }
    }))[l] = b(b(b(b({}, {
        duotone: "fad"
    }), t[l]), J), i), d(t)), dt = ((r = b({}, Z))[l] = b(b({}, r[l]), {
        fak: "fa-kit"
    }), d(r)), mt = ((n = b({}, {
        classic: {
            "fa-brands": "fab",
            "fa-duotone": "fad",
            "fa-light": "fal",
            "fa-regular": "far",
            "fa-solid": "fas",
            "fa-thin": "fat"
        },
        duotone: {
            "fa-regular": "fadr",
            "fa-light": "fadl",
            "fa-thin": "fadt"
        },
        sharp: {
            "fa-solid": "fass",
            "fa-regular": "fasr",
            "fa-light": "fasl",
            "fa-thin": "fast"
        },
        "sharp-duotone": {
            "fa-solid": "fasds",
            "fa-regular": "fasdr",
            "fa-light": "fasdl",
            "fa-thin": "fasdt"
        }
    }))[l] = b(b({}, n[l]), {
        "fa-kit": "fak"
    }), d(n), /fa(s|r|l|t|d|dr|dl|dt|b|k|kd|ss|sr|sl|st|sds|sdr|sdl|sdt)?[\-\ ]/), ht = "fa-layers-text", pt = /Font ?Awesome ?([56 ]*)(Solid|Regular|Light|Thin|Duotone|Brands|Free|Pro|Sharp Duotone|Sharp|Kit)?.*/i, gt = (d(b({}, {
        classic: {
            900: "fas",
            400: "far",
            normal: "far",
            300: "fal",
            100: "fat"
        },
        duotone: {
            900: "fad",
            400: "fadr",
            300: "fadl",
            100: "fadt"
        },
        sharp: {
            900: "fass",
            400: "fasr",
            300: "fasl",
            100: "fast"
        },
        "sharp-duotone": {
            900: "fasds",
            400: "fasdr",
            300: "fasdl",
            100: "fasdt"
        }
    })), [ "class", "data-prefix", "data-icon", "data-fa-transform", "data-fa-mask" ]), vt = {
        GROUP: "duotone-group",
        SWAP_OPACITY: "swap-opacity",
        PRIMARY: "primary",
        SECONDARY: "secondary"
    }, bt = [ "kit", ...o ], m = e.FontAwesomeConfig || {}, h = (g && "function" == typeof g.querySelector && [ [ "data-family-prefix", "familyPrefix" ], [ "data-css-prefix", "cssPrefix" ], [ "data-family-default", "familyDefault" ], [ "data-style-default", "styleDefault" ], [ "data-replacement-class", "replacementClass" ], [ "data-auto-replace-svg", "autoReplaceSvg" ], [ "data-auto-add-css", "autoAddCss" ], [ "data-auto-a11y", "autoA11y" ], [ "data-search-pseudo-elements", "searchPseudoElements" ], [ "data-observe-mutations", "observeMutations" ], [ "data-mutate-approach", "mutateApproach" ], [ "data-keep-original-source", "keepOriginalSource" ], [ "data-measure-performance", "measurePerformance" ], [ "data-show-missing-icons", "showMissingIcons" ] ].forEach(t => {
        var [ e, a ] = t, e = "" === (t = (t => {
            var e = g.querySelector("script[" + t + "]");
            if (e) return e.getAttribute(t);
        })(e)) || "false" !== t && ("true" === t || t);
        null != e && (m[a] = e);
    }), s = {
        styleDefault: "solid",
        familyDefault: l,
        cssPrefix: "fa",
        replacementClass: et,
        autoReplaceSvg: !0,
        autoAddCss: !0,
        autoA11y: !0,
        searchPseudoElements: !1,
        observeMutations: !0,
        mutateApproach: "async",
        keepOriginalSource: !0,
        measurePerformance: !1,
        showMissingIcons: !0
    }, m.familyPrefix && (m.cssPrefix = m.familyPrefix), b(b({}, s), m)), x = (h.autoReplaceSvg || (h.observeMutations = !1), 
    {}), yt = (Object.keys(s).forEach(e => {
        Object.defineProperty(x, e, {
            enumerable: !0,
            set: function(t) {
                h[e] = t, yt.forEach(t => t(x));
            },
            get: function() {
                return h[e];
            }
        });
    }), Object.defineProperty(x, "familyPrefix", {
        enumerable: !0,
        set: function(t) {
            h.cssPrefix = t, yt.forEach(t => t(x));
        },
        get: function() {
            return h.cssPrefix;
        }
    }), e.FontAwesomeConfig = x, []), p = tt, v = {
        size: 16,
        x: 0,
        y: 0,
        rotate: 0,
        flipX: !1,
        flipY: !1
    }, xt = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
    function k() {
        let t = 12, e = "";
        for (;0 < t--; ) e += xt[62 * Math.random() | 0];
        return e;
    }
    function w(t) {
        var e = [];
        for (let a = (t || []).length >>> 0; a--; ) e[a] = t[a];
        return e;
    }
    function kt(t) {
        return t.classList ? w(t.classList) : (t.getAttribute("class") || "").split(" ").filter(t => t);
    }
    function wt(t) {
        return "".concat(t).replace(/&/g, "&amp;").replace(/"/g, "&quot;").replace(/'/g, "&#39;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
    }
    function A(a) {
        return Object.keys(a || {}).reduce((t, e) => t + "".concat(e, ": ").concat(a[e].trim(), ";"), "");
    }
    function At(t) {
        return t.size !== v.size || t.x !== v.x || t.y !== v.y || t.rotate !== v.rotate || t.flipX || t.flipY;
    }
    function Pt() {
        var t, e, a = et, n = x.cssPrefix, r = x.replacementClass;
        let i = ':root, :host {\n  --fa-font-solid: normal 900 1em/1 "Font Awesome 6 Free";\n  --fa-font-regular: normal 400 1em/1 "Font Awesome 6 Free";\n  --fa-font-light: normal 300 1em/1 "Font Awesome 6 Pro";\n  --fa-font-thin: normal 100 1em/1 "Font Awesome 6 Pro";\n  --fa-font-duotone: normal 900 1em/1 "Font Awesome 6 Duotone";\n  --fa-font-duotone-regular: normal 400 1em/1 "Font Awesome 6 Duotone";\n  --fa-font-duotone-light: normal 300 1em/1 "Font Awesome 6 Duotone";\n  --fa-font-duotone-thin: normal 100 1em/1 "Font Awesome 6 Duotone";\n  --fa-font-brands: normal 400 1em/1 "Font Awesome 6 Brands";\n  --fa-font-sharp-solid: normal 900 1em/1 "Font Awesome 6 Sharp";\n  --fa-font-sharp-regular: normal 400 1em/1 "Font Awesome 6 Sharp";\n  --fa-font-sharp-light: normal 300 1em/1 "Font Awesome 6 Sharp";\n  --fa-font-sharp-thin: normal 100 1em/1 "Font Awesome 6 Sharp";\n  --fa-font-sharp-duotone-solid: normal 900 1em/1 "Font Awesome 6 Sharp Duotone";\n  --fa-font-sharp-duotone-regular: normal 400 1em/1 "Font Awesome 6 Sharp Duotone";\n  --fa-font-sharp-duotone-light: normal 300 1em/1 "Font Awesome 6 Sharp Duotone";\n  --fa-font-sharp-duotone-thin: normal 100 1em/1 "Font Awesome 6 Sharp Duotone";\n}\n\nsvg:not(:root).svg-inline--fa, svg:not(:host).svg-inline--fa {\n  overflow: visible;\n  box-sizing: content-box;\n}\n\n.svg-inline--fa {\n  display: var(--fa-display, inline-block);\n  height: 1em;\n  overflow: visible;\n  vertical-align: -0.125em;\n}\n.svg-inline--fa.fa-2xs {\n  vertical-align: 0.1em;\n}\n.svg-inline--fa.fa-xs {\n  vertical-align: 0em;\n}\n.svg-inline--fa.fa-sm {\n  vertical-align: -0.0714285705em;\n}\n.svg-inline--fa.fa-lg {\n  vertical-align: -0.2em;\n}\n.svg-inline--fa.fa-xl {\n  vertical-align: -0.25em;\n}\n.svg-inline--fa.fa-2xl {\n  vertical-align: -0.3125em;\n}\n.svg-inline--fa.fa-pull-left {\n  margin-right: var(--fa-pull-margin, 0.3em);\n  width: auto;\n}\n.svg-inline--fa.fa-pull-right {\n  margin-left: var(--fa-pull-margin, 0.3em);\n  width: auto;\n}\n.svg-inline--fa.fa-li {\n  width: var(--fa-li-width, 2em);\n  top: 0.25em;\n}\n.svg-inline--fa.fa-fw {\n  width: var(--fa-fw-width, 1.25em);\n}\n\n.fa-layers svg.svg-inline--fa {\n  bottom: 0;\n  left: 0;\n  margin: auto;\n  position: absolute;\n  right: 0;\n  top: 0;\n}\n\n.fa-layers-counter, .fa-layers-text {\n  display: inline-block;\n  position: absolute;\n  text-align: center;\n}\n\n.fa-layers {\n  display: inline-block;\n  height: 1em;\n  position: relative;\n  text-align: center;\n  vertical-align: -0.125em;\n  width: 1em;\n}\n.fa-layers svg.svg-inline--fa {\n  transform-origin: center center;\n}\n\n.fa-layers-text {\n  left: 50%;\n  top: 50%;\n  transform: translate(-50%, -50%);\n  transform-origin: center center;\n}\n\n.fa-layers-counter {\n  background-color: var(--fa-counter-background-color, #ff253a);\n  border-radius: var(--fa-counter-border-radius, 1em);\n  box-sizing: border-box;\n  color: var(--fa-inverse, #fff);\n  line-height: var(--fa-counter-line-height, 1);\n  max-width: var(--fa-counter-max-width, 5em);\n  min-width: var(--fa-counter-min-width, 1.5em);\n  overflow: hidden;\n  padding: var(--fa-counter-padding, 0.25em 0.5em);\n  right: var(--fa-right, 0);\n  text-overflow: ellipsis;\n  top: var(--fa-top, 0);\n  transform: scale(var(--fa-counter-scale, 0.25));\n  transform-origin: top right;\n}\n\n.fa-layers-bottom-right {\n  bottom: var(--fa-bottom, 0);\n  right: var(--fa-right, 0);\n  top: auto;\n  transform: scale(var(--fa-layers-scale, 0.25));\n  transform-origin: bottom right;\n}\n\n.fa-layers-bottom-left {\n  bottom: var(--fa-bottom, 0);\n  left: var(--fa-left, 0);\n  right: auto;\n  top: auto;\n  transform: scale(var(--fa-layers-scale, 0.25));\n  transform-origin: bottom left;\n}\n\n.fa-layers-top-right {\n  top: var(--fa-top, 0);\n  right: var(--fa-right, 0);\n  transform: scale(var(--fa-layers-scale, 0.25));\n  transform-origin: top right;\n}\n\n.fa-layers-top-left {\n  left: var(--fa-left, 0);\n  right: auto;\n  top: var(--fa-top, 0);\n  transform: scale(var(--fa-layers-scale, 0.25));\n  transform-origin: top left;\n}\n\n.fa-1x {\n  font-size: 1em;\n}\n\n.fa-2x {\n  font-size: 2em;\n}\n\n.fa-3x {\n  font-size: 3em;\n}\n\n.fa-4x {\n  font-size: 4em;\n}\n\n.fa-5x {\n  font-size: 5em;\n}\n\n.fa-6x {\n  font-size: 6em;\n}\n\n.fa-7x {\n  font-size: 7em;\n}\n\n.fa-8x {\n  font-size: 8em;\n}\n\n.fa-9x {\n  font-size: 9em;\n}\n\n.fa-10x {\n  font-size: 10em;\n}\n\n.fa-2xs {\n  font-size: 0.625em;\n  line-height: 0.1em;\n  vertical-align: 0.225em;\n}\n\n.fa-xs {\n  font-size: 0.75em;\n  line-height: 0.0833333337em;\n  vertical-align: 0.125em;\n}\n\n.fa-sm {\n  font-size: 0.875em;\n  line-height: 0.0714285718em;\n  vertical-align: 0.0535714295em;\n}\n\n.fa-lg {\n  font-size: 1.25em;\n  line-height: 0.05em;\n  vertical-align: -0.075em;\n}\n\n.fa-xl {\n  font-size: 1.5em;\n  line-height: 0.0416666682em;\n  vertical-align: -0.125em;\n}\n\n.fa-2xl {\n  font-size: 2em;\n  line-height: 0.03125em;\n  vertical-align: -0.1875em;\n}\n\n.fa-fw {\n  text-align: center;\n  width: 1.25em;\n}\n\n.fa-ul {\n  list-style-type: none;\n  margin-left: var(--fa-li-margin, 2.5em);\n  padding-left: 0;\n}\n.fa-ul > li {\n  position: relative;\n}\n\n.fa-li {\n  left: calc(-1 * var(--fa-li-width, 2em));\n  position: absolute;\n  text-align: center;\n  width: var(--fa-li-width, 2em);\n  line-height: inherit;\n}\n\n.fa-border {\n  border-color: var(--fa-border-color, #eee);\n  border-radius: var(--fa-border-radius, 0.1em);\n  border-style: var(--fa-border-style, solid);\n  border-width: var(--fa-border-width, 0.08em);\n  padding: var(--fa-border-padding, 0.2em 0.25em 0.15em);\n}\n\n.fa-pull-left {\n  float: left;\n  margin-right: var(--fa-pull-margin, 0.3em);\n}\n\n.fa-pull-right {\n  float: right;\n  margin-left: var(--fa-pull-margin, 0.3em);\n}\n\n.fa-beat {\n  animation-name: fa-beat;\n  animation-delay: var(--fa-animation-delay, 0s);\n  animation-direction: var(--fa-animation-direction, normal);\n  animation-duration: var(--fa-animation-duration, 1s);\n  animation-iteration-count: var(--fa-animation-iteration-count, infinite);\n  animation-timing-function: var(--fa-animation-timing, ease-in-out);\n}\n\n.fa-bounce {\n  animation-name: fa-bounce;\n  animation-delay: var(--fa-animation-delay, 0s);\n  animation-direction: var(--fa-animation-direction, normal);\n  animation-duration: var(--fa-animation-duration, 1s);\n  animation-iteration-count: var(--fa-animation-iteration-count, infinite);\n  animation-timing-function: var(--fa-animation-timing, cubic-bezier(0.28, 0.84, 0.42, 1));\n}\n\n.fa-fade {\n  animation-name: fa-fade;\n  animation-delay: var(--fa-animation-delay, 0s);\n  animation-direction: var(--fa-animation-direction, normal);\n  animation-duration: var(--fa-animation-duration, 1s);\n  animation-iteration-count: var(--fa-animation-iteration-count, infinite);\n  animation-timing-function: var(--fa-animation-timing, cubic-bezier(0.4, 0, 0.6, 1));\n}\n\n.fa-beat-fade {\n  animation-name: fa-beat-fade;\n  animation-delay: var(--fa-animation-delay, 0s);\n  animation-direction: var(--fa-animation-direction, normal);\n  animation-duration: var(--fa-animation-duration, 1s);\n  animation-iteration-count: var(--fa-animation-iteration-count, infinite);\n  animation-timing-function: var(--fa-animation-timing, cubic-bezier(0.4, 0, 0.6, 1));\n}\n\n.fa-flip {\n  animation-name: fa-flip;\n  animation-delay: var(--fa-animation-delay, 0s);\n  animation-direction: var(--fa-animation-direction, normal);\n  animation-duration: var(--fa-animation-duration, 1s);\n  animation-iteration-count: var(--fa-animation-iteration-count, infinite);\n  animation-timing-function: var(--fa-animation-timing, ease-in-out);\n}\n\n.fa-shake {\n  animation-name: fa-shake;\n  animation-delay: var(--fa-animation-delay, 0s);\n  animation-direction: var(--fa-animation-direction, normal);\n  animation-duration: var(--fa-animation-duration, 1s);\n  animation-iteration-count: var(--fa-animation-iteration-count, infinite);\n  animation-timing-function: var(--fa-animation-timing, linear);\n}\n\n.fa-spin {\n  animation-name: fa-spin;\n  animation-delay: var(--fa-animation-delay, 0s);\n  animation-direction: var(--fa-animation-direction, normal);\n  animation-duration: var(--fa-animation-duration, 2s);\n  animation-iteration-count: var(--fa-animation-iteration-count, infinite);\n  animation-timing-function: var(--fa-animation-timing, linear);\n}\n\n.fa-spin-reverse {\n  --fa-animation-direction: reverse;\n}\n\n.fa-pulse,\n.fa-spin-pulse {\n  animation-name: fa-spin;\n  animation-direction: var(--fa-animation-direction, normal);\n  animation-duration: var(--fa-animation-duration, 1s);\n  animation-iteration-count: var(--fa-animation-iteration-count, infinite);\n  animation-timing-function: var(--fa-animation-timing, steps(8));\n}\n\n@media (prefers-reduced-motion: reduce) {\n  .fa-beat,\n.fa-bounce,\n.fa-fade,\n.fa-beat-fade,\n.fa-flip,\n.fa-pulse,\n.fa-shake,\n.fa-spin,\n.fa-spin-pulse {\n    animation-delay: -1ms;\n    animation-duration: 1ms;\n    animation-iteration-count: 1;\n    transition-delay: 0s;\n    transition-duration: 0s;\n  }\n}\n@keyframes fa-beat {\n  0%, 90% {\n    transform: scale(1);\n  }\n  45% {\n    transform: scale(var(--fa-beat-scale, 1.25));\n  }\n}\n@keyframes fa-bounce {\n  0% {\n    transform: scale(1, 1) translateY(0);\n  }\n  10% {\n    transform: scale(var(--fa-bounce-start-scale-x, 1.1), var(--fa-bounce-start-scale-y, 0.9)) translateY(0);\n  }\n  30% {\n    transform: scale(var(--fa-bounce-jump-scale-x, 0.9), var(--fa-bounce-jump-scale-y, 1.1)) translateY(var(--fa-bounce-height, -0.5em));\n  }\n  50% {\n    transform: scale(var(--fa-bounce-land-scale-x, 1.05), var(--fa-bounce-land-scale-y, 0.95)) translateY(0);\n  }\n  57% {\n    transform: scale(1, 1) translateY(var(--fa-bounce-rebound, -0.125em));\n  }\n  64% {\n    transform: scale(1, 1) translateY(0);\n  }\n  100% {\n    transform: scale(1, 1) translateY(0);\n  }\n}\n@keyframes fa-fade {\n  50% {\n    opacity: var(--fa-fade-opacity, 0.4);\n  }\n}\n@keyframes fa-beat-fade {\n  0%, 100% {\n    opacity: var(--fa-beat-fade-opacity, 0.4);\n    transform: scale(1);\n  }\n  50% {\n    opacity: 1;\n    transform: scale(var(--fa-beat-fade-scale, 1.125));\n  }\n}\n@keyframes fa-flip {\n  50% {\n    transform: rotate3d(var(--fa-flip-x, 0), var(--fa-flip-y, 1), var(--fa-flip-z, 0), var(--fa-flip-angle, -180deg));\n  }\n}\n@keyframes fa-shake {\n  0% {\n    transform: rotate(-15deg);\n  }\n  4% {\n    transform: rotate(15deg);\n  }\n  8%, 24% {\n    transform: rotate(-18deg);\n  }\n  12%, 28% {\n    transform: rotate(18deg);\n  }\n  16% {\n    transform: rotate(-22deg);\n  }\n  20% {\n    transform: rotate(22deg);\n  }\n  32% {\n    transform: rotate(-12deg);\n  }\n  36% {\n    transform: rotate(12deg);\n  }\n  40%, 100% {\n    transform: rotate(0deg);\n  }\n}\n@keyframes fa-spin {\n  0% {\n    transform: rotate(0deg);\n  }\n  100% {\n    transform: rotate(360deg);\n  }\n}\n.fa-rotate-90 {\n  transform: rotate(90deg);\n}\n\n.fa-rotate-180 {\n  transform: rotate(180deg);\n}\n\n.fa-rotate-270 {\n  transform: rotate(270deg);\n}\n\n.fa-flip-horizontal {\n  transform: scale(-1, 1);\n}\n\n.fa-flip-vertical {\n  transform: scale(1, -1);\n}\n\n.fa-flip-both,\n.fa-flip-horizontal.fa-flip-vertical {\n  transform: scale(-1, -1);\n}\n\n.fa-rotate-by {\n  transform: rotate(var(--fa-rotate-angle, 0));\n}\n\n.fa-stack {\n  display: inline-block;\n  vertical-align: middle;\n  height: 2em;\n  position: relative;\n  width: 2.5em;\n}\n\n.fa-stack-1x,\n.fa-stack-2x {\n  bottom: 0;\n  left: 0;\n  margin: auto;\n  position: absolute;\n  right: 0;\n  top: 0;\n  z-index: var(--fa-stack-z-index, auto);\n}\n\n.svg-inline--fa.fa-stack-1x {\n  height: 1em;\n  width: 1.25em;\n}\n.svg-inline--fa.fa-stack-2x {\n  height: 2em;\n  width: 2.5em;\n}\n\n.fa-inverse {\n  color: var(--fa-inverse, #fff);\n}\n\n.sr-only,\n.fa-sr-only {\n  position: absolute;\n  width: 1px;\n  height: 1px;\n  padding: 0;\n  margin: -1px;\n  overflow: hidden;\n  clip: rect(0, 0, 0, 0);\n  white-space: nowrap;\n  border-width: 0;\n}\n\n.sr-only-focusable:not(:focus),\n.fa-sr-only-focusable:not(:focus) {\n  position: absolute;\n  width: 1px;\n  height: 1px;\n  padding: 0;\n  margin: -1px;\n  overflow: hidden;\n  clip: rect(0, 0, 0, 0);\n  white-space: nowrap;\n  border-width: 0;\n}\n\n.svg-inline--fa .fa-primary {\n  fill: var(--fa-primary-color, currentColor);\n  opacity: var(--fa-primary-opacity, 1);\n}\n\n.svg-inline--fa .fa-secondary {\n  fill: var(--fa-secondary-color, currentColor);\n  opacity: var(--fa-secondary-opacity, 0.4);\n}\n\n.svg-inline--fa.fa-swap-opacity .fa-primary {\n  opacity: var(--fa-secondary-opacity, 0.4);\n}\n\n.svg-inline--fa.fa-swap-opacity .fa-secondary {\n  opacity: var(--fa-primary-opacity, 1);\n}\n\n.svg-inline--fa mask .fa-primary,\n.svg-inline--fa mask .fa-secondary {\n  fill: black;\n}';
        return "fa" === n && r === a || (t = new RegExp("\\.".concat("fa", "\\-"), "g"), 
        e = new RegExp("\\--".concat("fa", "\\-"), "g"), a = new RegExp("\\.".concat(a), "g"), 
        i = i.replace(t, ".".concat(n, "-")).replace(e, "--".concat(n, "-")).replace(a, ".".concat(r))), 
        i;
    }
    let Ot = !1;
    function Nt() {
        if (x.autoAddCss && !Ot) {
            var a = Pt();
            if (a && f) {
                var n = g.createElement("style"), r = (n.setAttribute("type", "text/css"), 
                n.innerHTML = a, g.head.childNodes);
                let t = null;
                for (let e = r.length - 1; -1 < e; e--) {
                    var i = r[e], o = (i.tagName || "").toUpperCase();
                    -1 < [ "STYLE", "LINK" ].indexOf(o) && (t = i);
                }
                g.head.insertBefore(n, t);
            }
            Ot = !0;
        }
    }
    var J = {
        mixout() {
            return {
                dom: {
                    css: Pt,
                    insertCss: Nt
                }
            };
        },
        hooks() {
            return {
                beforeDOMElementCreation() {
                    Nt();
                },
                beforeI2svg() {
                    Nt();
                }
            };
        }
    }, P = ((i = e || {})[c] || (i[c] = {}), i[c].styles || (i[c].styles = {}), 
    i[c].hooks || (i[c].hooks = {}), i[c].shims || (i[c].shims = []), i[c]);
    function St() {
        g.removeEventListener("DOMContentLoaded", St), Et = 1, Ct.map(t => t());
    }
    let Ct = [], Et = !1;
    function Mt(t) {
        f && (Et ? setTimeout(t, 0) : Ct.push(t));
    }
    function O(t) {
        var a, {
            tag: e,
            attributes: n = {},
            children: r = []
        } = t;
        return "string" == typeof t ? wt(t) : "<".concat(e, " ").concat((a = n, 
        Object.keys(a || {}).reduce((t, e) => t + "".concat(e, '="').concat(wt(a[e]), '" '), "").trim()), ">").concat(r.map(O).join(""), "</").concat(e, ">");
    }
    function jt(t, e, a) {
        if (t && t[e] && t[e][a]) return {
            prefix: e,
            iconName: a,
            icon: t[e][a]
        };
    }
    function zt(t, e, a, n) {
        for (var r, i, o = Object.keys(t), s = o.length, l = void 0 !== n ? It(e, n) : e, f = void 0 === a ? (r = 1, 
        t[o[0]]) : (r = 0, a); r < s; r++) f = l(f, t[i = o[r]], i, t);
        return f;
    }
    f && !(Et = (g.documentElement.doScroll ? /^loaded|^c/ : /^loaded|^i|^c/).test(g.readyState)) && g.addEventListener("DOMContentLoaded", St);
    var It = function(r, i) {
        return function(t, e, a, n) {
            return r.call(i, t, e, a, n);
        };
    };
    function Ft(t) {
        var e = (t => {
            var e = [];
            let a = 0;
            for (var n = t.length; a < n; ) {
                var r, i = t.charCodeAt(a++);
                55296 <= i && i <= 56319 && a < n ? 56320 == (64512 & (r = t.charCodeAt(a++))) ? e.push(((1023 & i) << 10) + (1023 & r) + 65536) : (e.push(i), 
                a--) : e.push(i);
            }
            return e;
        })(t);
        return 1 === e.length ? e[0].toString(16) : null;
    }
    function Dt(n) {
        return Object.keys(n).reduce((t, e) => {
            var a = n[e];
            return !!a.icon ? t[a.iconName] = a.icon : t[e] = a, t;
        }, {});
    }
    function Rt(t, e, a) {
        var {
            skipHooks: n = !1
        } = 2 < arguments.length && void 0 !== a ? a : {}, r = Dt(e);
        "function" != typeof P.hooks.addPack || n ? P.styles[t] = b(b({}, P.styles[t] || {}), r) : P.hooks.addPack(t, Dt(e)), 
        "fas" === t && Rt("fa", e);
    }
    let {
        styles: N,
        shims: Lt
    } = P, Tt = Object.keys(dt), Yt = Tt.reduce((t, e) => (t[e] = Object.keys(dt[e]), 
    t), {}), S = null, Wt = {}, Ht = {}, _t = {}, Ut = {}, Bt = {};
    function Xt(t, e) {
        var a = e.split("-"), n = a[0], a = a.slice(1).join("-");
        return n !== t || "" === a || (e = a, ~bt.indexOf(e)) ? null : a;
    }
    let C = () => {
        var t = n => zt(N, (t, e, a) => (t[a] = zt(e, n, {}), t), {});
        Wt = t((e, t, a) => (t[3] && (e[t[3]] = a), t[2] && t[2].filter(t => "number" == typeof t).forEach(t => {
            e[t.toString(16)] = a;
        }), e)), Ht = t((e, t, a) => (e[a] = a, t[2] && t[2].filter(t => "string" == typeof t).forEach(t => {
            e[t] = a;
        }), e)), Bt = t((e, t, a) => {
            var n = t[2];
            return e[a] = a, n.forEach(t => {
                e[t] = a;
            }), e;
        });
        let i = "far" in N || x.autoFetchSvg;
        t = zt(Lt, (t, e) => {
            var a = e[0];
            let n = e[1];
            var r = e[2];
            return "far" !== n || i || (n = "fas"), "string" == typeof a && (t.names[a] = {
                prefix: n,
                iconName: r
            }), "number" == typeof a && (t.unicodes[a.toString(16)] = {
                prefix: n,
                iconName: r
            }), t;
        }, {
            names: {},
            unicodes: {}
        });
        _t = t.names, Ut = t.unicodes, S = Kt(x.styleDefault, {
            family: x.familyDefault
        });
    };
    function qt(t, e) {
        return (Wt[t] || {})[e];
    }
    function E(t, e) {
        return (Bt[t] || {})[e];
    }
    function Vt(t) {
        return _t[t] || {
            prefix: null,
            iconName: null
        };
    }
    ta = t => {
        S = Kt(t.styleDefault, {
            family: x.familyDefault
        });
    }, yt.push(ta), C();
    let Gt = () => ({
        prefix: null,
        iconName: null,
        rest: []
    });
    function Kt(t, e) {
        var {
            family: a = l
        } = 1 < arguments.length && void 0 !== e ? e : {}, n = ct[a][t];
        return a !== u || t ? (a = ut[a][t] || ut[a][n], n = t in P.styles ? t : null, 
        a || n || null) : "fad";
    }
    function Jt(t) {
        return t.sort().filter((t, e, a) => a.indexOf(t) === e);
    }
    function Qt(t, e) {
        var {
            skipLookups: a = !1
        } = 1 < arguments.length && void 0 !== e ? e : {};
        let n = null, r = $.concat(Q);
        var i = Jt(t.filter(t => r.includes(t))), o = Jt(t.filter(t => !$.includes(t))), [ s = null ] = i.filter(t => (n = t, 
        !X.includes(t))), i = (t => {
            let a = l, n = Tt.reduce((t, e) => (t[e] = "".concat(x.cssPrefix, "-").concat(e), 
            t), {});
            return q.forEach(e => {
                (t.includes(n[e]) || t.some(t => Yt[e].includes(t))) && (a = e);
            }), a;
        })(i), o = b(b({}, (t => {
            let a = [], n = null;
            return t.forEach(t => {
                var e = Xt(x.cssPrefix, t);
                e ? n = e : t && a.push(t);
            }), {
                iconName: n,
                rest: a
            };
        })(o)), {}, {
            prefix: Kt(s, {
                family: i
            })
        });
        return b(b(b({}, o), (t => {
            var {
                values: e,
                family: a,
                canonical: n,
                givenPrefix: r = "",
                styles: i = {},
                config: o = {}
            } = t, s = a === u, l = e.includes("fa-duotone") || e.includes("fad"), f = "duotone" === o.familyDefault, c = "fad" === n.prefix || "fa-duotone" === n.prefix;
            return !s && (l || f || c) && (n.prefix = "fad"), (e.includes("fa-brands") || e.includes("fab")) && (n.prefix = "fab"), 
            !n.prefix && Zt.includes(a) && (Object.keys(i).find(t => $t.includes(t)) || o.autoFetchSvg) && (s = V.get(a).defaultShortPrefixId, 
            n.prefix = s, n.iconName = E(n.prefix, n.iconName) || n.iconName), "fa" !== n.prefix && "fa" !== r || (n.prefix = S || "fas"), 
            n;
        })({
            values: t,
            family: i,
            styles: N,
            config: x,
            canonical: o,
            givenPrefix: n
        })), ((t, e, a) => {
            let {
                prefix: n,
                iconName: r
            } = a;
            var i, o;
            return !t && n && r && (i = "fa" === e ? Vt(r) : {}, o = E(n, r), r = i.iconName || o || r, 
            "far" !== (n = i.prefix || n) || N.far || !N.fas || x.autoFetchSvg || (n = "fas")), 
            {
                prefix: n,
                iconName: r
            };
        })(a, n, o));
    }
    let Zt = q.filter(t => t !== l || t !== u), $t = Object.keys(Z).filter(t => t !== l).map(t => Object.keys(Z[t])).flat(), te = [], M = {}, j = {}, ee = Object.keys(j);
    function ae(t, e) {
        for (var a = arguments.length, n = new Array(2 < a ? a - 2 : 0), r = 2; r < a; r++) n[r - 2] = arguments[r];
        return (M[t] || []).forEach(t => {
            e = t.apply(null, [ e, ...n ]);
        }), e;
    }
    function z(t) {
        for (var e = arguments.length, a = new Array(1 < e ? e - 1 : 0), n = 1; n < e; n++) a[n - 1] = arguments[n];
        (M[t] || []).forEach(t => {
            t.apply(null, a);
        });
    }
    function I(t) {
        var e = t, a = Array.prototype.slice.call(arguments, 1);
        return j[e] ? j[e].apply(null, a) : void 0;
    }
    function ne(t) {
        "fa" === t.prefix && (t.prefix = "fas");
        var e = t.iconName, a = t.prefix || S;
        if (e) return e = E(a, e) || e, jt(re.definitions, a, e) || jt(P.styles, a, e);
    }
    let re = new class {
        constructor() {
            this.definitions = {};
        }
        add() {
            for (var t = arguments.length, e = new Array(t), a = 0; a < t; a++) e[a] = arguments[a];
            let n = e.reduce(this._pullDefinitions, {});
            Object.keys(n).forEach(t => {
                this.definitions[t] = b(b({}, this.definitions[t] || {}), n[t]), 
                Rt(t, n[t]);
                var e = dt[l][t];
                e && Rt(e, n[t]), C();
            });
        }
        reset() {
            this.definitions = {};
        }
        _pullDefinitions(i, t) {
            let o = t.prefix && t.iconName && t.icon ? {
                0: t
            } : t;
            return Object.keys(o).map(t => {
                let {
                    prefix: e,
                    iconName: a,
                    icon: n
                } = o[t];
                var r = n[2];
                i[e] || (i[e] = {}), 0 < r.length && r.forEach(t => {
                    "string" == typeof t && (i[e][t] = n);
                }), i[e][a] = n;
            }), i;
        }
    }(), ie = {
        noAuto: () => {
            x.autoReplaceSvg = !1, x.observeMutations = !1, z("noAuto");
        },
        config: x,
        dom: {
            i2svg: function() {
                var t = 0 < arguments.length && void 0 !== arguments[0] ? arguments[0] : {};
                return f ? (z("beforeI2svg", t), I("pseudoElements2svg", t), I("i2svg", t)) : Promise.reject(new Error("Operation requires a DOM of some kind."));
            },
            watch: function() {
                let t = 0 < arguments.length && void 0 !== arguments[0] ? arguments[0] : {}, e = t.autoReplaceSvgRoot;
                !1 === x.autoReplaceSvg && (x.autoReplaceSvg = !0), x.observeMutations = !0, 
                Mt(() => {
                    F({
                        autoReplaceSvgRoot: e
                    }), z("watch", t);
                });
            }
        },
        parse: {
            icon: t => {
                var e, a;
                return null === t ? null : "object" == typeof t && t.prefix && t.iconName ? {
                    prefix: t.prefix,
                    iconName: E(t.prefix, t.iconName) || t.iconName
                } : Array.isArray(t) && 2 === t.length ? (e = 0 === t[1].indexOf("fa-") ? t[1].slice(3) : t[1], 
                {
                    prefix: a = Kt(t[0]),
                    iconName: E(a, e) || e
                }) : "string" == typeof t && (-1 < t.indexOf("".concat(x.cssPrefix, "-")) || t.match(mt)) ? {
                    prefix: (a = Qt(t.split(" "), {
                        skipLookups: !0
                    })).prefix || S,
                    iconName: E(a.prefix, a.iconName) || a.iconName
                } : "string" == typeof t ? {
                    prefix: S,
                    iconName: E(S, t) || t
                } : void 0;
            }
        },
        library: re,
        findIconDefinition: ne,
        toHtml: O
    }, F = function() {
        var t = 0 < arguments.length && void 0 !== arguments[0] ? arguments[0] : {}, {
            autoReplaceSvgRoot: t = g
        } = t;
        (0 < Object.keys(P.styles).length || x.autoFetchSvg) && f && x.autoReplaceSvg && ie.dom.i2svg({
            node: t
        });
    };
    function oe(e, t) {
        return Object.defineProperty(e, "abstract", {
            get: t
        }), Object.defineProperty(e, "html", {
            get: function() {
                return e.abstract.map(t => O(t));
            }
        }), Object.defineProperty(e, "node", {
            get: function() {
                var t;
                if (f) return (t = g.createElement("div")).innerHTML = e.html, t.children;
            }
        }), e;
    }
    function se(t) {
        let {
            icons: {
                main: e,
                mask: a
            },
            prefix: n,
            iconName: r,
            transform: i,
            symbol: o,
            title: s,
            maskId: l,
            titleId: f,
            extra: c,
            watchable: u = !1
        } = t;
        var d, m, {
            width: h,
            height: p
        } = a.found ? a : e, g = K.includes(n), v = [ x.replacementClass, r ? "".concat(x.cssPrefix, "-").concat(r) : "" ].filter(t => -1 === c.classes.indexOf(t)).filter(t => "" !== t || !!t).concat(c.classes).join(" "), v = {
            children: [],
            attributes: b(b({}, c.attributes), {}, {
                "data-prefix": n,
                "data-icon": r,
                class: v,
                role: c.attributes.role || "img",
                xmlns: "http://www.w3.org/2000/svg",
                viewBox: "0 0 ".concat(h, " ").concat(p)
            })
        }, g = g && !~c.classes.indexOf("fa-fw") ? {
            width: "".concat(h / p * 16 * .0625, "em")
        } : {}, h = (u && (v.attributes[y] = ""), s && (v.children.push({
            tag: "title",
            attributes: {
                id: v.attributes["aria-labelledby"] || "title-".concat(f || k())
            },
            children: [ s ]
        }), delete v.attributes.title), b(b({}, v), {}, {
            prefix: n,
            iconName: r,
            main: e,
            mask: a,
            maskId: l,
            transform: i,
            symbol: o,
            styles: b(b({}, g), c.styles)
        })), {
            children: p,
            attributes: v
        } = a.found && e.found ? I("generateAbstractMask", h) || {
            children: [],
            attributes: {}
        } : I("generateAbstractIcon", h) || {
            children: [],
            attributes: {}
        };
        return h.children = p, h.attributes = v, o ? ({
            prefix: g,
            iconName: p,
            children: v,
            attributes: m,
            symbol: d
        } = h, g = !0 === d ? "".concat(g, "-").concat(x.cssPrefix, "-").concat(p) : d, 
        [ {
            tag: "svg",
            attributes: {
                style: "display: none;"
            },
            children: [ {
                tag: "symbol",
                attributes: b(b({}, m), {}, {
                    id: g
                }),
                children: v
            } ]
        } ]) : ({
            children: p,
            main: d,
            mask: m,
            attributes: g,
            styles: v,
            transform: h
        } = h, At(h) && d.found && !m.found && ({
            width: m,
            height: d
        } = d, m = {
            x: m / d / 2,
            y: .5
        }, g.style = A(b(b({}, v), {}, {
            "transform-origin": "".concat(m.x + h.x / 16, "em ").concat(m.y + h.y / 16, "em")
        }))), [ {
            tag: "svg",
            attributes: g,
            children: p
        } ]);
    }
    function le(t) {
        var {
            content: e,
            width: a,
            height: n,
            transform: r,
            title: i,
            extra: o,
            watchable: s = !1
        } = t, l = b(b(b({}, o.attributes), i ? {
            title: i
        } : {}), {}, {
            class: o.classes.join(" ")
        }), s = (s && (l[y] = ""), b({}, o.styles)), o = (At(r) && (s.transform = (t => {
            var {
                transform: e,
                width: a = tt,
                height: n = tt,
                startCentered: r = !1
            } = t;
            let i = "";
            return r && B ? i += "translate(".concat(e.x / p - a / 2, "em, ").concat(e.y / p - n / 2, "em) ") : i += r ? "translate(calc(-50% + ".concat(e.x / p, "em), calc(-50% + ").concat(e.y / p, "em)) ") : "translate(".concat(e.x / p, "em, ").concat(e.y / p, "em) "), 
            i = (i += "scale(".concat(e.size / p * (e.flipX ? -1 : 1), ", ").concat(e.size / p * (e.flipY ? -1 : 1), ") ")) + "rotate(".concat(e.rotate, "deg) ");
        })({
            transform: r,
            startCentered: !0,
            width: a,
            height: n
        }), s["-webkit-transform"] = s.transform), A(s)), r = (0 < o.length && (l.style = o), 
        []);
        return r.push({
            tag: "span",
            attributes: l,
            children: [ e ]
        }), i && r.push({
            tag: "span",
            attributes: {
                class: "sr-only"
            },
            children: [ i ]
        }), r;
    }
    let fe = P.styles;
    function ce(t) {
        var e = t[0], a = t[1], [ n ] = t.slice(4);
        let r = null;
        return {
            found: !0,
            width: e,
            height: a,
            icon: r = Array.isArray(n) ? {
                tag: "g",
                attributes: {
                    class: "".concat(x.cssPrefix, "-").concat(vt.GROUP)
                },
                children: [ {
                    tag: "path",
                    attributes: {
                        class: "".concat(x.cssPrefix, "-").concat(vt.SECONDARY),
                        fill: "currentColor",
                        d: n[0]
                    }
                }, {
                    tag: "path",
                    attributes: {
                        class: "".concat(x.cssPrefix, "-").concat(vt.PRIMARY),
                        fill: "currentColor",
                        d: n[1]
                    }
                } ]
            } : {
                tag: "path",
                attributes: {
                    fill: "currentColor",
                    d: n
                }
            }
        };
    }
    let ue = {
        found: !1,
        width: 512,
        height: 512
    };
    function de(i, o) {
        let s = o;
        return "fa" === o && null !== x.styleDefault && (o = S), new Promise((t, e) => {
            var a, n, r;
            if ("fa" === s && (a = Vt(i) || {}, i = a.iconName || i, o = a.prefix || o), 
            i && o && fe[o] && fe[o][i]) return t(ce(fe[o][i]));
            n = i, r = o, ft || x.showMissingIcons || !n || console.error('Icon with name "'.concat(n, '" and prefix "').concat(r, '" is missing.')), 
            t(b(b({}, ue), {}, {
                icon: x.showMissingIcons && i && I("missingIconAbstract") || {}
            }));
        });
    }
    t = () => {};
    let me = x.measurePerformance && a && a.mark && a.measure ? a : {
        mark: t,
        measure: t
    }, D = 'FA "6.7.2"', he = t => {
        me.mark("".concat(D, " ").concat(t, " ends")), me.measure("".concat(D, " ").concat(t), "".concat(D, " ").concat(t, " begins"), "".concat(D, " ").concat(t, " ends"));
    };
    var pe = {
        begin: t => (me.mark("".concat(D, " ").concat(t, " begins")), () => he(t)),
        end: he
    };
    let ge = () => {};
    function ve(t) {
        return "string" == typeof (t.getAttribute ? t.getAttribute(y) : null);
    }
    function be(e, t) {
        let {
            ceFn: a = "svg" === e.tag ? function(t) {
                return g.createElementNS("http://www.w3.org/2000/svg", t);
            } : function(t) {
                return g.createElement(t);
            }
        } = 1 < arguments.length && void 0 !== t ? t : {};
        if ("string" == typeof e) return g.createTextNode(e);
        let n = a(e.tag);
        return Object.keys(e.attributes || []).forEach(function(t) {
            n.setAttribute(t, e.attributes[t]);
        }), (e.children || []).forEach(function(t) {
            n.appendChild(be(t, {
                ceFn: a
            }));
        }), n;
    }
    let ye = {
        replace: function(t) {
            let e = t[0];
            var a;
            e.parentNode && (t[1].forEach(t => {
                e.parentNode.insertBefore(be(t), e);
            }), null === e.getAttribute(y) && x.keepOriginalSource ? (a = g.createComment((t = e, 
            a = " ".concat(t.outerHTML, " "), "".concat(a, "Font Awesome fontawesome.com "))), 
            e.parentNode.replaceChild(a, e)) : e.remove());
        },
        nest: function(t) {
            var e = t[0], a = t[1];
            if (~kt(e).indexOf(x.replacementClass)) return ye.replace(t);
            let n = new RegExp("".concat(x.cssPrefix, "-.*"));
            delete a[0].attributes.id, a[0].attributes.class && (r = a[0].attributes.class.split(" ").reduce((t, e) => ((e === x.replacementClass || e.match(n) ? t.toSvg : t.toNode).push(e), 
            t), {
                toNode: [],
                toSvg: []
            }), a[0].attributes.class = r.toSvg.join(" "), 0 === r.toNode.length ? e.removeAttribute("class") : e.setAttribute("class", r.toNode.join(" ")));
            var r = a.map(t => O(t)).join("\n");
            e.setAttribute(y, ""), e.innerHTML = r;
        }
    };
    function xe(t) {
        t();
    }
    function ke(a, t) {
        let n = "function" == typeof t ? t : ge;
        if (0 === a.length) n(); else {
            let t = xe;
            (t = x.mutateApproach === st ? e.requestAnimationFrame || xe : t)(() => {
                var t = !0 !== x.autoReplaceSvg && ye[x.autoReplaceSvg] || ye.replace, e = pe.begin("mutate");
                a.map(t), e(), n();
            });
        }
    }
    let we = !1;
    function Ae() {
        we = !0;
    }
    function Pe() {
        we = !1;
    }
    let Oe = null;
    function Ne(t) {
        if (!_) return;
        if (!x.observeMutations) return;
        let {
            treeCallback: i = ge,
            nodeCallback: o = ge,
            pseudoElementsCallback: s = ge,
            observeMutationsRoot: e = g
        } = t;
        Oe = new _(t => {
            if (!we) {
                let r = S;
                w(t).forEach(t => {
                    var e, a, n;
                    "childList" === t.type && 0 < t.addedNodes.length && !ve(t.addedNodes[0]) && (x.searchPseudoElements && s(t.target), 
                    i(t.target)), "attributes" === t.type && t.target.parentNode && x.searchPseudoElements && s(t.target.parentNode), 
                    "attributes" === t.type && ve(t.target) && ~gt.indexOf(t.attributeName) && ("class" === t.attributeName && (e = t.target, 
                    a = e.getAttribute ? e.getAttribute(rt) : null, n = e.getAttribute ? e.getAttribute(it) : null, 
                    a) && n ? ({
                        prefix: a,
                        iconName: n
                    } = Qt(kt(t.target)), t.target.setAttribute(rt, a || r), n && t.target.setAttribute(it, n)) : (e = t.target) && e.classList && e.classList.contains && e.classList.contains(x.replacementClass) && o(t.target));
                });
            }
        }), f && Oe.observe(e, {
            childList: !0,
            attributes: !0,
            characterData: !0,
            subtree: !0
        });
    }
    function Se(t) {
        var e, a, n = t.getAttribute("data-prefix"), r = t.getAttribute("data-icon"), i = void 0 !== t.innerText ? t.innerText.trim() : "", o = Qt(kt(t));
        return o.prefix || (o.prefix = S), n && r && (o.prefix = n, o.iconName = r), 
        o.iconName && o.prefix || (o.prefix && 0 < i.length && (o.iconName = (e = o.prefix, 
        a = t.innerText, (Ht[e] || {})[a] || qt(o.prefix, Ft(t.innerText)))), !o.iconName && x.autoFetchSvg && t.firstChild && t.firstChild.nodeType === Node.TEXT_NODE && (o.iconName = t.firstChild.data)), 
        o;
    }
    function Ce(t, e) {
        var a = 1 < arguments.length && void 0 !== e ? e : {
            styleParser: !0
        }, {
            iconName: n,
            prefix: r,
            rest: i
        } = Se(t), o = (l = w((e = t).attributes).reduce((t, e) => ("class" !== t.name && "style" !== t.name && (t[e.name] = e.value), 
        t), {}), o = e.getAttribute("title"), s = e.getAttribute("data-fa-title-id"), 
        x.autoA11y && (o ? l["aria-labelledby"] = "".concat(x.replacementClass, "-title-").concat(s || k()) : (l["aria-hidden"] = "true", 
        l.focusable = "false")), l), s = ae("parseNodeAttributes", {}, t), l = a.styleParser ? (t => {
            var e = t.getAttribute("style");
            let a = [];
            return a = e ? e.split(";").reduce((t, e) => {
                var a = e.split(":"), n = a[0], a = a.slice(1);
                return n && 0 < a.length && (t[n] = a.join(":").trim()), t;
            }, {}) : a;
        })(t) : [];
        return b({
            iconName: n,
            title: t.getAttribute("title"),
            titleId: t.getAttribute("data-fa-title-id"),
            prefix: r,
            transform: v,
            mask: {
                iconName: null,
                prefix: null,
                rest: []
            },
            maskId: null,
            symbol: !1,
            extra: {
                classes: i,
                styles: l,
                attributes: o
            }
        }, s);
    }
    let Ee = P.styles;
    function Me(t) {
        var e = "nest" === x.autoReplaceSvg ? Ce(t, {
            styleParser: !1
        }) : Ce(t);
        return ~e.extra.classes.indexOf(ht) ? I("generateLayersText", t, e) : I("generateSvgReplacementMutation", t, e);
    }
    function je(t) {
        let n = 1 < arguments.length && void 0 !== arguments[1] ? arguments[1] : null;
        if (!f) return Promise.resolve();
        let e = g.documentElement.classList, r = t => e.add("".concat(ot, "-").concat(t)), i = t => e.remove("".concat(ot, "-").concat(t));
        var a = x.autoFetchSvg ? [ ...G, ...$ ] : X.concat(Object.keys(Ee)), a = (a.includes("fa") || a.push("fa"), 
        [ ".".concat(ht, ":not([").concat(y, "])") ].concat(a.map(t => ".".concat(t, ":not([").concat(y, "])"))).join(", "));
        if (0 === a.length) return Promise.resolve();
        let o = [];
        try {
            o = w(t.querySelectorAll(a));
        } catch (t) {}
        if (!(0 < o.length)) return Promise.resolve();
        r("pending"), i("complete");
        let s = pe.begin("onTree"), l = o.reduce((t, e) => {
            try {
                var a = Me(e);
                a && t.push(a);
            } catch (t) {
                ft || "MissingIcon" === t.name && console.error(t);
            }
            return t;
        }, []);
        return new Promise((e, a) => {
            Promise.all(l).then(t => {
                ke(t, () => {
                    r("active"), r("complete"), i("pending"), "function" == typeof n && n(), 
                    s(), e();
                });
            }).catch(t => {
                s(), a(t);
            });
        });
    }
    function ze(t) {
        let e = 1 < arguments.length && void 0 !== arguments[1] ? arguments[1] : null;
        Me(t).then(t => {
            t && ke([ t ], e);
        });
    }
    function Ie(n) {
        let r = 1 < arguments.length && void 0 !== arguments[1] ? arguments[1] : {}, {
            transform: i = v,
            symbol: o = !1,
            mask: s = null,
            maskId: l = null,
            title: f = null,
            titleId: c = null,
            classes: u = [],
            attributes: d = {},
            styles: m = {}
        } = r;
        if (n) {
            let {
                prefix: t,
                iconName: e,
                icon: a
            } = n;
            return oe(b({
                type: "icon"
            }, n), () => (z("beforeDOMElementCreation", {
                iconDefinition: n,
                params: r
            }), x.autoA11y && (f ? d["aria-labelledby"] = "".concat(x.replacementClass, "-title-").concat(c || k()) : (d["aria-hidden"] = "true", 
            d.focusable = "false")), se({
                icons: {
                    main: ce(a),
                    mask: s ? ce(s.icon) : {
                        found: !1,
                        width: null,
                        height: null,
                        icon: {}
                    }
                },
                prefix: t,
                iconName: e,
                transform: b(b({}, v), i),
                symbol: o,
                title: f,
                maskId: l,
                titleId: c,
                extra: {
                    attributes: d,
                    styles: m,
                    classes: u
                }
            })));
        }
    }
    let Fe = {
        mixout() {
            return {
                icon: (r = Ie, function(t) {
                    var e = 1 < arguments.length && void 0 !== arguments[1] ? arguments[1] : {}, a = (t || {}).icon ? t : ne(t || {});
                    let n = e.mask;
                    return n = n && ((n || {}).icon ? n : ne(n || {})), r(a, b(b({}, e), {}, {
                        mask: n
                    }));
                })
            };
            var r;
        },
        hooks() {
            return {
                mutationObserverCallbacks(t) {
                    return t.treeCallback = je, t.nodeCallback = ze, t;
                }
            };
        },
        provides(t) {
            t.i2svg = function(t) {
                var {
                    node: e = g,
                    callback: a = () => {}
                } = t;
                return je(e, a);
            }, t.generateSvgReplacementMutation = function(r, t) {
                let {
                    iconName: i,
                    title: o,
                    titleId: s,
                    prefix: l,
                    transform: f,
                    symbol: c,
                    mask: e,
                    maskId: u,
                    extra: d
                } = t;
                return new Promise((n, t) => {
                    Promise.all([ de(i, l), e.iconName ? de(e.iconName, e.prefix) : Promise.resolve({
                        found: !1,
                        width: 512,
                        height: 512,
                        icon: {}
                    }) ]).then(t => {
                        var [ e, a ] = t;
                        n([ r, se({
                            icons: {
                                main: e,
                                mask: a
                            },
                            prefix: l,
                            iconName: i,
                            transform: f,
                            symbol: c,
                            maskId: u,
                            title: o,
                            titleId: s,
                            extra: d,
                            watchable: !0
                        }) ]);
                    }).catch(t);
                });
            }, t.generateAbstractIcon = function(t) {
                var {
                    children: e,
                    attributes: a,
                    main: n,
                    transform: r,
                    styles: i
                } = t, i = A(i);
                0 < i.length && (a.style = i);
                let o;
                return At(r) && (o = I("generateAbstractTransformGrouping", {
                    main: n,
                    transform: r,
                    containerWidth: n.width,
                    iconWidth: n.width
                })), e.push(o || n.icon), {
                    children: e,
                    attributes: a
                };
            };
        }
    }, De = {
        mixout() {
            return {
                layer(t) {
                    let a = 1 < arguments.length && void 0 !== arguments[1] ? arguments[1] : {}, {
                        classes: n = []
                    } = a;
                    return oe({
                        type: "layer"
                    }, () => {
                        z("beforeDOMElementCreation", {
                            assembler: t,
                            params: a
                        });
                        let e = [];
                        return t(t => {
                            Array.isArray(t) ? t.map(t => {
                                e = e.concat(t.abstract);
                            }) : e = e.concat(t.abstract);
                        }), [ {
                            tag: "span",
                            attributes: {
                                class: [ "".concat(x.cssPrefix, "-layers"), ...n ].join(" ")
                            },
                            children: e
                        } ];
                    });
                }
            };
        }
    }, Re = {
        mixout() {
            return {
                counter(r) {
                    let i = 1 < arguments.length && void 0 !== arguments[1] ? arguments[1] : {}, {
                        title: o = null,
                        classes: s = [],
                        attributes: l = {},
                        styles: f = {}
                    } = i;
                    return oe({
                        type: "counter",
                        content: r
                    }, () => {
                        z("beforeDOMElementCreation", {
                            content: r,
                            params: i
                        });
                        var {
                            content: t,
                            title: e,
                            extra: a
                        } = {
                            content: r.toString(),
                            title: o,
                            extra: {
                                attributes: l,
                                styles: f,
                                classes: [ "".concat(x.cssPrefix, "-layers-counter"), ...s ]
                            }
                        }, n = b(b(b({}, a.attributes), e ? {
                            title: e
                        } : {}), {}, {
                            class: a.classes.join(" ")
                        });
                        return 0 < (a = A(a.styles)).length && (n.style = a), (a = []).push({
                            tag: "span",
                            attributes: n,
                            children: [ t ]
                        }), e && a.push({
                            tag: "span",
                            attributes: {
                                class: "sr-only"
                            },
                            children: [ e ]
                        }), a;
                    });
                }
            };
        }
    }, Le = {
        mixout() {
            return {
                text(t) {
                    let e = 1 < arguments.length && void 0 !== arguments[1] ? arguments[1] : {}, {
                        transform: a = v,
                        title: n = null,
                        classes: r = [],
                        attributes: i = {},
                        styles: o = {}
                    } = e;
                    return oe({
                        type: "text",
                        content: t
                    }, () => (z("beforeDOMElementCreation", {
                        content: t,
                        params: e
                    }), le({
                        content: t,
                        transform: b(b({}, v), a),
                        title: n,
                        extra: {
                            attributes: i,
                            styles: o,
                            classes: [ "".concat(x.cssPrefix, "-layers-text"), ...r ]
                        }
                    })));
                }
            };
        },
        provides(t) {
            t.generateLayersText = function(t, e) {
                var a, n, {
                    title: r,
                    transform: i,
                    extra: o
                } = e;
                let s = null, l = null;
                return B && (a = parseInt(getComputedStyle(t).fontSize, 10), n = t.getBoundingClientRect(), 
                s = n.width / a, l = n.height / a), x.autoA11y && !r && (o.attributes["aria-hidden"] = "true"), 
                Promise.resolve([ t, le({
                    content: t.innerHTML,
                    width: s,
                    height: l,
                    transform: i,
                    title: r,
                    extra: o,
                    watchable: !0
                }) ]);
            };
        }
    }, Te = new RegExp('"', "ug"), Ye = [ 1105920, 1112319 ], We = b(b(b(b({}, {
        FontAwesome: {
            normal: "fas",
            400: "fas"
        }
    }), {
        "Font Awesome 6 Free": {
            900: "fas",
            400: "far"
        },
        "Font Awesome 6 Pro": {
            900: "fas",
            400: "far",
            normal: "far",
            300: "fal",
            100: "fat"
        },
        "Font Awesome 6 Brands": {
            400: "fab",
            normal: "fab"
        },
        "Font Awesome 6 Duotone": {
            900: "fad",
            400: "fadr",
            normal: "fadr",
            300: "fadl",
            100: "fadt"
        },
        "Font Awesome 6 Sharp": {
            900: "fass",
            400: "fasr",
            normal: "fasr",
            300: "fasl",
            100: "fast"
        },
        "Font Awesome 6 Sharp Duotone": {
            900: "fasds",
            400: "fasdr",
            normal: "fasdr",
            300: "fasdl",
            100: "fasdt"
        }
    }), {
        "Font Awesome 5 Free": {
            900: "fas",
            400: "far"
        },
        "Font Awesome 5 Pro": {
            900: "fas",
            400: "far",
            normal: "far",
            300: "fal"
        },
        "Font Awesome 5 Brands": {
            400: "fab",
            normal: "fab"
        },
        "Font Awesome 5 Duotone": {
            900: "fad"
        }
    }), {
        "Font Awesome Kit": {
            400: "fak",
            normal: "fak"
        },
        "Font Awesome Kit Duotone": {
            400: "fakd",
            normal: "fakd"
        }
    }), He = Object.keys(We).reduce((t, e) => (t[e.toLowerCase()] = We[e], t), {}), _e = Object.keys(He).reduce((t, e) => {
        var a = He[e];
        return t[e] = a[900] || [ ...Object.entries(a) ][0][1], t;
    }, {});
    function Ue(m, h) {
        let p = "".concat(nt).concat(h.replace(":", "-"));
        return new Promise((s, a) => {
            if (null !== m.getAttribute(p)) return s();
            var n, r, l = w(m.children).filter(t => t.getAttribute(at) === h)[0], f = e.getComputedStyle(m, h), c = f.getPropertyValue("font-family"), u = c.match(pt), d = f.getPropertyValue("font-weight");
            let t = f.getPropertyValue("content");
            if (l && !u) return m.removeChild(l), s();
            if (u && "none" !== t && "" !== t) {
                let t = f.getPropertyValue("content"), i = (r = d, f = c.replace(/^['"]|['"]$/g, "").toLowerCase(), 
                d = parseInt(r), d = isNaN(d) ? "normal" : d, (He[f] || {})[d] || _e[f]);
                r = t, c = r.replace(Te, ""), r = 0, d = (n = c).length, f = (d = 55296 <= (f = n.charCodeAt(r)) && f <= 56319 && r + 1 < d && 56320 <= (d = n.charCodeAt(r + 1)) && d <= 57343 ? 1024 * (f - 55296) + d - 56320 + 65536 : f) >= Ye[0] && d <= Ye[1];
                var {
                    value: c,
                    isSecondary: f
                } = {
                    value: Ft((d = 2 === c.length && c[0] === c[1]) ? c[0] : c),
                    isSecondary: f || d
                }, d = u[0].startsWith("FontAwesome");
                let e = qt(i, c), o = e;
                if (d && (n = c, u = Ut[n], d = qt("fas", n), (c = u || (d ? {
                    prefix: "fas",
                    iconName: d
                } : null) || {
                    prefix: null,
                    iconName: null
                }).iconName) && c.prefix && (e = c.iconName, i = c.prefix), !e || f || l && l.getAttribute(rt) === i && l.getAttribute(it) === o) s(); else {
                    m.setAttribute(p, o), l && m.removeChild(l);
                    let n = {
                        iconName: null,
                        title: null,
                        titleId: null,
                        prefix: null,
                        transform: v,
                        symbol: !1,
                        mask: {
                            iconName: null,
                            prefix: null,
                            rest: []
                        },
                        maskId: null,
                        extra: {
                            classes: [],
                            styles: {},
                            attributes: {}
                        }
                    }, r = n.extra;
                    r.attributes[at] = h, de(e, i).then(t => {
                        var e = se(b(b({}, n), {}, {
                            icons: {
                                main: t,
                                mask: Gt()
                            },
                            prefix: i,
                            iconName: o,
                            extra: r,
                            watchable: !0
                        })), a = g.createElementNS("http://www.w3.org/2000/svg", "svg");
                        "::before" === h ? m.insertBefore(a, m.firstChild) : m.appendChild(a), 
                        a.outerHTML = e.map(t => O(t)).join("\n"), m.removeAttribute(p), 
                        s();
                    }).catch(a);
                }
            } else s();
        });
    }
    function Be(t) {
        return Promise.all([ Ue(t, "::before"), Ue(t, "::after") ]);
    }
    function Xe(t) {
        return !(t.parentNode === document.head || ~lt.indexOf(t.tagName.toUpperCase()) || t.getAttribute(at) || t.parentNode && "svg" === t.parentNode.tagName);
    }
    function qe(r) {
        if (f) return new Promise((t, e) => {
            var a = w(r.querySelectorAll("*")).filter(Xe).map(Be);
            let n = pe.begin("searchPseudoElements");
            Ae(), Promise.all(a).then(() => {
                n(), Pe(), t();
            }).catch(() => {
                n(), Pe(), e();
            });
        });
    }
    let Ve = {
        hooks() {
            return {
                mutationObserverCallbacks(t) {
                    return t.pseudoElementsCallback = qe, t;
                }
            };
        },
        provides(t) {
            t.pseudoElements2svg = function(t) {
                var {
                    node: e = g
                } = t;
                x.searchPseudoElements && qe(e);
            };
        }
    }, Ge = !1, Ke = {
        mixout() {
            return {
                dom: {
                    unwatch() {
                        Ae(), Ge = !0;
                    }
                }
            };
        },
        hooks() {
            return {
                bootstrap() {
                    Ne(ae("mutationObserverCallbacks", {}));
                },
                noAuto() {
                    Oe && Oe.disconnect();
                },
                watch(t) {
                    var e = t.observeMutationsRoot;
                    Ge ? Pe() : Ne(ae("mutationObserverCallbacks", {
                        observeMutationsRoot: e
                    }));
                }
            };
        }
    }, Je = t => t.toLowerCase().split(" ").reduce((t, e) => {
        var a = e.toLowerCase().split("-"), n = a[0], r = a.slice(1).join("-");
        if (n && "h" === r) t.flipX = !0; else if (n && "v" === r) t.flipY = !0; else if (r = parseFloat(r), 
        !isNaN(r)) switch (n) {
          case "grow":
            t.size = t.size + r;
            break;

          case "shrink":
            t.size = t.size - r;
            break;

          case "left":
            t.x = t.x - r;
            break;

          case "right":
            t.x = t.x + r;
            break;

          case "up":
            t.y = t.y - r;
            break;

          case "down":
            t.y = t.y + r;
            break;

          case "rotate":
            t.rotate = t.rotate + r;
        }
        return t;
    }, {
        size: 16,
        x: 0,
        y: 0,
        flipX: !1,
        flipY: !1,
        rotate: 0
    }), Qe = {
        mixout() {
            return {
                parse: {
                    transform: t => Je(t)
                }
            };
        },
        hooks() {
            return {
                parseNodeAttributes(t, e) {
                    var a = e.getAttribute("data-fa-transform");
                    return a && (t.transform = Je(a)), t;
                }
            };
        },
        provides(t) {
            t.generateAbstractTransformGrouping = function(t) {
                var {
                    main: e,
                    transform: a,
                    containerWidth: n,
                    iconWidth: r
                } = t, n = {
                    transform: "translate(".concat(n / 2, " 256)")
                }, i = "translate(".concat(32 * a.x, ", ").concat(32 * a.y, ") "), o = "scale(".concat(a.size / 16 * (a.flipX ? -1 : 1), ", ").concat(a.size / 16 * (a.flipY ? -1 : 1), ") "), a = "rotate(".concat(a.rotate, " 0 0)"), n = {
                    outer: n,
                    inner: {
                        transform: "".concat(i, " ").concat(o, " ").concat(a)
                    },
                    path: {
                        transform: "translate(".concat(r / 2 * -1, " -256)")
                    }
                };
                return {
                    tag: "g",
                    attributes: b({}, n.outer),
                    children: [ {
                        tag: "g",
                        attributes: b({}, n.inner),
                        children: [ {
                            tag: e.icon.tag,
                            children: e.icon.children,
                            attributes: b(b({}, e.icon.attributes), n.path)
                        } ]
                    } ]
                };
            };
        }
    }, Ze = {
        x: 0,
        y: 0,
        width: "100%",
        height: "100%"
    };
    function $e(t) {
        return t.attributes && (t.attributes.fill || (!(1 < arguments.length && void 0 !== arguments[1]) || arguments[1])) && (t.attributes.fill = "black"), 
        t;
    }
    r = [ J, Fe, De, Re, Le, Ve, Ke, Qe, {
        hooks() {
            return {
                parseNodeAttributes(t, e) {
                    var a = e.getAttribute("data-fa-mask"), a = a ? Qt(a.split(" ").map(t => t.trim())) : Gt();
                    return a.prefix || (a.prefix = S), t.mask = a, t.maskId = e.getAttribute("data-fa-mask-id"), 
                    t;
                }
            };
        },
        provides(t) {
            t.generateAbstractMask = function(t) {
                var {
                    children: e,
                    attributes: a,
                    main: n,
                    mask: r,
                    maskId: i,
                    transform: o
                } = t, {
                    width: n,
                    icon: s
                } = n, {
                    width: r,
                    icon: l
                } = r, o = (t => {
                    var {
                        transform: e,
                        containerWidth: a,
                        iconWidth: n
                    } = t, a = {
                        transform: "translate(".concat(a / 2, " 256)")
                    }, r = "translate(".concat(32 * e.x, ", ").concat(32 * e.y, ") "), i = "scale(".concat(e.size / 16 * (e.flipX ? -1 : 1), ", ").concat(e.size / 16 * (e.flipY ? -1 : 1), ") "), e = "rotate(".concat(e.rotate, " 0 0)");
                    return {
                        outer: a,
                        inner: {
                            transform: "".concat(r, " ").concat(i, " ").concat(e)
                        },
                        path: {
                            transform: "translate(".concat(n / 2 * -1, " -256)")
                        }
                    };
                })({
                    transform: o,
                    containerWidth: r,
                    iconWidth: n
                }), r = {
                    tag: "rect",
                    attributes: b(b({}, Ze), {}, {
                        fill: "white"
                    })
                }, n = s.children ? {
                    children: s.children.map($e)
                } : {}, s = {
                    tag: "g",
                    attributes: b({}, o.inner),
                    children: [ $e(b({
                        tag: s.tag,
                        attributes: b(b({}, s.attributes), o.path)
                    }, n)) ]
                }, n = {
                    tag: "g",
                    attributes: b({}, o.outer),
                    children: [ s ]
                }, o = "mask-".concat(i || k()), s = "clip-".concat(i || k()), i = {
                    tag: "mask",
                    attributes: b(b({}, Ze), {}, {
                        id: o,
                        maskUnits: "userSpaceOnUse",
                        maskContentUnits: "userSpaceOnUse"
                    }),
                    children: [ r, n ]
                }, r = {
                    tag: "defs",
                    children: [ {
                        tag: "clipPath",
                        attributes: {
                            id: s
                        },
                        children: "g" === (t = l).tag ? t.children : [ t ]
                    }, i ]
                };
                return e.push(r, {
                    tag: "rect",
                    attributes: b({
                        fill: "currentColor",
                        "clip-path": "url(#".concat(s, ")"),
                        mask: "url(#".concat(o, ")")
                    }, Ze)
                }), {
                    children: e,
                    attributes: a
                };
            };
        }
    }, {
        provides(t) {
            let i = !1;
            e.matchMedia && (i = e.matchMedia("(prefers-reduced-motion: reduce)").matches), 
            t.missingIconAbstract = function() {
                var t = [], e = {
                    fill: "currentColor"
                }, a = {
                    attributeType: "XML",
                    repeatCount: "indefinite",
                    dur: "2s"
                }, n = (t.push({
                    tag: "path",
                    attributes: b(b({}, e), {}, {
                        d: "M156.5,447.7l-12.6,29.5c-18.7-9.5-35.9-21.2-51.5-34.9l22.7-22.7C127.6,430.5,141.5,440,156.5,447.7z M40.6,272H8.5 c1.4,21.2,5.4,41.7,11.7,61.1L50,321.2C45.1,305.5,41.8,289,40.6,272z M40.6,240c1.4-18.8,5.2-37,11.1-54.1l-29.5-12.6 C14.7,194.3,10,216.7,8.5,240H40.6z M64.3,156.5c7.8-14.9,17.2-28.8,28.1-41.5L69.7,92.3c-13.7,15.6-25.5,32.8-34.9,51.5 L64.3,156.5z M397,419.6c-13.9,12-29.4,22.3-46.1,30.4l11.9,29.8c20.7-9.9,39.8-22.6,56.9-37.6L397,419.6z M115,92.4 c13.9-12,29.4-22.3,46.1-30.4l-11.9-29.8c-20.7,9.9-39.8,22.6-56.8,37.6L115,92.4z M447.7,355.5c-7.8,14.9-17.2,28.8-28.1,41.5 l22.7,22.7c13.7-15.6,25.5-32.9,34.9-51.5L447.7,355.5z M471.4,272c-1.4,18.8-5.2,37-11.1,54.1l29.5,12.6 c7.5-21.1,12.2-43.5,13.6-66.8H471.4z M321.2,462c-15.7,5-32.2,8.2-49.2,9.4v32.1c21.2-1.4,41.7-5.4,61.1-11.7L321.2,462z M240,471.4c-18.8-1.4-37-5.2-54.1-11.1l-12.6,29.5c21.1,7.5,43.5,12.2,66.8,13.6V471.4z M462,190.8c5,15.7,8.2,32.2,9.4,49.2h32.1 c-1.4-21.2-5.4-41.7-11.7-61.1L462,190.8z M92.4,397c-12-13.9-22.3-29.4-30.4-46.1l-29.8,11.9c9.9,20.7,22.6,39.8,37.6,56.9 L92.4,397z M272,40.6c18.8,1.4,36.9,5.2,54.1,11.1l12.6-29.5C317.7,14.7,295.3,10,272,8.5V40.6z M190.8,50 c15.7-5,32.2-8.2,49.2-9.4V8.5c-21.2,1.4-41.7,5.4-61.1,11.7L190.8,50z M442.3,92.3L419.6,115c12,13.9,22.3,29.4,30.5,46.1 l29.8-11.9C470,128.5,457.3,109.4,442.3,92.3z M397,92.4l22.7-22.7c-15.6-13.7-32.8-25.5-51.5-34.9l-12.6,29.5 C370.4,72.1,384.4,81.5,397,92.4z"
                    })
                }), b(b({}, a), {}, {
                    attributeName: "opacity"
                })), r = {
                    tag: "circle",
                    attributes: b(b({}, e), {}, {
                        cx: "256",
                        cy: "364",
                        r: "28"
                    }),
                    children: []
                };
                return i || r.children.push({
                    tag: "animate",
                    attributes: b(b({}, a), {}, {
                        attributeName: "r",
                        values: "28;14;28;28;14;28;"
                    })
                }, {
                    tag: "animate",
                    attributes: b(b({}, n), {}, {
                        values: "1;0;1;1;0;1;"
                    })
                }), t.push(r), t.push({
                    tag: "path",
                    attributes: b(b({}, e), {}, {
                        opacity: "1",
                        d: "M263.7,312h-16c-6.6,0-12-5.4-12-12c0-71,77.4-63.9,77.4-107.8c0-20-17.8-40.2-57.4-40.2c-29.1,0-44.3,9.6-59.2,28.7 c-3.9,5-11.1,6-16.2,2.4l-13.1-9.2c-5.6-3.9-6.9-11.8-2.6-17.2c21.2-27.2,46.4-44.7,91.2-44.7c52.3,0,97.4,29.8,97.4,80.2 c0,67.6-77.4,63.5-77.4,107.8C275.7,306.6,270.3,312,263.7,312z"
                    }),
                    children: i ? [] : [ {
                        tag: "animate",
                        attributes: b(b({}, n), {}, {
                            values: "1;0;0;0;0;1;"
                        })
                    } ]
                }), i || t.push({
                    tag: "path",
                    attributes: b(b({}, e), {}, {
                        opacity: "0",
                        d: "M232.5,134.5l7,168c0.3,6.4,5.6,11.5,12,11.5h9c6.4,0,11.7-5.1,12-11.5l7-168c0.3-6.8-5.2-12.5-12-12.5h-23 C237.7,122,232.2,127.7,232.5,134.5z"
                    }),
                    children: [ {
                        tag: "animate",
                        attributes: b(b({}, n), {}, {
                            values: "0;0;1;1;0;0;"
                        })
                    } ]
                }), {
                    tag: "g",
                    attributes: {
                        class: "missing"
                    },
                    children: t
                };
            };
        }
    }, {
        hooks() {
            return {
                parseNodeAttributes(t, e) {
                    var a = e.getAttribute("data-fa-symbol");
                    return t.symbol = null !== a && ("" === a || a), t;
                }
            };
        }
    } ];
    {
        var ta = r;
        let n = {
            mixoutsTo: ie
        }.mixoutsTo;
        te = ta, M = {}, Object.keys(j).forEach(t => {
            -1 === ee.indexOf(t) && delete j[t];
        }), te.forEach(t => {
            let a = t.mixout ? t.mixout() : {};
            if (Object.keys(a).forEach(e => {
                "function" == typeof a[e] && (n[e] = a[e]), "object" == typeof a[e] && Object.keys(a[e]).forEach(t => {
                    n[e] || (n[e] = {}), n[e][t] = a[e][t];
                });
            }), t.hooks) {
                let e = t.hooks();
                Object.keys(e).forEach(t => {
                    M[t] || (M[t] = []), M[t].push(e[t]);
                });
            }
            t.provides && t.provides(j);
        }), n;
    }
    !function(t) {
        try {
            for (var e = arguments.length, a = new Array(1 < e ? e - 1 : 0), n = 1; n < e; n++) a[n - 1] = arguments[n];
            t(...a);
        } catch (t) {
            if (!ft) throw t;
        }
    }(function(t) {
        U && (e.FontAwesome || (e.FontAwesome = ie), Mt(() => {
            F(), z("bootstrap");
        })), P.hooks = b(b({}, P.hooks), {}, {
            addPack: (t, e) => {
                P.styles[t] = b(b({}, P.styles[t] || {}), e), C(), F();
            },
            addPacks: t => {
                t.forEach(t => {
                    var [ e, a ] = t;
                    P.styles[e] = b(b({}, P.styles[e] || {}), a);
                }), C(), F();
            },
            addShims: t => {
                P.shims.push(...t), C(), F();
            }
        });
    });
})();