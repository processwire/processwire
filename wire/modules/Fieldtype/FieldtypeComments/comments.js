
/**
 * Set a cookie value
 *
 * @param string name
 * @param string value
 * @param int days Specify 0 for session-only cookie
 *
 */
function CommentFormSetCookie(name, value, days) {
	var cookieValue = name + "=" + escape(value) + ";path=/";
	if(days == null) days = 0;
	if(days > 0) {
		var today = new Date();
		var expire = new Date();
		expire.setTime(today.getTime() + 3600000 * 24 * days);
		document.cookie = cookieValue + ";expires=" + expire.toGMTString();
	} else {
		document.cookie = cookieValue; 
	}
}

/**
 * Get a cookie value
 *
 * @param string name
 * @return string
 *
 */
function CommentFormGetCookie(name) {
	var regex = new RegExp('[; ]' + name + '=([^\\s;]*)');
	var match = (' ' + document.cookie).match(regex);
	if(name && match) return unescape(match[1]);
	return '';
}

/**
 * Handle the 5-star rating system for comments
 *
 * @param jQuery $
 *
 */
function CommentFormStars($) {

	function decodeEntities(encodedString) {
		if(encodedString.indexOf('&') == -1) return encodedString;
		var textarea = document.createElement('textarea');
		textarea.innerHTML = encodedString;
		return textarea.value;
	}

	// stars
	function setStars($parent, star) {
		var onClass = $parent.attr('data-onclass');

		var starOn = $parent.attr('data-on');
		if(typeof starOn != "undefined") {
			var starOff = $parent.attr('data-off');
			starOn = decodeEntities(starOn);
			starOff = decodeEntities(starOff);
		} else {
			var starOn = '';
			var starOff = '';
		}
		$parent.children('span').each(function() {
			var val = parseInt($(this).attr('data-value'));
			if(val <= star) {
				if(starOn.length) $(this).html(starOn);
				$(this).addClass(onClass);
			} else {
				if(starOff.length) $(this).html(starOff);
				$(this).removeClass(onClass);
			}
		});
	}

	$(".CommentFormStars input").hide();

	$(document).on('click', ".CommentStarsInput span", function(e) {
		var value = parseInt($(this).attr('data-value'));
		var $parent = $(this).parent();
		var $input = $parent.prev('input');
		$input.val(value).attr('value', value); // redundancy intended, val() not working on webkit mobile for some reason
		setStars($parent, value);
		$input.change();
		return false;
	});

	$(document).on('mouseover', ".CommentStarsInput span", function(e) {
		var $parent = $(this).parent();
		var value = parseInt($(this).attr('data-value'));
		setStars($parent, value);
	}).on('mouseout', ".CommentStarsInput span", function(e) {
		var $parent = $(this).parent();
		var $input = $parent.prev('input');
		var value = parseInt($input.val());
		setStars($parent, value);
	});
}

/**
 * Initialize comments form 
 * 
 */
jQuery(document).ready(function($) {
	$(".CommentActionReply").click(function() {
		var $this = $(this);
		var $form = $this.parent().next('form');
		if($form.length == 0) {
			$form = $("#CommentForm form").clone().removeAttr('id');
			$form.hide().find(".CommentFormParent").val($(this).attr('data-comment-id'));
			$(this).parent().after($form);
			$form.slideDown();
		} else if(!$form.is(":visible")) {
			$form.slideDown();
		} else {
			$form.slideUp();
		}
		return false;
	});

	// remember values when comment form submitted
	$(".CommentFormSubmit button").on('click', function() {
		var $this = $(this);
		var $form = $this.closest('form.CommentForm');

		var $wrapStars = $form.find(".CommentFormStarsRequired");
		if($wrapStars.length) {
			var stars = parseInt($wrapStars.find("input").val());
			if(!stars) {
				alert($wrapStars.attr('data-note'));
				return false;
			}
		}
		
		var cite = $form.find(".CommentFormCite input").val();
		var email = $form.find(".CommentFormEmail input").val();
		var $website = $form.find(".CommentFormWebsite input");
		var website = $website.length > 0 ? $website.val() : '';
		var $notify = $form.find(".CommentFormNotify :checked");
		var notify = $notify.length > 0 ? $notify.val() : '';
		if(cite.indexOf('|') > -1) cite = '';
		if(email.indexOf('|') > -1) email = '';
		if(website.indexOf('|') > -1) website = '';
		var cookieValue = cite + '|' + email + '|' + website + '|' + notify;
		CommentFormSetCookie('CommentForm', cookieValue, 0);
	});

	// populate comment form values if they exist in cookie
	var cookieValue = CommentFormGetCookie('CommentForm');
	if(cookieValue.length > 0) {
		var values = cookieValue.split('|');
		var $form = $("form.CommentForm");
		$form.find(".CommentFormCite input").val(values[0]);
		$form.find(".CommentFormEmail input").val(values[1]);
		$form.find(".CommentFormWebsite input").val(values[2]);
		$form.find(".CommentFormNotify :input[value='" + values[3] + "']").attr('checked', 'checked');
	}

	// upvoting and downvoting
	var voting = false;
	$(".CommentActionUpvote, .CommentActionDownvote").on('click', function() {
		if(voting) return false;
		voting = true; 
		var $a = $(this); 
		$.getJSON($a.attr('data-url'), function(data) {
			//console.log(data); 
			if('success' in data) {
				if(data.success) {
					var $votes = $a.closest('.CommentVotes'); 
					$votes.find('.CommentUpvoteCnt').text(data.upvotes);
					$votes.find('.CommentDownvoteCnt').text(data.downvotes); 
					$a.addClass('CommentVoted'); 
				} else if(data.message.length) {
					alert(data.message); 
				}
			} else {
				// let the link passthru to handle via regular pageload rather than ajax
				voting = false;
				return true; 
			}
			voting = false;
		}); 
		return false; 
	});

	if($(".CommentStarsInput").length) {
		CommentFormStars($);
	}
}); 