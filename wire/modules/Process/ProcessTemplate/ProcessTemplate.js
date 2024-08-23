
function ProcessTemplateAsmSelect() {

	/**
	 * Identify asmSelect <li> items with classes that indicate how they contribute to Inputfield width rows
	 *
	 * - "rowItem" class is added all items that contribute to a row
	 * - "rowStart" class is added to items that begin a row
	 * - "rowStop" class is added to items that stop/finish a row
	 * - "rowError" class is also added to items that stop a row but row does not add up to 100%
	 *
	 * This method should be called at init, as well as after any sorting or other changes to the asmSelect.
	 * Return value is integer containing the quantity of rows that use columnWidth.
	 *
	 */
	function setupRows() {

		var $inputfield = $('#wrap_fieldgroup_fields');
		var $item = $inputfield.find('.asmListItem').eq(0);
		var $lastItem = null;
		var lastItemWidth = 0;
		var total = 0;
		var numRows = 0;

		do {
			$item.removeClass('rowItem rowStart rowStop rowError');
			var w = parseInt($item.find('.columnWidth').text());
			if(w === 100) {
				// new 100% item
				if(total > 0 && total < 100 && lastItemWidth > 0 && lastItemWidth < 100) {
					// last total was under 100, so last item didn't finish a row
					$lastItem.addClass('rowError');
					if(!$lastItem.hasClass('rowStart')) $lastItem.addClass('rowStop');
				}
				// start new total
				total = 0;
			} else {
				// new partial width item
				if(!total) {
					$item.addClass('rowStart'); // starting a new row
					numRows++;
				}
				$item.addClass('rowItem');
				if(total + w > 100) {
					// existing total plus this item makes it exceed 100% width
					if($lastItem && lastItemWidth < 100) {
						if($lastItem.hasClass('rowStart')) {
							// last item started a single-item row under 100%
							$lastItem.addClass('rowError');
						} else {
							// last item finished a multi-item row that didn't reach 100%
							$lastItem.addClass('rowStop rowError');
						}
					}
					// start our new row
					$item.addClass('rowStart');
					numRows++;
					total = w;
				} else if(total + w == 100) {
					// this item plus previous item(s) add up to a 100% row
					$item.addClass('rowStop');
					total = 0; // new row will start on next iteration
				} else {
					// partial width item in the middle of a row
					total += w;
				}
			}
			$lastItem = $item;
			lastItemWidth = w;
			$item = $item.next('.asmListItem');
		} while($item.length);

		if($lastItem.length && lastItemWidth < 100) {
			$lastItem.addClass('rowStop');
			if(total > 0 && total < 100) {
				$lastItem.addClass('rowError');
			} else {
				$lastItem.removeClass('rowError');
			}
		}

		return numRows;
	}

	/**
	 * Setup the inline column-width adjustment feature in asmList <li> items
	 *
	 */
	function setupColumnWidth() {

		var $percentElement = null;
		var currentPct = 0;
		var lastPageX = 0;
		var lastPageY = 0;
		var mousingActive = false;
		var isDblClick = false;
		var snapWithin = 9;
		var snapWidth = 0;
		var useSnapWidth = false;
		var useRails = true;

		function asmListItem($item) {
			if(!$item.hasClass('asmListItem')) $item = $item.closest('.asmListItem');
			return $item;
		}

		function columnWidthItem($item) {
			if($item.hasClass('columnWidth')) return $item;
			return $item.find('.columnWidth');
		}

		function getColumnWidth($item) {
			$item = columnWidthItem($item);
			return parseInt($item.text());
		}
		
		/**
		 * Given a width (10-100), pad it to the nearest 5% or nearest predefined (33%, etc.)
		 * 
		 * @param width
		 * @returns {number|*}
		 * 
		 */
		function getRailWidth(width) {
			if(width >= 96) return 100;
			if(width <= 10) return 10;
			if(width == 33 || width == 34 || width == 66) return width;
			width = width.toString();
			var w1 = parseInt(width.substring(0,1));
			var w2 = parseInt(width.substring(1));
			if(w2 >= 7) {
				w1++; w2 = 0;
			} else if(w2 >= 4) {
				w2 = 5;
			} else if(w2 >= 0) {
				w2 = 0;
			}
			return parseInt(w1.toString() + w2.toString());
		}

		// deprecated
		function getSnapWidth($item) {
			$item = asmListItem($item);
			var rowWidth = getRowWidth($item);
			var itemWidth = getColumnWidth($item);
			var snap;
			if(rowWidth == 100) {
				snap = itemWidth;
			} else {
				snap = 100 - (rowWidth - itemWidth);
			}
			return snap;
		}

		function getRowStartItem($item) {
			$item = asmListItem($item);
			if(!$item.hasClass('rowItem') || $item.hasClass('rowStart')) return $item;
			var $prevItem = $item;
			do {
				$prevItem = $prevItem.prev('.rowItem');
			} while($prevItem.length && !$prevItem.hasClass('rowStart'));
			return $prevItem.length ? $prevItem : $item;
		}

		function getRowStopItem($item) {
			$item = asmListItem($item);
			if(!item.hasClass('rowItem') || $item.hasClass('rowStop')) return $item;
			var $nextItem = $item;
			do {
				$nextItem = $nextItem.next('.rowItem');
			} while($nextItem.length && !$nextItem.hasClass('rowStop'));
			return $nextItem.length ? $nextItem : $item;
		}

		function getRowWidth($item) {
			if(!$item.hasClass('rowItem')) return 100;
			$item = getRowStartItem($item);
			var total = getColumnWidth($item);
			var $nextItem = $item;
			var w = 0;
			do {
				$nextItem = $nextItem.next('.rowItem');
				if(!$nextItem.length || $nextItem.hasClass('rowStart')) break;
				w = getColumnWidth($nextItem);
				if(total + w > 100) break;
				total += w;
			} while(total < 100 && !$nextItem.hasClass('rowStop'));
			return total;
		}

		function setColumnWidth($item, columnWidth) {
			var $parent;
			if($item.hasClass('columnWidth')) {
				$parent = $item.closest('.asmListItem');
			} else {
				$parent = $item;
				$item = $item.find('.columnWidth');
			}
			if(useSnapWidth && snapWidth > 0) {
				if(columnWidth > snapWidth && columnWidth - snapWidth <= snapWithin) {
					columnWidth = snapWidth;
				} else if(columnWidth < snapWidth && snapWidth - columnWidth <= snapWithin) {
					columnWidth = snapWidth;
				}
			}
			if(useRails) columnWidth = getRailWidth(columnWidth);
			var pct = parseInt(columnWidth) + '%';
			$item.text(pct);
			var $columnWidthBar = $parent.find('.columnWidthBar');
			var $columnWidthBarPct = $columnWidthBar.children('.columnWidthBarPct');
			$columnWidthBar.css('transition', 'width 0.1s');// ease-in');
			$columnWidthBar.css('width', columnWidth + '%')
			$columnWidthBarPct.text(pct);
			if(columnWidth > 95) {
				$columnWidthBarPct.text('');
			} else {
				$columnWidthBarPct.text(pct);
			}
		}

		function saveColumnWidth($item) {
			var columnWidth = getColumnWidth($item);
			var $li = $item.hasClass('asmListItem') ? $item : $item.closest('.asmListItem');
			var url = './saveProperty';
			var data = {
				id: $('#Inputfield_id').val(), 
				property: 'columnWidth',
				columnWidth: columnWidth,
				field: parseInt($li.find('.columnWidth').attr('data-field'))
			};
			var $csrf = $('input._post_token');
			data[$csrf.attr('name')] = $csrf.val();

			$.post(url, data, function(result) {
				if(result.success) {
					if(result.value != columnWidth) setColumnWidth($item, result.value);
				}
			}, 'json');
		}

		function startColumnWidthBar($item) {
			if(!$item.hasClass('asmListItem')) $item = $item.closest('.asmListItem');
			if(isDblClick || !mousingActive) return;
			var $columnWidthBar = $item.find('.columnWidthBar');
			if($columnWidthBar.length) $columnWidthBar.remove();
			var pct = getColumnWidth($item) + '%';
			var $columnWidthBarPct = $("<span />").addClass('columnWidthBarPct').text(pct);
			$columnWidthBar = $('<div />').addClass('columnWidthBar').append($columnWidthBarPct);
			$columnWidthBar.appendTo($item);
			$columnWidthBar.css('width', pct);
		}

		function stopColumnWidthBar($item) {
			if(!$item.hasClass('asmListItem')) $item = $item.closest('.asmListItem');
			var $columnWidthBar = $item.find('.columnWidthBar');
			if(!$columnWidthBar.length) return;
			$columnWidthBar.remove();
		}

		function setActive($item, active) {
			var $list = $item.closest('.ui-sortable');
			if(!$item.hasClass('columnWidth')) $item = $item.find('.columnWidth');
			var $parent = $item.closest('.asmListItem');

			if(active) {
				if(mousingActive) return;
				mousingActive = true;
				$item.addClass('columnWidthActive');
				$('body').addClass('columnWidthActive');
				$item.siblings('.fieldType, .fieldInfo').css('opacity', 0.3);
				$list.sortable('disable');
				if(useSnapWidth) snapWidth = getSnapWidth($parent);
				startColumnWidthBar($item);
			} else {
				if(!mousingActive) return;
				mousingActive = false;
				stopColumnWidthBar($item);
				snapWidth = 0;
				$item.removeClass('columnWidthActive');
				$('body').removeClass('columnWidthActive');
				$item.siblings('.fieldType, .fieldInfo').css('opacity', 1.0);
				$list.sortable('enable');
			}
		}
	
		// returns all widths in an array. also disables the useRails option when 
		// any existing width does not line up with our predefined rail size
		function getAllWidths($inputfield) {
			var widths = [];
			$inputfield.find('.columnWidth').each(function() {
				var width = parseInt($(this).text());
				widths.push(width);
				if(useRails && width != 66 && width != 33 && width != 34) {
					if(width % 5 !== 0) useRails = false;
				}
			});
			useSnapWidth = !useRails;
			return widths;
		}

		var mouseMove = function(e) {
			if(lastPageX && lastPageY) {
				var diffX = (e.pageX - lastPageX) / 3;
				var diffY = (e.pageY - lastPageY);
				var diff = Math.abs(diffX) >= Math.abs(diffY) ? diffX : (diffY * -1);
				if(diff === 0) return;
				var pct = currentPct;
				var detectMax = 10;
				var detectMin = 1;
				var d = Math.abs(diff);
				if(useRails && d >= detectMax) {
					var moveAmt = 5;
					if(diff > 0 && pct < 100) pct += diff > detectMax ? moveAmt : 1;
					if(diff < 0 && pct > 10) pct -= d > detectMax ? moveAmt : 1;
				} else if(d >= detectMin) {
					if(diff > 0 && pct < 100) pct++;
					if(diff < 0 && pct > 10) pct--;
				}
				if(pct != currentPct) {
					setColumnWidth($percentElement, pct);
					currentPct = pct;
				}
			}
			lastPageX = e.pageX;
			lastPageY = e.pageY;
		};

		var mouseUp = function() {
			$(document).off('mouseup', mouseUp);
			$(document).off('mousemove', mouseMove);
			saveColumnWidth($percentElement);
			setActive($percentElement, false);
			setupRows();
		};
		
		var mouseDblClick = function($percentElement) {
			var $editLink = $percentElement.closest('li').find('.asmListItemEdit').eq(0).children('a');
			var href = $editLink.attr('href'); // geet the url to update
			$editLink.attr('href', href + '#find-columnWidth'); // update url to find the columnWidth inputfield
			setTimeout(function() { $editLink.attr('href', href); }, 1000); // restore previous url
			$editLink.trigger('click');
		};
		
		var isMousedown = false;
		
		var mouseDown = function(e) {
			$percentElement = $(this);
			if(isMousedown) {
				isMousedown = false;
				mouseDblClick($percentElement);
				$percentElement.trigger('mouseup'); // prevents a asm sort from starting
				return;
			}
			isMousedown = true;
			setTimeout(function() { isMousedown = false; }, 500);
			
			if($percentElement.hasClass('columnWidthOff')) return false;
			setActive($percentElement, true);
			currentPct = getColumnWidth($percentElement);
			$(document).on('mouseup', mouseUp);
			$(document).on('mousemove', mouseMove);
		};

		var mouseOut = function(e) {
			if(mousingActive) return;
			$(this).closest('.ui-sortable').sortable('enable');
		};

		var mouseOver = function(e) {
			if(mousingActive) return;
			$(this).closest('.ui-sortable').sortable('disable');
		};

		/*
		var dblClick = function(e) {
			var $t = $(this);
			isDblClick = true;
			console.log('dblclick');
			var $editLink = $t.closest('li').find('.asmListItemEdit').eq(0).children('a');
			$editLink.trigger('click');
			snapWidth = getSnapWidth($t);
			if(snapWidth) {
				setColumnWidth($t, snapWidth);
				setupRows()
				saveColumnWidth($t);
			}
			isDblClick = false;
		};
		 */

		/*
		var toggleRequired = function() {
			var $li = $(this).closest('.asmListItem');
			var $a = $li.find('.asmListItemEdit').eq(0).children('a');
			var url = $a.attr('href');
			var $inputRequired = $li.find('.inputRequired');
			var value = $inputRequired.text();
			var data = {
				save_property: 'required',
				required: (value.indexOf('*') > -1 ? 0 : 1)
			};
			$inputRequired.html("<i class='fa fa-spin fa-spinner fa-fw'></i>");

			$.post(url, data, function(result) {
				$inputRequired.text('');
				if(result.success) {
					$inputRequired.text(result.value);
				}
			}, 'json');
		};
		*/

		var $inputfield = $('#wrap_fieldgroup_fields'); // Inputfield wrapping element
		var $select = $('#fieldgroup_fields'); // original (hidden) select
	
		$inputfield
			.on('mousedown', '.columnWidth', mouseDown)
			.on('mouseover', '.columnWidth', mouseOver)
			.on('mouseout', '.columnWidth', mouseOut)
			.on('asm-ready', function() {
				// triggered by manual inline call to ProcessTemplateInitFields() function
				setupRows()
				// sets the useRails toggle
				getAllWidths($inputfield); 
			})
			.on('asmItemUpdated', function() {
				setupRows();
			}); 

		$select.on('change', function(e, eventData) {

			// eventData is provided by a change event triggered from asmSelect plugin after a sort or select event
			if(typeof eventData == "undefined") return;
			if(typeof eventData.type == "undefined") return;
			if(eventData.type === 'add') $inputfield.addClass('field-added'); 
			if(eventData.type != 'sort') return;

			// update row identifications after any changes
			setupRows();

			// save changes to order
			var value = $(this).val();
			var url = './saveProperty';
			var data = {
				id: $('#Inputfield_id').val(),
				property: 'fieldgroup_fields',
				fieldgroup_fields: value,
				_fieldgroup_fields_changed: 'changed'
			};
			var $csrf = $('input._post_token');
			data[$csrf.attr('name')] = $csrf.val();

			$.post(url, data, function(result) {
				// we just silently post
			}, 'json');
		});
	}
	
	setupColumnWidth();
}

/********************************************************************/

function ProcessTemplate() {

	/**
	 * Access/Roles
	 * 
	 */
	function setupAccessTab() {
		
		var redirectLoginClick = function() {
			if($("#redirectLogin_-1:checked").length > 0) {
				$("#wrap_redirectLoginURL").slideDown();
			} else {
				$("#wrap_redirectLoginURL").hide();
			}
		}

		var adjustAccessFields = function() {

			var items = [ '#wrap_redirectLogin', '#wrap_guestSearchable' ];

			if($("#roles_37").is(":checked")) {
				$("#wrap_redirectLoginURL").hide();

				$(items).each(function(key, value) {
					var $item = $(value);
					if($item.is(".InputfieldStateCollapsed")) {
						$item.hide();
					} else {
						$item.slideUp();
					}
				});

				$('input.viewRoles').prop('checked', true);

			} else {

				$(items).each(function(key, value) {
					var $item = $(value);
					if($item.is(":visible")) return;
					$item.slideDown("fast", function() {
						if(!$item.is(".InputfieldStateCollapsed")) return;
						$item.find(".InputfieldStateToggle").trigger('click');
					});
				});
				redirectLoginClick();
			}

		};

		$("#wrap_useRoles input").on('click', function() {
			if($("#useRoles_1:checked").length > 0) {
				$("#wrap_redirectLogin").hide();
				$("#wrap_guestSearchable").hide();
				$("#useRolesYes").slideDown(400, function(){ $(this).css('overflow','visible') });
				$("input.viewRoles").prop('checked', true);
			} else {
				$("#useRolesYes").slideUp();
				$("#accessOverrides:visible").slideUp();
			}
		});

		if($("#useRoles_0:checked").length > 0) {
			$("#useRolesYes").hide();
			$("#accessOverrides").hide();
		}


		$("#roles_37").on('click', adjustAccessFields);
		$("input.viewRoles:not(#roles_37)").on('click', function() {
			// prevent unchecking 'view' for other roles when 'guest' role is checked
			var $t = $(this);
			if($("#roles_37").is(":checked")) return false;
			return true;
		});

		// when edit checked or unchecked, update the createRoles to match since they are dependent
		var editRolesClick = function() {

			var $editRoles = $("#roles_editor input.editRoles");
			var numChecked = 0;

			$editRoles.each(function() {
				var $t = $(this);
				if($t.is(":disabled")) return false;

				var $createRoles = $("input.createRoles[value=" + $t.attr('value') + "]");

				if($t.is(":checked")) {
					numChecked++;
					$createRoles.prop('disabled', false);
				} else {
					$createRoles.prop('checked', false).prop('disabled', true);
				}
			});

			if(numChecked) {
				$("#accessOverrides").slideDown();
			} else {
				$("#accessOverrides").hide();
			}

			return true;
		};

		var editOrAddClick = function() {
			var numChecked = 0;
			$("#roles_editor input.editRoles").each(function() {
				if(!$(this).is(":disabled") && $(this).is(":checked")) numChecked++;
			});
			$("#roles_editor input.addRoles").each(function() {
				if(!$(this).is(":disabled") && $(this).is(":checked")) numChecked++;
			});
			numChecked > 0 ? $("#wrap_noInherit").slideDown() : $("#wrap_noInherit").hide();
		};

		$("#roles_editor input.editRoles").on('click', editRolesClick);
		$("#roles_editor input.editRoles, #roles_editor input.addRoles").on('click', editOrAddClick);

		editRolesClick();
		editOrAddClick();

		$("#wrap_redirectLogin input").on('click', redirectLoginClick);

		adjustAccessFields();
		redirectLoginClick();
	}

	/**
	 * Export and import functions
	 * 
	 */
	function setupImportExport() {
		$("#export_data").on('click', function() { $(this).select(); });

		$(".import_toggle input[type=radio]").on('change', function() {
			var $table = $(this).parents('p.import_toggle').next('table');
			var $fieldset = $(this).closest('.InputfieldFieldset');
			if($(this).is(":checked") && $(this).val() == 0) {
				$table.hide();
				$fieldset.addClass('ui-priority-secondary');
			} else {
				$table.show();
				$fieldset.removeClass('ui-priority-secondary');
			}
		}).trigger('change');

		$("#import_form table td:not(:first-child)").each(function() {
			var html = $(this).html();
			var refresh = false;
			if(html.substring(0,1) == '{') {
				html = '<pre>' + html + '</pre>';
				html = html.replace(/<br>/g, "");
				refresh = true;
			}
			if(refresh) $(this).html(html);
		});
	}

	/**
	 * Initialization 
	 * 
	 */
	function init() {
		$("#wrap_filter_system input").on('click', function() {
			$(this).parents("form").trigger('submit');
		});

		$("#filter_field").on('change', function() {
			$(this).parents("form").trigger('submit');
		});

		setupAccessTab();

		// instantiate the WireTabs
		var $templateEdit = $("#ProcessTemplateEdit");
		if($templateEdit.length > 0) {
			$templateEdit.find('script').remove();
			$templateEdit.WireTabs({
				items: $(".Inputfields li.WireTab"),
				id: 'TemplateEditTabs',
				skipRememberTabIDs: ['WireTabDelete']
			});
		}

		setupImportExport();

		$("#fieldgroup_fields").on('change', function() {
			$("#_fieldgroup_fields_changed").val('changed');
		});
	}
	
	init();
}

function ProcessTemplateInitFields() {
	$('#wrap_fieldgroup_fields').trigger('asm-ready');
}

$(document).ready(function() {
	ProcessTemplate();
	ProcessTemplateAsmSelect();
}); 
