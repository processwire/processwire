<script>
  <?php	
  if(strpos($layout, 'sidenav-tree') === 0) {
    echo "if(typeof parent.isPresent != 'undefined'){";
    if(strpos($process, 'ProcessPageList') === 0) {
      echo "parent.hideTreePane();";
    } else {
      echo "if(!parent.isMobileWidth() && parent.treePaneHidden()) parent.showTreePane();";
    }
    if($process == 'ProcessPageEdit' && ($input->get('s') || $input->get('new'))) {
      echo "parent.refreshTreePane(" . ((int) $input->get('id')) . ");";
    }
    echo "}";
  }
  ?>
  ProcessWireAdminTheme.init();
</script>