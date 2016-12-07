/**
 * ProcessWire Page List Process, JQuery Plugin
 *
 * Provides the Javascript/jQuery implementation of the PageList process when used with the JSON renderer
 * 
 * ProcessWire 3.x (development), Copyright 2016 by Ryan Cramer
 * https://processwire.com
 *
 */

function ProcessPageListInit() {
	if(ProcessWire.config.ProcessPageList) {
		$('#' + ProcessWire.config.ProcessPageList.containerID).ProcessPageList(ProcessWire.config.ProcessPageList);
	}
}

$(document).ready(function() {
	ProcessPageListInit();
}); 

(function($) {
	
	$.fn.ProcessPageList = function(customOptions) {

		/**
	 	 * List of options that may be passed to the plugin
		 *
		 */
		var options = {

			// mode: 'select' or 'actions', currently this is automatically determined based on the element the PageList is attached to
			mode: '',		

			// default number of pages to show before pagination
			limit: 35,		

			// the page ID that starts the list
			rootPageID: 0,			 

			// show the page identified by 'rootPageID' ?	
			showRootPage: true,

			// the page ID currently selected
			selectedPageID: 0, 
		
			// id of the admin page
			adminPageID: 2, 
		
			// id of the trash page
			trashPageID: 7, 
		
			// language ID, if applicable
			langID: 0,
			
			// in 'select' mode, allow no value to be selected (to abort a selected value)
			selectAllowUnselect: false,

			// show the 'currently selected' page header? (should be false on multi-selection)
			selectShowPageHeader: true, 

			// show the parent path in the selected page label?
			selectShowPath: true, 

			// the label to click on to change the currently selected page
			selectStartLabel: 'Change', 

			// the label to click on to cancel selecting a page
			selectCancelLabel: 'Cancel',

			// the label to click on to select a given page
			selectSelectLabel: 'Select',

			// the label to click on to unselect a selected page
			selectUnselectLabel: 'Unselect',

			// label used for 'more' in paginated lists
			moreLabel: 'More',
			
			// label used for 'trash' button in Move mode
			trashLabel: 'Trash', 

			// label used for the move instruction
			moveInstructionLabel: "Click and drag to move", 

			// href attribute of 'select' link
			selectSelectHref: '#', 

			// href attribute of 'unselect' link
			selectUnselectHref: '#',
	
			// URL where page lists are loaded from 	
			ajaxURL: ProcessWire.config.urls.admin + 'page/list/', 	

			// URL where page move's should be posted
			ajaxMoveURL: ProcessWire.config.urls.admin + 'page/sort/',

			// pagination number that you want to open (to correspond with openPageIDs)
			openPagination: 0, 

			// IDs of the pages that we want to automatically open (default none) 
			openPageIDs: [],
		
			// pre-rendered data corresponding to openPageIDs, indexed by '_123' where 123 is id
			openPageData: {},

			// speed at which the slideUp/slideDown run (in ms)
			speed: 200,
			
			// whether or not hovering an item reveals its actions
			useHoverActions: false, 
		
			// milliseconds delay between hovering an item and it revealing actions 
			hoverActionDelay: 250,
			
			// milliseconds in fade time to reveal or hide hover actions
			hoverActionFade: 150,
		
			// markup for the spinner used when ajax calls are made
			spinnerMarkup: "<span class='PageListLoading'><i class='ui-priority-secondary fa fa-fw fa-spin fa-spinner'></i></span>",
		
			// session field name that holds page label format, when used
			labelName: '',
		};
	
		// array of "123.0" (page_id.start) that are currently open (used in non-select mode only)
		var currentOpenPageIDs = [];
	
		// true when operations are occurring where we want to ignore clicks
		var ignoreClicks = false;
		
		var isModal = $("body").hasClass("modal");
	
		$.extend(options, customOptions);

		return this.each(function(index) {

			var $container = $(this); 
			var $root; 
			var $loading = $(options.spinnerMarkup); 
			var firstPagination = 0; // used internally by the getPaginationList() function
			var curPagination = 0; // current page number used by getPaginationList() function

			/**
	 		 * Initialize the Page List
			 *
			 */
			function init() {

				$root = $("<div class='PageListRoot'></div>"); 
				
				if($container.is(":input")) {
					options.selectedPageID = $container.val();
					if(!options.selectedPageID.length) options.selectedPageID = 0;
					options.mode = 'select';
					$container.before($root); 
					setupSelectMode();
				} else {
					options.mode = 'actions'; 
					$container.append($root); 
					loadChildren(options.rootPageID > 0 ? options.rootPageID : 1, $root, 0, true); 
					/*
					// longclick to initiate sort, still marinating on whether to support this
					$(document).on('longclick', 'a.PageListPage', function() {
						$(this).parent().find('.PageListActionMove > a').click();
					});
					*/
				}
				
				if(options.useHoverActions) { 
					$root.addClass('PageListUseHoverActions');
					setupHoverActions();
				}
			}

			/**
			 * If hover actions enabled, setup events to hide/show hover actions
			 * 
 			 */
			function setupHoverActions() {
				
				var hoverTimeout = null;
				var hoverOutTimeout = null;
				var $hoveredItem = null;
				
				function showItem($item) {
					var $actions = $item.find('.PageListActions');
					if(!$actions.is(":visible") || $item.hasClass('PageListItemOpen')) {
						// we confirm :visible so that we don't iterfere with admin themes that already
						// make the PageList items visible with css hover states
						$item.addClass('PageListItemHover');
						$actions.css('display', 'inline').css('opacity', 0)
							.animate({opacity: 1.0}, options.hoverActionFade);
					}
				}
				
				function hideItem($item) {
					var $actions = $item.find('.PageListActions');
					$item.removeClass('PageListItemHover');
					if($actions.is(":visible")) { // || $hoveredItem.hasClass('PageListItemOpen')) {
						$actions.animate({opacity: 0}, options.hoverActionFade, function () {
							$actions.hide();
						});
					}
				}

				$(document).on('keydown', '.PageListItem', function(e) { 
					// PR#1 makes page-list keyboard accessible
					e = e || window.event;
					if(e.keyCode == 0 || e.keyCode == 32) {
						// spacebar
						var $actions = $(this).find('.PageListActions');
						if($actions.is(":visible")) {
							$actions.css('display', 'none');
						} else {
							$actions.css('display', 'inline-block');
						}
						return false;
					}
				});
				
				$(document).on('mouseover', '.PageListItem', function(e) {

					if($root.is(".PageListSorting") || $root.is(".PageListSortSaving")) return;
					if(!$(this).children('a:first').is(":hover")) return;
					
					$hoveredItem = $(this);
					//console.log('pageX=' + e.pageX);
					//console.log('offsetX=' + $hoveredItem.offset().left);
					
					//var maxPageX = $(this).children('.PageListNumChildren').offset().left + 100;
					//if(e.pageX > maxPageX) return;
					
					if($hoveredItem.hasClass('PageListItemHover')) return;
					var $item = $(this);
					if(hoverTimeout) clearTimeout(hoverTimeout);
					var delay = options.hoverActionDelay;
					
					
					hoverTimeout = setTimeout(function() {
						if($hoveredItem.attr('class') == $item.attr('class')) {
							if(!$hoveredItem.children('a:first').is(":hover")) return;
							var $hideItems = $(".PageListItemHover");
							showItem($hoveredItem);
							$hideItems.each(function() { hideItem($(this)); });
						}
					}, delay); 

				}).on('mouseout', '.PageListItem', function(e) {
					if($root.is(".PageListSorting") || $root.is(".PageListSortSaving")) return;
					var $item = $(this);
					if($item.hasClass('PageListItemOpen')) return;
					if(!$item.hasClass('PageListItemHover')) return;
					var delay = options.hoverActionDelay * 0.7;
					hoverOutTimeout = setTimeout(function() {
						if($item.is(":hover")) return;
						if($item.attr('class') == $hoveredItem.attr('class')) return;
						hideItem($item);
					}, delay);
				});
			
			}
			
			/**
	 		 * Sets up a mode where the user is given a "select" link for each page, rather than a list of actions
			 * 
			 * When they hit "select" the list collapses and the selected page ID is populated into an input
			 *
			 */
			function setupSelectMode() {

				var $actions = $("<ul></ul>").addClass('PageListActions PageListSelectActions actions'); 
				var $pageLabel = $("<p></p>").addClass("PageListSelectName"); 
				if(options.selectShowPageHeader) $pageLabel.append($loading); 

				var $action = $("<a></a>").addClass("PageListSelectActionToggle").attr('href', '#')
					.text(options.selectStartLabel).click(function() {

					if($(this).text() == options.selectStartLabel) {

						loadChildren(options.rootPageID > 0 ? options.rootPageID : 1, $root, 0, true); 
						$(this).text(options.selectCancelLabel); 

					} else {
						$root.children(".PageList").slideUp(options.speed, function() {
							$(this).remove();
						}); 
						$(this).text(options.selectStartLabel); 
					}
					return false; 
				}); 

				$actions.append($("<li></li>").append($action)); 

				$root.append($("<div></div>").addClass('PageListSelectHeader').append($pageLabel).append($actions)); 

				if(options.selectShowPageHeader) { 
					var ajaxURL = options.ajaxURL + 
						"?id=" + options.selectedPageID + 
						"&render=JSON&start=0&limit=0&lang=" + options.langID + 
						"&mode=" + options.mode;
					if(options.labelName.length) ajaxURL += '&labelName=' + options.labelName;
					$.getJSON(ajaxURL, function(data) {
						var parentPath = '';
						if(options.selectShowPath) {
							parentPath = data.page.path;
							if(parentPath.substring(-1) == '/') parentPath = parentPath.substring(0, parentPath.length-1); 
							parentPath = parentPath.substring(0, parentPath.lastIndexOf('/')+1); 
							parentPath = '<span class="detail">' + parentPath + '</span> ';
						} 
						var label = options.selectedPageID > 0 ? parentPath + data.page.label : '';
						$root.children(".PageListSelectHeader").find(".PageListSelectName").html(label); 
					}); 
				}
			}

			/**
			 * Method that is triggered when the processChildren() method completes
			 *
			 */
			function loaded() {
				ignoreClicks = false;
			}

			/**
			 * Handles pagination of PageList items
			 *
			 * @param int id ID of the page having children to show
			 * @param start Index that we are starting with in the current list
		 	 * @param int limit The limit being applied to the list (items per page)
			 * @param int total The total number of items in the list (excluding any limits)
			 * @return jQuery $list The pagination list ready for insertion
			 *
			 */
			function getPaginationList(id, start, limit, total) {

				// console.log('getPaginationList(id=' + id + ', start=' + start + ", limit=" + limit + ", total=" + total + ')'); 

				var maxPaginationLinks = 9; 
				var numPaginations = Math.ceil(total / limit); 
				curPagination = start >= limit ? Math.floor(start / limit) : 0;

				if(curPagination == 0) {		
					firstPagination = 0; 
				
				} else if((curPagination-maxPaginationLinks+1) > firstPagination) {
					firstPagination = curPagination - Math.floor(maxPaginationLinks / 2); 

				} else if(firstPagination > 0 && curPagination == firstPagination) {
					firstPagination = curPagination - Math.ceil(maxPaginationLinks / 2); 
				}


				// if we're on the last page of pagination links, then make the firstPagination static at the end
				if(firstPagination > numPaginations - maxPaginationLinks) firstPagination = numPaginations - maxPaginationLinks; 

				if(firstPagination < 0) firstPagination = 0;

				var $list = $("<ul></ul>").addClass("PageListPagination").data('paginationInfo', {
					start: start,
					limit: limit,
					total: total
				}); 

				/**
				 * paginationClick is the event function called when an item in the pagination nav is clicked
				 *
				 * It loads the new pages (via loadChildren) and then replaces the old pageList with the new one
				 *
				 */
				var paginationClick = function(e) {
					var $curList = $(this).parents("ul.PageListPagination");
					var info = $curList.data('paginationInfo'); 
					if(!info) return false;
					var start = parseInt($(this).attr('href')) * info.limit;
					if(start === NaN) start = 0;
					var $newList = getPaginationList(id, start, info.limit, info.total);
					var $spinner = $(options.spinnerMarkup);
					var $loading = $("<li>&nbsp;</li>").append($spinner.hide());
					$curList.siblings(".PageList").remove(); // remove any open lists below current
					$curList.replaceWith($newList); 
					$newList.append($loading); 
					$spinner.fadeIn('fast');
					var $siblings = $newList.siblings().css('opacity', 0.5);
					loadChildren(id, $newList.parent(), $(this).attr('href') * info.limit, false, false, true, function() {
						$spinner.fadeOut('fast', function() {
							$loading.remove();
						});
						$newList.parent('.PageList').prev('.PageListItem').data('start', start);
						updateOpenPageIDs();
					}); 
					return false;	
				}
		
				var $separator = null;
				var $blankItem = null;
	
				for(var pagination = firstPagination, cnt = 0; pagination < numPaginations; pagination++, cnt++) {

					var $a = $("<a></a>").html(pagination+1).attr('href', pagination).addClass('ui-state-default'); 
					var $item = $("<li></li>").addClass('PageListPagination' + cnt).append($a); // .addClass('ui-state-default');

					if(pagination == curPagination) {
						//$item.addClass("PageListPaginationCurrent ui-state-focus"); 
						$item.addClass("PageListPaginationCurrent").find("a").removeClass('ui-state-default').addClass("ui-state-active"); 
					}

					$list.append($item); 

					if(!$blankItem) {
						$blankItem = $item.clone().removeClass('PageListPaginationCurrent ui-state-active'); 
						$blankItem.find('a').removeClass('ui-state-active').addClass('ui-state-default');  
					}
					// if(!$blankItem) $blankItem = $item.clone().removeClass('PageListPaginationCurrent').find('a').removeClass('ui-state-focus').addClass('ui-state-default'); 
					if(!$separator) $separator = $blankItem.clone().removeClass('ui-state-default').html("&hellip;"); 
					//if(!$separator) $separator = $blankItem.clone().html("&hellip;"); 

					if(cnt >= maxPaginationLinks && pagination < numPaginations) {
						$lastItem = $blankItem.clone();
						$lastItem.find("a").text(numPaginations).attr('href', numPaginations-1);
						$list.append($separator.clone()).append($lastItem); 
						break;
					} 
				}


				if(firstPagination > 0) {
					$firstItem = $blankItem.clone();
					$firstItem.find("a").text("1").attr('href', '0').click(paginationClick); 
					$list.prepend($separator.clone()).prepend($firstItem); 
				}

				//if(curPagination+1 < maxPaginationLinks && curPagination+1 < numPaginations) {
				if(curPagination+1 < numPaginations) {
					$nextBtn = $blankItem.clone();
					$nextBtn.find("a").html("<i class='fa fa-angle-right'></i>").attr('href', curPagination+1); 
					$list.append($nextBtn);
				}

				if(curPagination > 0) {
					$prevBtn = $blankItem.clone();
					$prevBtn.find("a").attr('href', curPagination-1).html("<i class='fa fa-angle-left'></i>"); 
					$list.prepend($prevBtn); 
				}

				$list.find("a").click(paginationClick)
					.hover(function() { 
						$(this).addClass('ui-state-hover'); 
					}, function() { 
						$(this).removeClass("ui-state-hover"); 
					}); 

				return $list;
			}

			/**
	 		 * Load children via ajax call, attach them to $target and show. 
			 *
			 * @param int id ID of the page having children to show
			 * @param jQuery $target Item to attach children to
		 	 * @param int start If not starting from first item, num of item to start with
			 * @param bool beginList Set to true if this is the first call to create the list
			 * @param bool replace Should any existing list be replaced (true) or appended (false)
			 * @param bool pagination Set to false if you don't want pagination, otherwise leave it out
			 *
			 */
			function loadChildren(id, $target, start, beginList, pagination, replace, callback) {
				
				if(pagination == undefined) pagination = true; 
				if(replace == undefined) replace = false;

				var processChildren = function(data) {

					if(data && data.error) {
						ProcessWire.alert(data.message); 
						$loading.hide();
						ignoreClicks = false;
						return; 
					}

					var $children = listChildren($(data.children)); 
					var nextStart = data.start + data.limit; 
					//var openPageKey = id + '-' + start;
					
					if(data.page.numChildren > nextStart) {
						var $a = $("<a></a>").attr('href', nextStart).data('pageId', id).text(options.moreLabel).click(clickMore); 
						$children.append($("<ul></ul>").addClass('PageListActions actions').append($("<li></li>").addClass('PageListActionMore').append($a)));
					}
					if(pagination && (data.page.numChildren > nextStart || data.start > 0)) {
						$children.prepend(getPaginationList(id, data.start, data.limit, data.page.numChildren));
					}

					$children.hide();

					if(beginList) {
						var $listRoot; 
						$listRoot = listChildren($(data.page)); 
						if(options.showRootPage) $listRoot.children(".PageListItem").addClass("PageListItemOpen"); 
							else $listRoot.children('.PageListItem').hide().parent('.PageList').addClass('PageListRootHidden'); 
						$listRoot.append($children); 
						$target.append($listRoot);

					} else if($target.is(".PageList")) {
					
						var $newChildren = $children.children(".PageListItem, .PageListActions"); 
						if(replace) $target.children(".PageListItem, .PageListActions").replaceWith($newChildren); 
							else $target.append($newChildren); 

					} else {
						$target.after($children); 
					}

					if($loading.parent().is('.PageListRoot')) {
						$loading.hide();
					} else {
						$loading.fadeOut('fast');
					}

					if(replace) {
						$children.show();
						loaded();
						if(callback != undefined) callback();
					} else { 
						$children.slideDown(options.speed, function() {
							loaded();
							if(callback != undefined) callback();
						}); 
					}
					
					$children.prev('.PageListItem').data('start', data.start);

					// if a pagination is requested to be opened, and it exists, then open it
					/*
					if(options.openPagination > 1) {
						//var $a = $(".PageListPagination" + (options.openPagination-1) + ">a");
						var $a = $(".PageListPagination a[href=" + (options.openPagination-1) + "]");
						if($a.size() > 0) {
							$a.click();	
							options.openPagination = 0;
						} else {
							// last pagination link
							$(".PageListPagination9 a").click();
						}
					}
					*/
					$target.removeClass('PageListForceReload'); // if it happens to be present

				}; 

				if(!replace) $target.append($loading.fadeIn('fast')); 
			
		
				var key = id + '-' + start;
				if(typeof options.openPageData[key] != "undefined" 
					&& !$target.hasClass('PageListID7') // trash
					&& !$target.hasClass('PageListForceReload')) {
					processChildren(options.openPageData[key]);
					return;
				} 
				
				// @teppokoivula PR #1052
				var ajaxURL = options.ajaxURL + 
					"?id=" + id + 
					"&render=JSON&start=" + start + 
					"&lang=" + options.langID + 
					"&open=" + options.openPageIDs[0] + 
					"&mode=" + options.mode;
				if(options.labelName.length) ajaxURL += '&labelName=' + options.labelName;
				$.getJSON(ajaxURL)
					.done(function(data, textStatus, jqXHR) {
						processChildren(data);
					})
					.fail(function(jqXHR, textStatus, errorThrown) {
						processChildren({
							error: 1,
							message: !jqXHR.status ? options.ajaxNetworkError : options.ajaxUnknownError
						});
					});
				// end #1052
			}

			/**
			 * Given a list of pages, generates a list of them
			 *
			 * @param jQuery $children
			 *
			 */ 
			function listChildren($children) {

				var $list = $("<div></div>").addClass("PageList");
				var $ul = $list;

				$children.each(function(n, child) {
					$ul.append(listChild(child)); 
				}); 	
				
				addClickEvents($ul);
				
				return $list; 
			}

			/**
			 * 
			 * @param $ul Any element that contains items needing click events attached
			 * 
			 */
			function addClickEvents($ul) {

				$("a.PageListPage", $ul).click(clickChild);
				$(".PageListActionMove a", $ul).click(clickMove);
				$(".PageListActionSelect a", $ul).click(clickSelect);
				$(".PageListTriggerOpen:not(.PageListID1) > a.PageListPage", $ul).click();
				$(".PageListActionExtras > a:not(.clickExtras)", $ul).addClass('clickExtras').on('click', clickExtras);
				
				// if(options.useHoverActions) $(".PageListActionExtras > a", $ul).on('mouseover', clickExtras);
			}

			/**
			 * Given a single page, generates the list item for it
			 *
			 * @param map child
			 *
			 */
			function listChild(child) {
				
				var $li = $("<div></div>").data('pageId', child.id).addClass('PageListItem').addClass('PageListTemplate_' + child.template); 
				var $a = $("<a></a>")
					.attr('href', '#')
					.attr('title', child.path)
					.html(child.label)
					.addClass('PageListPage label'); 

				$li.addClass('PageListID' + child.id); 
				if(child.status == 0) $li.addClass('PageListStatusOff disabled');
				if(child.status & 2048) $li.addClass('PageListStatusUnpublished secondary'); 
				if(child.status & 1024) $li.addClass('PageListStatusHidden secondary'); 
				if(child.status & 512) $li.addClass('PageListStatusTemp secondary'); // typically combined with PageListStatusUnpublished
				if(child.status & 16) $li.addClass('PageListStatusSystem'); 
				if(child.status & 8) $li.addClass('PageListStatusSystem'); 
				if(child.status & 4) $li.addClass('PageListStatusLocked'); 
				if(child.addClass && child.addClass.length) $li.addClass(child.addClass); 
				if(child.type && child.type.length > 0) if(child.type == 'System') $li.addClass('PageListStatusSystem'); 

				$(options.openPageIDs).each(function(n, id) {
					id = parseInt(id);
					if(child.id == id) $li.addClass('PageListTriggerOpen'); 
				}); 

				$li.append($a); 
				var $numChildren = $("<span>" + (child.numChildren ? child.numChildren : '') + "</span>").addClass('PageListNumChildren detail'); 
				$li.append($numChildren); 
		
				if(child.note && child.note.length) $li.append($("<span>" + child.note + "</span>").addClass('PageListNote detail')); 	
				
				var $actions = $("<ul></ul>").addClass('PageListActions actions'); 
				var links = options.rootPageID == child.id ? [] : [{ name: options.selectSelectLabel, url: options.selectSelectHref }]; 
				if(options.mode == 'actions') {
					links = child.actions; 
				} else if(options.selectAllowUnselect) {
					if(child.id == $container.val()) links = [{ name: options.selectUnselectLabel, url: options.selectUnselectHref }]; 
				}

				var $lastAction = null;
				$(links).each(function(n, action) {
					var actionName;
					if(action.name == options.selectSelectLabel) actionName = 'Select';
						else if(action.name == options.selectUnselectLabel) actionName = 'Select'; 
						else actionName = action.cn; // cn = className

					var $a = $("<a></a>").html(action.name).attr('href', action.url);
					if(!isModal) {
						if(action.cn == 'Edit') {
							$a.addClass('pw-modal pw-modal-large pw-modal-longclick');
							$a.attr('data-buttons', '#ProcessPageEdit > .Inputfields > .InputfieldSubmit .ui-button');
						} else if(action.cn == 'View') {
							$a.addClass('pw-modal pw-modal-large pw-modal-longclick');
						}
					}
					if(typeof action.extras != "undefined") {
						$a.data('extras', action.extras);
					}
					var $action = $("<li></li>").addClass('PageListAction' + actionName).append($a);
					if(actionName == 'Extras') $lastAction = $action; 
						else $actions.append($action);
				}); 
				if($lastAction) {
					$actions.append($lastAction);
					$lastAction.addClass('ui-priority-secondary');
				}

				$li.append($actions); 
				return $li;
			}

			/**
			 * Extra actions button click handler
			 * 
			 */
			function clickExtras(e) {

				var $a = $(this);
				var extras = $a.data('extras');
				if(typeof extras == "undefined") return false;
			
				var $li = $a.closest('.PageListItem');
				var $actions = $a.closest('.PageListActions');
				var $lastItem = null;
				var $icon = $a.children('i.fa');
				var $extraActions = $actions.find("li.PageListActionExtra");
			
				/*
				if($extraActions.length && e.type != 'click') {
					// mouseover only opens, but a click is required to close
					return;
				}
				*/
				
				$icon.toggleClass('fa-flip-horizontal');
			
				if($extraActions.length) {
					$extraActions.fadeOut(100, function() {
						$extraActions.remove();
					}); 
					return false;
				}
				
				for(var extraKey in extras) {
					
					var extra = extras[extraKey];
					var $extraLink = $("<a />")
						.addClass('PageListActionExtra PageListAction' + extra.cn)
						.attr('href', extra.url)
						.html(extra.name);
				
					/*
					if(extra.cn == 'Trash') {
						// handler for trash action
						$extraLink.click(function() {
							trashPage($li);
							return false;
						});
						
					} else 
					*/
					if(typeof extra.ajax != "undefined" && extra.ajax == true) {
						// ajax action
						$extraLink.click(function () {
							
							$li.find('.PageListActions').hide();
							var $spinner = $(options.spinnerMarkup);
							var href = $(this).attr('href');
							var actionName = href.match(/[\?&]action=([-_a-zA-Z0-9]+)/)[1];
							var pageID = parseInt(href.match(/[\?&]id=([0-9]+)/)[1]);
							var tokenName = $("#PageListContainer").attr('data-token-name');
							var tokenValue = $("#PageListContainer").attr('data-token-value');
							var postData = {
								action: actionName,
								id: pageID, 
							};
							postData[tokenName] = tokenValue;
							$li.append($spinner);
							
							$.post(href + '&render=json', postData, function (data) {
								
								if (data.success) {
									
									$li.fadeOut('fast', function() {
										
										var addNew = false;
										var removeItem = data.remove;
										var refreshChildren = data.refreshChildren;
										var $liNew = false;
										
										if(typeof data.child != "undefined") {
											// prepare update existing item
											$liNew = listChild(data.child);
										} else if(typeof data.newChild != "undefined") {
											// prepare append new item
											$liNew = listChild(data.newChild);
											addNew = true; 
										}
										
										// display a message for a second to let them know what was done
										if($liNew) {
											var $msg = $("<span />").addClass('notes').html(data.message);
											$msg.prepend("&nbsp;<i class='fa fa-check-square ui-priority-secondary'></i>&nbsp;");
											$liNew.append($msg);
											addClickEvents($liNew);
										}
										
										if(addNew) {
											// append new item
											$spinner.fadeOut('normal', function() { $spinner.remove() }); 
											$liNew.hide();
											$li.after($liNew);
											$liNew.slideDown();
										} else if($liNew) {
											// update existing item
											if($li.hasClass('PageListItemOpen')) $liNew.addClass('PageListItemOpen');
											$li.replaceWith($liNew);
										}
										
										$li.fadeIn('fast', function () {
											// display message for 1 second, then remove
											setTimeout(function () {
												$msg.fadeOut('normal', function () { 
													if(removeItem) {
														var $numChildren = $liNew.closest('.PageList').prev('.PageListItem').children('.PageListNumChildren'); 
														if($numChildren.length) {
															var numChildren = parseInt($numChildren.text());
															if(numChildren > 0) $numChildren.text(numChildren-1); 
														}
														$liNew.next('.PageList').fadeOut('fast');
														$liNew.fadeOut('fast', function() {
															$liNew.remove();
														});
													} else {
														$msg.remove();
													}
												});
											}, 1000);
										});
									
										// refresh the children of the page represented by refreshChildren
										if(refreshChildren) {
											var $refreshParent = $(".PageListID" + refreshChildren);
											if($refreshParent.length) {
												$refreshParent.addClass('PageListForceReload'); 
												var $a = $refreshParent.children('a.PageListPage'); 
												if($refreshParent.hasClass('PageListItemOpen')) {
													$a.click();
													setTimeout(function() { $a.click(); }, 250);
												} else {
													$a.click();
												}
											}
										}
									});
									
								} else {
									// data.success === false, so display error
									$spinner.remove();
									ProcessWire.alert(data.message);
								}
							});
							return false;
						});
					} else {
						// some other action where the direct URL can be used, so we don't need to do anything
					}
					
					var $extraLinkItem = $("<li />").addClass('PageListActionExtra PageListAction' + extra.cn).append($extraLink);
					$extraLink.hide();
					
					if(extra.cn == 'Trash') {
						$li.addClass('trashable');
						// ensure the Trash item is always the last one
						$lastItem = $extraLinkItem;
					} else {
						$actions.append($extraLinkItem);
					}
				}
				
				if($lastItem) $actions.append($lastItem);
				
				$actions.find(".PageListActionExtra a").fadeIn(50, function() {
					$(this).css('display', 'inline-block');
				});
				
				return false;
			}

			/**
			 * Event called when a page label is clicked on
			 *
			 * @param event e
			 *
			 */
			function clickChild(e) {

				var $t = $(this); 
				var $li = $t.parent('.PageListItem'); 
				var id = $li.data('pageId');

				if(ignoreClicks && !$li.hasClass("PageListTriggerOpen")) return false; 

				if($root.is(".PageListSorting") || $root.is(".PageListSortSaving")) {
					return false; 
				}

				if($li.hasClass("PageListItemOpen")) {
					var collapseThis = true;
					if($li.hasClass('PageListID1') && !$li.hasClass('PageListForceReload') && options.mode != 'select') {
						var $collapseItems = $(this).closest('.PageListRoot').find('.PageListItemOpen:not(.PageListID1)');
						if($collapseItems.length) {
							// collapse all open items, except homepage, when homepage link is collapsed
							$root.find('.PageListItemOpen:not(.PageListID1)').each(function() {
								$(this).children('a.PageListPage').click();
							});
							collapseThis = false;
						}
					}
					if(collapseThis) {	
						$li.removeClass('PageListItemOpen').next(".PageList").slideUp(options.speed, function() {
							$(this).remove();
						});
					}
				} else {
					$li.addClass('PageListItemOpen');
					var numChildren = parseInt($li.children('.PageListNumChildren').text()); 
					if(numChildren > 0 || $li.hasClass('PageListForceReload')) {
						ignoreClicks = true; 
						var start = getOpenPageStart(id);
						loadChildren(id, $li, start, false); 
					}
				}
	
				if(options.mode != 'select') {
					setTimeout(function() { updateOpenPageIDs() }, 250); 
				}

				return false;
			}

			/**
			 * Get the pagination "start" index for the given open page ID 
			 * 
			 * @param id
			 * @returns {number}
			 * 
			 */
			function getOpenPageStart(id) {
				var start = 0;
				for(n = 0; n < options.openPageIDs.length; n++) {
					var key = options.openPageIDs[n];
					if(key.indexOf('-') == -1) continue;
					var parts = options.openPageIDs[n].split('-');
					var _id = parseInt(parts[0]);
					if(_id == id) {
						start = parseInt(parts[1]);
						break;
					}
				}
				return start;
			}

			/**
			 * Update the currentOpenPageIDs list and cookie to reflect the current open pages
			 * 
			 */
			function updateOpenPageIDs() {
				currentOpenPageIDs = [];
				$('.PageListItemOpen').each(function() {
					var id = $(this).data('pageId');
					var start = $(this).data('start');
					if(typeof start == "undefined" || start === null) {
						start = 0;
					} else {
						var start = parseInt(start);
					}
					if(jQuery.inArray(id, currentOpenPageIDs) == -1) {
						currentOpenPageIDs.push(id + '-' + start); // id.start
					}
				});
				// console.log(currentOpenPageIDs);
				$.cookie('pagelist_open', currentOpenPageIDs);
			}
			
			/**
			 * Event called when the 'more' action/link is clicked on
			 *
			 * @param event e
			 *
			 */
			function clickMore(e) {

				var $t = $(this); 
				var $actions = $t.parent('li').parent('ul.PageListActions'); 
				var $pageList = $actions.parent('.PageList'); 
				var id = $t.data('pageId');
				var nextStart = parseInt($t.attr('href')); 
		
				loadChildren(id, $pageList, nextStart, false); 
				$actions.remove();
				return false; 
			}

			/**
			 * Event called when the 'move' action/link is clicked on
			 *
			 * @param event e
			 *
			 */
			function clickMove() {

				if(ignoreClicks) return false;
				

				var $t = $(this);
				
				if($(".PageListItem:visible").length == 1) {
					// no other items to sort/move to
					$t.css('text-decoration', 'line-through').addClass('ui-state-disabled');
					return false;
				}
				
				var $li = $t.parent('li').parent('ul.PageListActions').parent('.PageListItem'); 

				// $li.children(".PageListPage").click(); 
				if($li.hasClass("PageListItemOpen")) $li.children(".PageListPage").click(); // @somatonic PR163

				// make an invisible PageList placeholder that allows 'move' action to create a child below this
				$root.find('.PageListItemOpen').each(function() {
					var numChildren = $(this).children('.PageListNumChildren').text(); 
					// if there are children and the next sibling doesn't contain a visible .PageList, then don't add a placeholder
					if(parseInt(numChildren) > 1 && $(this).next().find(".PageList:visible").size() == 0) {
						return; 
					}
					var $ul = $("<div></div>").addClass('PageListPlaceholder').addClass('PageList');
					$ul.append($("<div></div>").addClass('PageListItem PageListPlaceholderItem').html('&nbsp;'));
					$(this).after($ul);
					//$(this).prepend($ul.clone()); 
					//$(this).addClass('PageListItemNoSort'); 
				}); 

				var sortOptions = {
					stop: stopMove, 
					helper: 'PageListItemHelper', 
					items: '.PageListItem:not(.PageListItemOpen)',
					placeholder: 'PageListSortPlaceholder',
					start: function(e, ui) {
						$(".PageListSortPlaceholder").css('width', ui.item.children(".PageListPage").outerWidth() + 'px'); 
					}
				};

				var $sortRoot = $root.children('.PageList').children('.PageList');

				var $cancelLink = $("<a href='#'>" + options.selectCancelLabel + "</a>").click(function() { 
					return cancelMove($li); 
				});
				
				var $actions = $li.children("ul.PageListActions");
				var $moveAction = $("<span class='PageListMoveNote detail'><i class='fa fa-fw fa-sort'></i> " + options.moveInstructionLabel + "<i class='fa fa-fw fa-angle-left'></i></span>");
				$moveAction.append($cancelLink);
			
				$actions.before($moveAction); 
				
				$li.addClass('PageListSortItem'); 
				$li.parent('.PageList').attr('id', 'PageListMoveFrom'); 

				$root.addClass('PageListSorting'); 
				$sortRoot.addClass('PageListSortingList').sortable(sortOptions); 

				return false; 

			}

			/**
			 * Remove everything setup from an active 'move' 
			 *
			 * @param jQuery $li List item that initiated the 'move'
			 * @return bool
			 *
			 */
			function cancelMove($li) {
				var $sortRoot = $root.find('.PageListSortingList'); 
				$sortRoot.sortable('destroy').removeClass('PageListSortingList'); 
				$li.removeClass('PageListSortItem').parent('.PageList').removeAttr('id'); 
				$li.find('.PageListMoveNote').remove();
				$root.find(".PageListPlaceholder").remove();
				$root.removeClass('PageListSorting'); 
				return false; 
			}
			
			/**
			 * Remove everything setup from an active 'move'
			 *
			 * @param jQuery $li List item that initiated the 'move'
			 * @return bool
			 *
			 */
			function trashPage($li) {
				var $trash = $root.find('.PageListID' + options.trashPageID);
				if(!$trash.hasClass('PageListItemOpen')) {
					$root.removeClass('PageListSorting'); 
					$trash.children('a').click();
					$root.addClass('PageListSorting'); 
				}
				var $trashList = $trash.next('.PageList');
				if($trashList.length == 0) {
					$trashList = $("<div class='PageList'></div>");
					$trash.after($trashList);
				}
				$trashList.prepend($li);
				var ui = { item: $li };
				stopMove(null, ui);
			}
			
			/**
			 * Event called when the mouse stops after performing a 'move'
			 *
			 * @param event e
			 * @param jQueryUI ui
			 * @return bool
			 *
			 */
			function stopMove(e, ui) {

				var $li = ui.item; 
				var $a = $li.children('.PageListPage'); 
				var id = parseInt($li.data('pageId')); 
				var $ul = $li.parent('.PageList'); 
				var $from = $("#PageListMoveFrom")

				// get the previous sibling .PageListItem, and skip over the pagination list if it's there
				var $ulPrev = $ul.prev().is('.PageListItem') ? $ul.prev() : $ul.prev().prev();
				var parent_id = parseInt($ulPrev.data('pageId')); 

				// check if item was moved to an invalid spot
				// in this case, a spot between another open PageListItem and it's PageList
				var $liPrev = $li.prev(".PageListItem"); 
				if($liPrev.is(".PageListItemOpen")) return false; 

				// check if item was moved into an invisible parent placeholder PageList
				if($ul.is('.PageListPlaceholder')) {
					// if so, it's no longer a placeholder, but a real PageList
					$ul.removeClass('PageListPlaceholder').children('.PageListPlaceholderItem').remove();
				}

				$root.addClass('PageListSortSaving'); 
				cancelMove($li); 

				// setup to save the change
				$li.append($loading.fadeIn('fast')); 
				var sortCSV = '';
			
				// create a CSV string containing the order of Page IDs	
				$ul.children(".PageListItem").each(function() {
					sortCSV += $(this).data('pageId') + ','; 
				}); 

				var postData = {
					id: id, 
					parent_id: parent_id, 
					sort: sortCSV
				}; 

				postData[$('#PageListContainer').attr('data-token-name')] = $('#PageListContainer').attr('data-token-value'); // CSRF Token

				var success = 'unknown'; 
			
				// save the change	
				$.post(options.ajaxMoveURL, postData, function(data) {

					$loading.fadeOut('fast');

					$a.fadeOut('fast', function() {
						$(this).fadeIn("fast")
						$li.removeClass('PageListSortItem'); 
						$root.removeClass('PageListSorting');
					}); 

					if(data && data.error) {
						ProcessWire.alert(data.message); 
					}

					// if item moved from one list to another, then update the numChildren counts
					if(!$ul.is("#PageListMoveFrom")) {
						// update count where item came from
						var $fromItem = $from.prev(".PageListItem"); 	
						var $numChildren = $fromItem.children(".PageListNumChildren"); 
						var n = $numChildren.text().length > 0 ? parseInt($numChildren.text()) - 1 : 0; 
						if(n == 0) {
							n = '';
							$from.remove(); // empty list, no longer needed
						}
						$numChildren.text(n); 
				
						// update count where item went to	
						var $toItem = $ul.prev(".PageListItem"); 
						$numChildren = $toItem.children(".PageListNumChildren"); 	
						n = $numChildren.text().length > 0 ? parseInt($numChildren.text()) + 1 : 1; 
						$numChildren.text(n); 
					}
					$from.attr('id', ''); 
					$root.removeClass('PageListSortSaving'); 

				}, 'json'); 

				// trigger pageMoved event: @teppokoivula
				$li.trigger('pageMoved'); 

				return true; // whether or not to allow the sort
			}

			/**
			 * Event called when the "select" link is clicked on in select mode
			 *
			 * This also triggers a 'pageSelected' event on the attached text input. 
			 *
			 * @see setupSelectMode()
			 *
			 */
			function clickSelect() {

				var $t = $(this); 
				var $li = $t.parent('li').parent('ul.PageListActions').parent('.PageListItem'); 
				var id = $li.data('pageId');
				var $a = $li.children(".PageListPage"); 
				var title = $a.text();
				var url = $a.attr('title'); 
				var $header = $root.children(".PageListSelectHeader"); 

				if($t.text() == options.selectUnselectLabel) {
					// if unselect link clicked, then blank out the values
					id = 0; 
					title = '';
				}

				if(id != $container.val()) $container.val(id).change();

				if(options.selectShowPageHeader) { 
					$header.children(".PageListSelectName").text(title); 
				}
				
				// trigger pageSelected event	
				$container.trigger('pageSelected', { 
					id: id, 
					url: url, 
					title: title, 
					a: $a 
				}); 	


				$header.find(".PageListSelectActionToggle").click(); // close the list

				// jump to specified anchor, if provided
				if(options.selectSelectHref == '#') return false; 
				return true; 
			}


			// initialize the plugin
			init(); 

		}); 
	};
})(jQuery); 

