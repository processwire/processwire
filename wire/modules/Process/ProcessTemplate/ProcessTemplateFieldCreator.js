// made by adrian with fieldset additions by matjazpotocnik 
function TemplateFieldAddDialog() {

    var $a = $(this);
    var fieldReady = false;
    var $iframe = pwModalWindow($a.attr('href'), {}, 'large');

    $iframe.on('load', function() {

		var $button;
        var buttons = [];
        var $icontents = $iframe.contents();
        var n = 0;

        // hide things we don't need in a modal context
        $icontents.find('#breadcrumbs ul.nav, #Inputfield_submit_save_field_copy').hide();

        // copy buttons in iframe to dialog
        $icontents.find("#content form button.ui-button[type=submit]").each(function() {
            var text = $(this).text();
            var skip = false;
			$button = $(this);
            // avoid duplicate buttons
            for(var i = 0; i < buttons.length; i++) {
                if(buttons[i].text == text || text.length < 1) skip = true;
            }
            if(!skip) {
                buttons[n] = {
                    'text': text,
                    'class': ($button.hasClass('ui-priority-secondary') ? 'ui-priority-secondary' : ''),
                    'click': function() {
                        $button.trigger('click');
                        fieldReady = true;
                    }
                };
                n++;
            }
            $button.hide();
        });
		
		// if field has been saved once, now offer a Close & Add button
		if(fieldReady) {
			buttons[n] = {
				'text': $('#fieldgroup_fields').attr('data-closeAddLabel'),
				'class': ($button && $button.hasClass('ui-priority-secondary') ? 'ui-priority-secondary' : ''),
				'click': function() {
					setTimeout(function() { buttonClicked(); }, 500);
				}
			};
		}
	
		$iframe.setButtons(buttons);
		
		/*************************************/
		
		function buttonClicked() {
			var newFieldId = $icontents.find("#Inputfield_id").last().val();
			var $options = $('#fieldgroup_fields option');
			var numOptions = $options.length;
		
			$iframe.dialog('close');
		
			$options.eq(1).before($("<option></option>").val(newFieldId).text($icontents.find("#Inputfield_name").val()));
		
			$('#fieldgroup_fields option[value="'+newFieldId+'"]')
				.attr('id', 'asm0option'+numOptions)
				.attr('data-desc', ($icontents.find("#field_label").val()))
				.attr('data-status', ($icontents.find("#Inputfield_type option:selected").text()));
		
			$("#asmSelect0 option").eq(1).before($("<option></option>")
				.val(newFieldId).text($icontents.find("#Inputfield_name").val()));
			$("#asmSelect0").find('option:selected').prop('selected', false);
			$('#asmSelect0 option[value="'+newFieldId+'"]')
				.attr('rel', 'asm0option'+numOptions)
				.attr('selected', 'selected')
				.addClass('asmOptionDisabled')
				.prop('disabled', 'disabled');
		
			// MP check for Fieldset (Open) and Fieldset in Tab (Open)
			var name = $icontents.find('#Inputfield_name').val();
			var type = $icontents.find('#Inputfield_type option:selected').val();
		
			if(type === 'FieldtypeFieldsetOpen' || type === 'FieldtypeFieldsetTabOpen') {
				// Fieldset added
				name = name + '_END';
				var numOptions1 = numOptions + 1;
				// just an asumption that created _END field has an ID incremented by 1, no way to tell for sure
				// other than querying db via ajax, I think it's not worth it
				var newFieldId1 = (parseInt(newFieldId) + 1) + '';
			
				$options.eq(1).before($('<option></option>')
					.val(newFieldId1)
					.text(name)
				);
			
				var dataStatus = '';
				var dataDesc = '';
			
				$('#fieldgroup_fields option[value="'+newFieldId1+'"]')
					.attr('id', 'asm0option'+numOptions1)
					.attr('data-desc', dataDesc)
					.attr('data-status', dataStatus);
			
				$('#asmSelect0 option').eq(1).after($('<option></option>')
					.val(newFieldId1)
					.text(name)
				);
			
				$('#asmSelect0 option[value="'+newFieldId1+'"]')
					.attr('rel', 'asm0option'+numOptions1)
					.addClass('asmOptionDisabled')
					.prop('disabled', true);
			
				// rebuild event recognized by asmSelect
				$('#fieldgroup_fields').trigger('rebuild');
			}
		
			$('#asmSelect0 option[value="'+newFieldId+'"]')
				.trigger('change')
				.prop('selected', false);
		}
	
	});
	
	return false;
}

$(document).ready(function() {
    $('#wrap_fieldgroup_fields p.description a').on('click', TemplateFieldAddDialog);
});
