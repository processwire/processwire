
$(document).ready(function() {
	
	$(".InputfieldIcon select").change(function() {
		
		var $select = $(this); 
		var val = $select.val();
	
		if(val.length > 0) {
			$select.closest(".InputfieldIcon").find(".InputfieldHeader > i.fa:first-child")
				.attr('class', 'fa ' + val) 
				.parent().effect('highlight', 500);
			var $all = $select.siblings(".InputfieldIconAll");
			if($all.is(":visible")) {
				$all.find('.on').removeClass('on').mouseout();
				var $icon = $all.find("." + val).parent('span');
				if(!$icon.hasClass('on')) $icon.addClass('on').mouseover();
			}
		}
		
		$select.removeClass('on'); 
	});
	
	$(".InputfieldIconAll").hide();
	
	$("a.InputfieldIconShowAll").click(function() {
	
		var $link = $(this);
		var $all = $link.siblings(".InputfieldIconAll");
		var $select = $link.siblings("select"); 
		
		if($all.is(":visible")) {
			$all.slideUp('fast', function() {
				$all.html(''); 
			}); 
			return false;
		}
		
		$select.children("option").each(function() {
			var val = $(this).attr('value'); 
			if(val.length == 0) return;
			var $icon = $("<i class='fa fa-fw'></i>")
				.addClass(val)
				.attr('data-name', val)
				.css('cursor', 'pointer')
				.attr('title', val); 
			var $span = $("<span />")
				.css('padding', '2px 2px 2px 2px')
				.css('margin-right', '10px')
				.css('line-height', '30px')
				.append($icon);
			$all.append($span); 
		}); 
		
		$all.slideDown('fast', function() {
			
			$all.on('click', 'span', function() {
				$all.find('.on').removeClass('on').mouseout();
				$(this).addClass('on').mouseover();
				if(!$select.hasClass('on')) $select.val($(this).find('i.fa').attr('data-name')).change();

			});
			$all.on('mouseover', 'span', function() {
				$(this).css('background-color', 'red').css('color','white'); 
			});
			$all.on('mouseout', 'span', function() {
				if(!$(this).hasClass('on')) {
					$(this).css('background-color', 'inherit').css('color', 'inherit'); 
				}
			});

			var val = $select.val();	
			if(val.length > 0) $all.find("." + val).each(function() {
				$(this).parent('span').addClass('on').mouseover();
			}); 
		}); 
	
		return false;
	}); 
	
});