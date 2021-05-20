# 3.1.0
- [All Changes](https://github.com/wikimedia/less.php/compare/v3.0.0...v3.1.0)
* PHP 8.0 support: Drop use of curly braces for sub-string eval (James D. Forrester)
* Make `Directive::__construct` $rules arg optional (fix PHP 7.4 warning) (Sam Reed)
* ProcessExtends: Improve performance by using a map for selectors and parents (Andrey Legayev)
* build: Run CI tests on PHP 8.0 too (James D. Forrester)
* code: Fix PSR12.Properties.ConstantVisibility.NotFound (Sam Reed)

# 3.0.0
- [All Changes](https://github.com/wikimedia/less.php/compare/v2.0.0...v3.0.0)
- Raise PHP requirement from 7.1 to 7.2.9 (James Forrester)
- build: Upgrade phpunit to ^8.5 and make pass  (James Forrester)
- build: Install php-parallel-lint  (James Forrester)
- build: Install minus-x and make pass  (James Forrester)

# 2.0.0
- [All Changes](https://github.com/wikimedia/less.php/compare/1.8.2...v2.0.0)
- Relax PHP requirement down to 7.1, from 7.2.9 (Franz Liedke)
- Reflect recent breaking changes properly with the semantic versioning (James Forrester)

# 1.8.2
- [All Changes](https://github.com/wikimedia/less.php/compare/1.8.1...1.8.2)
- Require PHP 7.2.9+, up from 5.3+ (James Forrester)
- Release: Update Version.php with the current release ID (COBadger)
- Fix access array offset on value of type null (Michele Locati)
- Fixed test suite on PHP 7.4 (Sergei Morozov)
- docs: Fix 1.8.1 "All changes" link (Timo Tijhof)

# 1.8.1
- [All Changes](https://github.com/wikimedia/less.php/compare/v1.8.0...1.8.1)
- Another PHP 7.3 compatibility tweak

# 1.8.0
- [All Changes](https://github.com/Asenar/less.php/compare/v1.7.0.13...v1.8.0)
- Wikimedia fork
- Supports up to PHP 7.3
- No longer tested against PHP 5, though it's still remains allowed in `composer.json` for HHVM compatibility
- Switched to [semantic versioning](https://semver.org/), hence version numbers now use 3 digits

# 1.7.0.13
 - [All Changes](https://github.com/Asenar/less.php/compare/v1.7.0.12...v1.7.0.13)
 - Fix composer.json (PSR-4 was invalid)

# 1.7.0.12
 - [All Changes](https://github.com/Asenar/less.php/compare/v1.7.0.11...v1.7.0.12)
 - set bin/lessc bit executable
 - Add 'gettingVariables' method in Less_Parser

# 1.7.0.11
 - [All Changes](https://github.com/Asenar/less.php/compare/v1.7.0.10...v1.7.0.11)
 - Fix realpath issue (windows)
 - Set Less_Tree_Call property back to public ( Fix 258 266 267 issues from oyejorge/less.php)

# 1.7.0.10

 - [All Changes](https://github.com/oyejorge/less.php/compare/v1.7.0.9...v1.7.10)
 - Add indentation option
 - Add 'optional' modifier for @import
 - fix $color in Exception messages
 - don't use set_time_limit when running cli
 - take relative-url into account when building the cache filename
 - urlArgs should be string no array()
 - add bug-report fixtures [#6dc898f](https://github.com/oyejorge/less.php/commit/6dc898f5d75b447464906bdf19d79c2e19d95e33)
 - fix #269, missing on NameValue type [#a8dac63](https://github.com/oyejorge/less.php/commit/a8dac63d93fb941c54fb78b12588abf635747c1b)

# 1.7.0.9

 - [All Changes](https://github.com/oyejorge/less.php/compare/v1.7.0.8...v1.7.0.9)
 - Remove space at beginning of Version.php
 - Revert require() paths in test interface
