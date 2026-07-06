function InputfieldIcon() {

	var isFA6 = !!(ProcessWire && ProcessWire.config && ProcessWire.config.adminIcons &&
		parseInt(ProcessWire.config.adminIcons.version) >= 6 && ProcessWire.icon);

	$('.InputfieldIcon select').on('change', function() {
		var $select = $(this);
		var val = $select.val();
		if(val.length > 0) {
			var $headerIcon = $select.closest('.InputfieldIcon').find('.InputfieldHeader > i:first-child');
			if(isFA6) {
				var cls = $(ProcessWire.icon(val.replace(/^fa-/, ''), 'fw')).attr('class');
				$headerIcon.attr('class', cls);
			} else {
				$headerIcon.attr('class', 'fa fa-fw ' + val);
			}
			$headerIcon.parent().effect('highlight', 500);
		}
		$select.removeClass('on');
	});

	$('.InputfieldIconAll').hide();

	$('.InputfieldIconSearch').on('keydown', function(e) {
		if(e.key !== 'Enter') return;
		e.preventDefault();
		var $first = $(this).siblings('.InputfieldIconAll').find('i:visible').first();
		if($first.length) $first.trigger('click');
	});

	$('.InputfieldIconSearch').on('input', function() {
		var $input = $(this);
		var $all = $input.siblings('.InputfieldIconAll');
		var $select = $input.siblings('select');
		var term = $input.val().toLowerCase().replace(/^fa-/, '').trim();

		if(term.length < 3) {
			$all.hide();
			return;
		}

		if(!$all.hasClass('initialized')) {
			$all.addClass('initialized');

			var html = '';
			$select.children('option').each(function() {
				var val = $(this).val();
				if(!val) return;
				if(isFA6) {
					var iconHtml = ProcessWire.icon(val.replace(/^fa-/, ''), 'fw');
					html += iconHtml.replace(/'><\/i>$/, " InputfieldIconItem' title='" + val + "'></i>");
				} else {
					html += "<i class='fa fw " + val + "' title='" + val + "'></i>";
				}
			});
			$all.append(html);

			$all.on('click', 'i', function() {
				var $i = $(this);
				if($i.hasClass('on')) {
					$i.removeClass('on');
					$select.val('').trigger('change');
					return;
				}
				$all.find('.on').removeClass('on');
				$i.addClass('on');
				if(!$select.hasClass('on')) {
					$select.val($i.attr('title')).trigger('change');
				}
			});
		}

		var hasVisible = false;
		$all.find('i').each(function() {
			var name = ($(this).attr('title') || '').replace(/^fa-/, '').toLowerCase();
			var visible = name.indexOf(term) >= 0;
			$(this).toggle(visible);
			if(visible) hasVisible = true;
		});

		if(hasVisible) {
			$all.show();
			$all.find('.on').removeClass('on');
			var val = $select.val();
			if(val) $all.find('.' + val).addClass('on');
		} else {
			$all.hide();
		}
	});
}

$(document).ready(function() {
	InputfieldIcon();
});
