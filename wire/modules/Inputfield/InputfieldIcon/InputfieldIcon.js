$(document).ready(function() {

	$(".InputfieldIcon select").change(function() {

		var $select = $(this);
		var val = $select.val();

		if (val.length > 0) {
			$select.closest(".InputfieldIcon").find(".InputfieldHeader > i.fa:first-child")
				.attr('class', 'fa ' + val)
				.parent().effect('highlight', 500);
			var $all = $select.siblings(".InputfieldIconAll");
			if ($all.is(":visible")) {
				$all.find('.on').removeClass('on');
				$all.find("." + val).addClass('on');
			}
		}

		$select.removeClass('on');
	});

	$(".InputfieldIconAll").hide();


	$("a.InputfieldIconShowAll").on('click', function() {

		var $all = $(this).siblings(".InputfieldIconAll");
		var $select = $(this).siblings("select");

		if ($all.is(":visible")) {
			$all.slideUp('fast');
			//$all.off('click', 'i');
			return false;
		}

		$all.slideDown('fast', function() {

			if(!$all.hasClass('initialized')) {
				
				$all.addClass('initialized');
				
				$select.children("option").each(function() {
					var val = $(this).attr('value');
					if (val.length == 0) return;
					$all.append("<i class='fa fw " + val + "' title='" + val + "'>");
				});
			

				$all.on('click', 'i', function() {

					if ($(this).hasClass('on')) {
						$(this).removeClass('on');
						$select.val('').change();
						return;
					}

					$all.find('.on').removeClass('on');
					$(this).addClass('on');

					if (!$select.hasClass('on')) {
						$select.val($(this).attr('title')).change();
					}

				});

				var val = $select.val();
				if (val.length > 0) $all.find("." + val).addClass('on');
			}
		});

		return false;
	});

});
