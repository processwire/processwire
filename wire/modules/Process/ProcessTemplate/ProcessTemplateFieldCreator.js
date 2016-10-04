// made by adrian
function TemplateFieldAddDialog() {

    var $a = $(this);
    var fieldReady = false;
    var $iframe = pwModalWindow($a.attr('href'), {}, 'large');

    $iframe.load(function() {

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
                        $button.click();
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
                    setTimeout(function() {
                        var newFieldId = $icontents.find("#Inputfield_id:last").val();
                        $iframe.dialog('close');
						var $options = $('#fieldgroup_fields option');
                        var numOptions = $options.length;

                        $options.eq(1).before($("<option></option>").val(newFieldId).text($icontents.find("#Inputfield_name").val()));
                        $('#fieldgroup_fields option[value="'+newFieldId+'"]')
                            .attr('id', 'asm0option'+numOptions)
                            .attr('data-desc', ($icontents.find("#field_label").val()))
                            .attr('data-status', ($icontents.find("#Inputfield_type option:selected").text()));

                        $("#asmSelect0 option").eq(1).before($("<option></option>")
							.val(newFieldId).text($icontents.find("#Inputfield_name").val()));
                        $("#asmSelect0").find('option:selected').removeAttr("selected");
                        $('#asmSelect0 option[value="'+newFieldId+'"]')
                            .attr('rel', 'asm0option'+numOptions)
                            .attr('selected', 'selected')
                            .addClass('asmOptionDisabled')
                            .attr('disabled', 'disabled')
                            .trigger('change')
                            .removeAttr("selected");
                    }, 500);
                }
            };
        }

        $iframe.setButtons(buttons);
    });

    return false;
}



$(document).ready(function() {
    $('#wrap_fieldgroup_fields p.description a').click(TemplateFieldAddDialog);
});