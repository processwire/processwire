function initCodeAce() {
  var editor1 = ace.edit('ace_code');

  var settings = $('#ace_code');

  var theme = settings.attr('data-theme');
  if (theme) editor1.setTheme('ace/theme/' + theme); 

  var keybinding = settings.attr('data-keybinding'); 
  if (keybinding && keybinding != 'none') editor1.setKeyboardHandler('ace/keyboard/' + keybinding);

  var behaviorsenabled = settings.attr('data-behaviors-enabled');
  if (behaviorsenabled == 'on') editor1.setBehavioursEnabled(true);

  var wrapbehaviorsenabled = settings.attr('data-wrap-behaviors-enabled');
  if (wrapbehaviorsenabled == 'on') editor1.setWrapBehavioursEnabled(true);

  var input = $('input[name="ace_code"]');
  editor1.session.setValue(input.val());
  editor1.getSession().on('change', function () {
    input.val(editor1.getSession().getValue());
  });

  $('#ace_type').change(function() {
    var val = $(this).val();
    if (val == 'php') {
      var editorValue = editor1.getSession().getValue();
      if (editorValue.length < 1) editor1.getSession().setValue("<?php\n\n");
    }
    editor1.getSession().setMode('ace/mode/' + val);
  }).change();
}

$(document).ready(function() {
  setTimeout('initCodeAce()', 250);
});