
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
 */
function CommentFormStars() {

	function decodeEntities(encodedString) {
		if(encodedString.indexOf('&') == -1) return encodedString;
		var textarea = document.createElement('textarea');
		textarea.innerHTML = encodedString;
		return textarea.value;
	}

	function setStars($parent, star) {
		var onClass, offClass, starOn, starOff;
		
		onClass = $parent.attr('data-onclass');
		offClass = $parent.attr('data-offclass');
		starOn = $parent.attr('data-on');

		if(typeof onClass == "undefined") onClass = 'CommentStarOff';
		if(typeof offClass == "undefined") offClass = 'CommentStarOff';
		
		if(typeof starOn != "undefined") {
			starOff = $parent.attr('data-off');
			starOn = decodeEntities(starOn);
			starOff = decodeEntities(starOff);
		} else {
			starOn = '';
			starOff = '';
		}
		
		$parent.children('span').each(function() {
			var val = parseInt(jQuery(this).attr('data-value'));
			if(val <= star) {
				if(starOn.length) jQuery(this).html(starOn);
				jQuery(this).addClass(onClass).removeClass(offClass);
			} else {
				if(starOff.length) jQuery(this).html(starOff);
				jQuery(this).removeClass(onClass).addClass(offClass);
			}
		});
	}

	jQuery('.CommentFormStars input').hide();


	jQuery(document).on('click', '.CommentStarsInput span', function(e) {
		var value = parseInt(jQuery(this).attr('data-value'));
		var $parent = jQuery(this).parent();
		var $input = $parent.prev('input');
		var valuePrev = parseInt($input.val());
		if(value === valuePrev) value = 0; // click on current value to unset
		$input.val(value).attr('value', value); // redundancy intended, val() not working on webkit mobile for some reason
		setStars($parent, value);
		$input.trigger('change');
		return false;
	});

	/*
	// removed because on newer jQuery versions (?) adding a mouseover event here 
	// is preventing the above click event from firing
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
	*/
}

/**
 * Event handler for reply button click in threaded comments
 * 
 */
function CommentActionReplyClick() {
	
	var $this = jQuery(this);
	var $item = $this.closest('.CommentListItem');
	var $form = $this.parent().next('form.CommentForm');
	var commentID = $item.attr('data-comment');

	if($form.length == 0) {
		// if form is not parent's next item, see if we can
		// find it wthin the .CommentListItem somewhere
		$form = $item.find('.CommentForm' + commentID);
	}

	if($form.length == 0) {
		// form does not yet exist for this reply
		// clone the main CommentForm
		$form = jQuery('#CommentForm form').clone().removeAttr('id');
		$form.addClass('CommentForm' + commentID);
		$form.hide().find('.CommentFormParent').val(commentID);
		var $formPlaceholder = $item.find('form:not(.CommentFormReply)').first();
		if($formPlaceholder.length) {
			// use existing <form></form> placed in there as optional target for reply form
			$formPlaceholder.replaceWith($form);
		} else {
			$this.parent().after($form);
		}
		$form.addClass('CommentFormReply');
		if($form.is('form[hidden]')) {
			$form.removeAttr('hidden');
		} else if(!$form.is(':visible')) {
			$form.slideDown();
		}
		$form.trigger('CommentFormReplyAdd');
		$form.trigger('CommentFormReplyShow');
	} else if(!$form.is(':visible')) {
		$form.slideDown();
		$form.trigger('CommentFormReplyShow');
	} else {
		$form.slideUp();
		$form.trigger('CommentFormReplyHide');
	}
	
	return false;
}

/**
 * Event handler when the 'n Replies' link is clicked
 * 
 */
function CommentActionRepliesClick() {
	
	var $this = jQuery(this);
	var href = $this.attr('href');
	var $list = $this.closest('.CommentListItem').find(href); 
	
	if($list.is(':hidden')) {
		$list.removeAttr('hidden');
	} else {
		$list.attr('hidden', true);
	}
	
	return false;
}

/**
 * Event handler for comment submit button click
 * 
 * Remember values when comment form submitted and save in cookie
 * so that other comment forms can be populated with same info to 
 * save them a step. 
 * 
 */
function CommentFormSubmitClick() {
	
	var $this = jQuery(this);
	var $form = $this.closest('form.CommentForm');
	var $wrapStars = $form.find('.CommentFormStarsRequired');
	
	if($wrapStars.length) {
		var stars = parseInt($wrapStars.find('input').val());
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
}

/**
 * Populate cookie values to comment form
 * 
 */
function CommentFormCookies() {

	var $form = jQuery('form.CommentForm');
	if(!$form.length) return;
	
	var cookieValue = CommentFormGetCookie('CommentForm');
	if(cookieValue.length < 1) return;
	
	var values = cookieValue.split('|');
	
	$form.find(".CommentFormCite input").val(values[0]);
	$form.find(".CommentFormEmail input").val(values[1]);
	$form.find(".CommentFormWebsite input").val(values[2]);
	// $form.find(".CommentFormNotify :input[value='" + values[3] + "']").attr('checked', 'checked'); // JQM
	$form.find(".CommentFormNotify :input[value='" + values[3] + "']").prop('checked', true);
}

/**
 * Manage upvotes and downvotes
 * 
 */
function CommentFormUpvoteDownvote() {
	var voting = false;
	jQuery('.CommentActionUpvote, .CommentActionDownvote').on('click', function() {
		if(voting) return false;
		voting = true;
		var $a = jQuery(this);
		jQuery.getJSON($a.attr('data-url'), function(data) {
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
}

function CommentFormInit() {
	jQuery('.CommentActionReply').on('click', CommentActionReplyClick);
	jQuery('.CommentActionReplies').on('click', CommentActionRepliesClick);
	jQuery('.CommentFormSubmit button').on('click', CommentFormSubmitClick);

	CommentFormCookies();
	CommentFormUpvoteDownvote();

	if(jQuery('.CommentStarsInput').length) CommentFormStars();
}

/**
 * Initialize comments form 
 * 
 */
jQuery(document).ready(function() {
	CommentFormInit();
}); 
