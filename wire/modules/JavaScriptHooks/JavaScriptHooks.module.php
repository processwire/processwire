<?php

namespace ProcessWire;

/**
 * @author Bernhard Baumrock, 10.07.2025
 * @license Licensed under MIT
 * @link https://www.baumrock.com
 */
class JavaScriptHooks extends WireData implements Module, ConfigurableModule
{
  public function ready()
  {
    $this->loadJsFile();
    $this->devtools();
  }

  private function code(string $file): string
  {
    $markup = wire()->files->render(__DIR__ . "/examples/{$file}");
    return "<pre class='uk-margin-remove-top'><code>"
      . wire()->sanitizer->entities($markup)
      . "</code></pre>"
      . $markup;
  }

  /**
   * Auto-compile all JS assets via RockDevTools
   * @return void
   * @throws WireException
   */
  private function devtools(): void
  {
    if (!wire()->config->rockdevtools) return;
    try {
      rockdevtools()
        ->assets()
        ->js()
        ->add(__DIR__ . '/src/**.js')
        ->save(
          __DIR__ . '/dst/JavaScriptHooks.min.js',
          // minify: false,
        );
    } catch (\Throwable $th) {
      bd($th->getMessage());
    }
  }

  /**
   * Config inputfields
   * @param InputfieldWrapper $inputfields
   */
  public function getModuleConfigInputfields($inputfields)
  {
    $inputfields->add([
      'type' => 'markup',
      'label' => 'Tests/Examples',
      'value' => 'Please check out the module <a href="./edit?name=ProcessJavaScriptHooks">ProcessJavaScriptHooks</a> for examples.',
      'icon' => 'code',
    ]);
    return $inputfields;
  }

  private function loadJsFile(): void
  {
    if (wire()->config->ajax) return;
    if (wire()->config->external) return;
    $url = wire()->config->urls($this);
    wire()->config->scripts->add($url . "dst/JavaScriptHooks.min.js");
  }
}
