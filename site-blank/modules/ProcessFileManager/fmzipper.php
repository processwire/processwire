<?php namespace ProcessFileManager;
if(!defined("PROCESSWIRE")) die();

class FM_Zipper {
  private $zip;

  public function __construct() {
    $this->zip = new \ZipArchive();
  }

  public function create($filename, $files) {
    $res = $this->zip->open($filename, \ZipArchive::CREATE);
    if ($res !== true) {
      return false;
    }
    if (is_array($files)) {
      foreach ($files as $f) {
        if (!$this->addFileOrDir($f)) {
          $this->zip->close();
          return false;
        }
      }
      $this->zip->close();
      return true;
    } else {
      if ($this->addFileOrDir($files)) {
        $this->zip->close();
        return true;
      }
      return false;
    }
  }

  public function unzip($filename, $path) {
    $res = $this->zip->open($filename);
    if ($res !== true) {
      return false;
    }
    if ($this->zip->extractTo($path)) {
      $this->zip->close();
      return true;
    }
    return false;
  }

  private function addFileOrDir($filename) {
    if (is_file($filename)) {
      return $this->zip->addFile($filename);
    } elseif (is_dir($filename)) {
      return $this->addDir($filename);
    }
    return false;
  }

  private function addDir($path) {
    if (!$this->zip->addEmptyDir($path)) {
      return false;
    }
    $objects = scandir($path);
    if (is_array($objects)) {
      foreach ($objects as $file) {
        if ($file != '.' && $file != '..') {
          if (is_dir($path.'/'.$file)) {
            if (!$this->addDir($path.'/'.$file)) {
              return false;
            }
          } elseif (is_file($path.'/'.$file)) {
            if (!$this->zip->addFile($path.'/'.$file)) {
              return false;
            }
          }
        }
      }
      return true;
    }
    return false;
  }
}
?>