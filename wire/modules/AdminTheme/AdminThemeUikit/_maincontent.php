<!-- MAIN CONTENT -->
<main id='main' class='pw-container uk-container uk-container-expand uk-margin uk-margin-large-bottom'>
  <div class='pw-content' id='content'>
    
    <header id='pw-content-head'>
      
      <?php if($layout != 'sidenav' && $layout != 'modal') echo $adminTheme->renderBreadcrumbs(); ?>

      <div id='pw-content-head-buttons' class='uk-float-right uk-visible@s'>
        <?php echo $adminTheme->renderAddNewButton(); ?>
      </div>

      <?php 
      $headline = $adminTheme->getHeadline();
      $headlinePos = strpos($content, ">$headline</h1>");
      if(!$adminTheme->isModal && ($headlinePos === false || $headlinePos < 500)) {
        echo "<h1 id='pw-content-title' class='uk-margin-remove-top'>$headline</h1>";
      }
      ?>
      
    </header>
    
    <div id='pw-content-body'>
      <?php
      echo $page->get('body');
      echo $content;
      echo $adminTheme->renderExtraMarkup('content');
      ?>
    </div>
    
  </div>
</main>