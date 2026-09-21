<?php

namespace ProcessWire;

/**
 * @author Bernhard Baumrock, 10.07.2025
 * @license Licensed under MIT
 * @link https://www.baumrock.com
 */
class ProcessJavaScriptHooks extends Process implements ConfigurableModule
{
  public function execute()
  {
    $this->headline('JavaScriptHooks Tests/Examples');
    $this->browserTitle('JavaScriptHooks Tests/Examples');
    /** @var InputfieldForm $form */
    $form = $this->wire->modules->get('InputfieldForm');
    $file = wire()->input->get('file', 'string');

    $form->add([
      'type' => 'markup',
      'label' => 'Navigation',
      'icon' => 'sitemap',
      'value' => $this->renderNav(),
      'collapsed' => $file ? true : false,
    ]);

    if ($file) {
      $form->add([
        'type' => 'markup',
        'label' => 'Example',
        'icon' => 'code',
        'value' => $this->renderExample($file),
      ]);
      $form->add([
        'type' => 'markup',
        'label' => 'Nav',
        'icon' => 'code',
        'value' => $this->renderBottomNav(),
      ]);
    }

    return $form->render();
  }

  private function getFiles(): array
  {
    $files = glob(__DIR__ . '/examples/*.php');
    $files = array_filter($files, function ($file) {
      if (basename($file) == 'assets.php') return false;
      return true;
    });
    $files = array_map(function ($file) {
      return basename($file, '.php');
    }, $files);
    return $files;
  }

  private function nextFile(string $file): string
  {
    $files = $this->getFiles();
    $index = array_search($file, $files);
    if ($index === false || $index === count($files) - 1) return '';
    return $files[$index + 1];
  }

  private function previousFile(string $file): string
  {
    $files = $this->getFiles();
    $index = array_search($file, $files);
    if ($index === false || $index === 0) return '';
    return $files[$index - 1];
  }

  private function renderBottomNav(): string
  {
    $file = wire()->input->get('file', 'string');
    $next = $this->nextFile($file);
    $previous = $this->previousFile($file);
    $out = '<div class="uk-flex uk-flex-between">';
    if ($previous) $out .= "<div><a href='?file={$previous}'><< {$previous}</a></div>";
    else $out .= '<div></div>';
    if ($next) $out .= "<div><a href='?file={$next}'>{$next} >></a></div>";
    else $out .= '<div></div>';
    $out .= '</div>';
    return $out;
  }

  private function renderExample(string $file): string
  {
    return $this->renderFile(__DIR__ . '/examples/' . $file . '.php')
      . $this->renderFile(__DIR__ . '/examples/assets.php');
  }

  private function renderFile(string $file): string
  {
    return wire()->files->render(
      $file,
      [],
      [
        'allowedPaths' => [__DIR__],
      ]
    );
  }

  private function renderNav(): string
  {
    $files = $this->getFiles();
    $table = '<table class="uk-table uk-table-striped uk-table-small uk-margin-remove">';
    foreach ($files as $file) {
      $base = basename($file, '.php');
      $table .= '<tr>';
      $table .= '<td><a href="?file=' . $base . '">' . $base . '</a></td>';
      $table .= '</tr>';
    }
    $table .= '</table>';
    return $table;
  }

  public function getModuleConfigInputfields($inputfields)
  {
    $url = wire()->config->urls->admin . 'setup/javascripthooks/';
    $inputfields->add([
      'type' => 'markup',
      'label' => 'Tests/Examples',
      'value' => "<a href='$url' class='uk-button uk-button-primary'>Open examples</a>",
      'icon' => 'code',
    ]);
    return $inputfields;
  }
}
