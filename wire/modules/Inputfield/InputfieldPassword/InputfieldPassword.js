jQuery(document).ready(function($) {
	
	var $inputs = $("input.InputfieldPasswordComplexify");
	
	$inputs.each(function() {
		
		var $input = $(this);
		var $inputfield = $input.closest('.Inputfield');
		var $confirm = $inputfield.find('.InputfieldPasswordConfirm');
		var $confirms = $confirm.next('.pass-confirm');
		var $wrapScores = $input.siblings('.pass-scores');
		var $percent = $input.siblings('.pass-percent');
		var $scores = $wrapScores.children();
		var requirements = $wrapScores.attr('data-requirements').split(' ');
		var minlength = parseInt($input.attr('data-minlength'));
		var options = {
			banMode: $input.attr('data-banMode'),
			strengthScaleFactor: parseFloat($input.attr('data-factor')),
			minimumChars: minlength
		};

		$input.complexify(options, function(valid, complexity) {
			
			var $on = null;
			var val = $input.val();
			var len = val.length;
			var numGood = 0;
			
			if(len > 0) {
				
				for(var n = 0; n < requirements.length; n++) {
					
					var fail = false;
					var requirement = requirements[n];
					var $requirement = $inputfield.find('.pass-require-' + requirement);
					
					if(requirement == 'letter') {
						var re = XRegExp("\\p{L}"); 
						if(!re.test(val)) fail = true;
					} else if(requirement == 'upper') {
						var re = XRegExp("\\p{Lu}"); 
						if(!re.test(val)) fail = true;
					} else if(requirement == 'lower') {
						var re = XRegExp("\\p{Ll}");
						if(!re.test(val)) fail = true;
					} else if(requirement == 'digit') {
						var re = XRegExp("\\p{N}");
						if(!re.test(val)) fail = true;
					} else if(requirement == 'other') {
						var re = XRegExp("\\p{P}");
						var rx = XRegExp("\\p{S}");
						if(!re.test(val) && !rx.test(val)) fail = true;
					} else if(requirement == 'space') {
						var re = XRegExp("\\p{Z}");
						if(!re.test(val)) fail = true;
					} else if(requirement == 'minlength') {
						if(len < minlength) fail = true; 
					}
					if(fail) {
						$requirement.removeClass('pass-require-good ui-priority-secondary');
					} else {
						$requirement.addClass('pass-require-good ui-priority-secondary');
						numGood++;
					}
				}
			} else {
				$inputfield.find('.pass-require-good').removeClass('pass-require-good ui-priority-secondary');
			}
			
			
			if(len == 0) {
				$scores.removeClass('on');
				return;
			} else if(numGood < requirements.length) {
				// doesn't match requirements
				$on = $scores.filter('.pass-fail');
			} else if(len < minlength) {
				// too short
				$on = $scores.filter('.pass-short');
			} else if(!valid) {
				// too common
				$on = $scores.filter('.pass-common');
			} else if(complexity == 0) {
				// invalid
				$on = $scores.filter('.pass-invalid');
			} else if(complexity < 50) {
				// weak
				$on = $scores.filter('.pass-weak');
			} else if(complexity < 70) {
				// medium
				$on = $scores.filter('.pass-medium');
			} else if(complexity < 100) {
				// good
				$on = $scores.filter('.pass-good');
			} else if(complexity == 100) {
				// excellent
				$on = $scores.filter('.pass-excellent');
			}
		
			if($on && !$on.hasClass('on')) {
				$on.siblings('.on').removeClass('on');
				$on.addClass('on');
			}
			if($on.hasClass('pass-fail') || $on.hasClass('pass-short') || $on.hasClass('pass-common') || $on.hasClass('pass-invalid')) {
				$confirm.attr('disabled', 'disabled').val('').change();
			} else {
				$confirm.removeAttr('disabled');
				$on.find('small').remove();
				$on.append("<small style='margin-left:0.5em'>(" + Math.floor(complexity) + "%)</small>");
			}
			
			if($confirm.val().length) {
				$confirm.change();
			}
			
			//console.log(valid);
			//console.log(complexity);
		});
		
		$input.on('change', function() {
			var val = $(this).val();
			if(val.length > 0) {
				$input.attr('required', 'required');	
				$confirm.attr('required', 'required');
			} else if(!$(this).closest('.InputfieldStateRequired').length) {
				$input.removeAttr('required');
				$confirm.removeAttr('required');
			}
		});
		
		$confirm.on('keyup change', function() {
			
			var val1 = $input.val();
			var val2 = $(this).val();
			var $on = null;
			var $p = $input.closest('p').removeClass('pass-matches');
			
			if(val2.length == 0) {
				$on = $confirms.children('.confirm-pending');
			} else if(val1 == val2) {
				$on = $confirms.children('.confirm-yes');
				$p.addClass('pass-matches');
			} else if(val1.indexOf(val2) === 0) {
				$on = $confirms.children('.confirm-qty');
				$on.children('span').html(val2.length + "/" + val1.length);
			} else {
				$on = $confirms.children('.confirm-no');
			}
			if($on) $on.addClass('on').siblings('.on').removeClass('on');
		});
	});

	// accommodate issue where Firefox auto-populates remembered password when it shouldn't
	var $ffinputs = $inputs.filter("[autocomplete='off']");
	if($ffinputs.length) {
		setTimeout(function() {
			$ffinputs.each(function() {
				if($(this).val().length < 1) return;
				$(this).val('').trigger('keyup').change()
					.closest('.Inputfield').removeClass('InputfieldStateChanged');
			});
		}, 1000);
	}
}); 