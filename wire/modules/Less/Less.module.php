<?php namespace ProcessWire;
class Less extends WireData implements Module, ConfigurableModule {

  public static function getModuleInfo() {
    return [
      'title' => 'Less',
      'version' => '0.0.1',
      'summary' => 'Less Parser for ProcessWire',
      'autoload' => false,
      'singular' => false,
      'icon' => 'css3',
      'requires' => [],
      'installs' => [],
    ];
  }

  public function init() {
  }

  /**
   * Get instance of less parser
   */
  public function parser($options = null) {
    require_once("vendor/autoload.php");
    $parser = new \Less_Parser($options);
    return $parser;
  }

  /**
   * Save CSS to file
   * @return object
   */
  public function save($file, $css) {
    $file = Paths::normalizeSeparators($file);
    $this->wire->files->filePutContents($file, $css);
    $config = $this->wire()->config;
    return (object)[
      'path' => $file,
      'url' => str_replace($config->paths->root, $config->urls->root, $file),
    ];
  }

  /**
  * Config inputfields
  * @param InputfieldWrapper $inputfields
  */
  public function getModuleConfigInputfields($inputfields) {

    // add settings (eg minify result, recreate on every load for dev, etc)

    return $inputfields;
  }
}
