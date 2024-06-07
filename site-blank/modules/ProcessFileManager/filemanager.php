<?php namespace ProcessFileManager;
if(!defined("PROCESSWIRE")) die();

class FileManager {
  public $fm_iconv_input_enc = 'CP1252';
  public $fm_datetime_format = 'd.m.y H:i:s';
  public $fm_datetime_zone = 'UTC';
  public $use_ace = true;
  public $fm_self_url;

  public $fm_ace_theme = 'monokai';
  public $fm_ace_keybinding = 'none';
  public $fm_ace_height = 400;
  public $fm_ace_behaviors_enabled = 'off';
  public $fm_ace_wrap_behaviors_enabled = 'off';

  private $fm_image_exts = array('ico', 'gif', 'jpg', 'jpeg', 'jpc', 'jp2', 'jpx', 'xbm', 'wbmp', 'png', 'bmp', 'tif', 'tiff', 'psd');
  private $fm_audio_exts = array('wav', 'mp3', 'ogg', 'm4a');
  private $fm_video_exts = array('webm', 'mp4', 'm4v', 'ogm', 'ogv', 'mov');
  private $fm_text_exts = array('txt', 'css', 'ini', 'conf', 'log', 'htaccess', 'passwd', 'ftpquota', 'sql', 'js', 'json', 'sh', 'config', 'php', 'php4', 'php5', 'phps', 'phtml', 'htm', 'html', 'shtml', 'xhtml', 'xml', 'xsl', 'm3u', 'm3u8', 'pls', 'cue', 'eml', 'msg', 'csv', 'bat', 'twig', 'tpl', 'md', 'gitignore', 'less', 'sass', 'scss', 'c', 'cpp', 'cs', 'py', 'map', 'lock', 'dtd', 'svg', 'pas');
  private $fm_text_mimes = array('application/xml', 'application/javascript', 'application/x-javascript', 'image/svg+xml', 'message/rfc822');

  private $fm_root_path;
  private $fm_root_url;
  private $fm_is_win;
  private $fm_path;

  function __construct() {
    $root_path = $_SERVER['DOCUMENT_ROOT'];
    $http_host = $_SERVER['HTTP_HOST'];
    $is_https = isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] == 'on' || $_SERVER['HTTPS'] == 1) || isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https';
    $root_path = rtrim($root_path, '\\/');
    $root_path = str_replace('\\', '/', $root_path);
    $this->fm_root_path = $root_path;
    $this->fm_root_url = ($is_https ? 'https' : 'http').'://'.$http_host;
    $this->fm_self_url = ($is_https ? 'https' : 'http').'://'.$http_host.$_SERVER['PHP_SELF'];
    $this->fm_is_win = DIRECTORY_SEPARATOR == '\\';
    $p = isset($_GET['p']) ? $_GET['p'] : (isset($_POST['p']) ? $_POST['p'] : '');
    $p = $this->fm_clean_path($p);
    $this->fm_path = $p;
  }

  function show() {
    if (!isset($_GET['p'])) {
      $this->fm_redirect($this->fm_self_url.'?p=');
    }

    if (isset($_GET['del'])) {
      $del = $_GET['del'];
      $del = $this->fm_clean_path($del);
      $del = str_replace('/', '', $del);
      if ($del != '' && $del != '..' && $del != '.') {
        $path = $this->fm_root_path;
        if ($this->fm_path != '') {
          $path .= '/'.$this->fm_path;
        }
        $is_dir = is_dir($path . '/' . $del);
        if ($this->fm_rdelete($path . '/' . $del)) {
          $msg = $is_dir ? 'Folder <b>%s</b> deleted' : 'File <b>%s</b> deleted';
          $this->fm_set_msg(sprintf($msg, $this->fm_enc($del)));
        } else {
          $msg = $is_dir ? 'Folder <b>%s</b> not deleted' : 'File <b>%s</b> not deleted';
          $this->fm_set_msg(sprintf($msg, $this->fm_enc($del)), 'error');
        }
      } else {
        $this->fm_set_msg('Wrong file or folder name', 'error');
      }
      $this->fm_redirect($this->fm_self_url.'?p='.$this->fm_urlencode($this->fm_path));
    }

    if (isset($_GET['newfolder'])) {
      $new = strip_tags($_GET['newfolder']);
      $new = $this->fm_clean_path($new);
      $new = str_replace('/', '', $new);
      if ($new != '' && $new != '..' && $new != '.') {
        $path = $this->fm_root_path;
        if ($this->fm_path != '') {
          $path .= '/'.$this->fm_path;
        }
        if ($this->fm_mkdir($path.'/'.$new, false) === true) {
          $this->fm_set_msg(sprintf('Folder <b>%s</b> created', $this->fm_enc($new)));
        } elseif (fm_mkdir($path.'/'.$new, false) === $path.'/'.$new) {
          $this->fm_set_msg(sprintf('Folder <b>%s</b> already exists', $this->fm_enc($new)), 'alert');
        } else {
          $this->fm_set_msg(sprintf('Folder <b>%s</b> not created', $this->fm_enc($new)), 'error');
        }
      } else {
        $this->fm_set_msg('Wrong folder name', 'error');
      }
      $this->fm_redirect($this->fm_self_url.'?p='.$this->fm_urlencode($this->fm_path));
    }

    if (isset($_GET['newfile'])) {
      $new = strip_tags($_GET['newfile']);
      $new = $this->fm_clean_path($new);
      $new = str_replace('/', '', $new);
      if ($new != '' && $new != '..' && $new != '.') {
        $path = $this->fm_root_path;
        if ($this->fm_path != '') {
          $path .= '/'.$this->fm_path;
        }
        if (file_exists($path.'/'.$new)) {
          $this->fm_set_msg(sprintf('File <b>%s</b> already exists', $this->fm_enc($new)), 'alert');
        } else if (file_put_contents($path.'/'.$new, '') === false) {
          $this->fm_set_msg(sprintf('File <b>%s</b> not created', $this->fm_enc($new)), 'error');
        } else {
          $this->fm_set_msg(sprintf('File <b>%s</b> created', $this->fm_enc($new)));
        }
      } else {
        $this->fm_set_msg('Wrong file name', 'error');
      }
      $this->fm_redirect($this->fm_self_url.'?p='.$this->fm_urlencode($this->fm_path));
    }

    if (isset($_GET['copy'], $_GET['finish'])) {
      $copy = $_GET['copy'];
      $copy = $this->fm_clean_path($copy);
      if ($copy == '') {
        $this->fm_set_msg('Source path not defined', 'error');
        $this->fm_redirect($this->fm_self_url.'?p='.$this->fm_urlencode($this->fm_path));
      }
      $from = $this->fm_root_path.'/'.$copy;
      $dest = $this->fm_root_path;
      if ($this->fm_path != '') {
        $dest .= '/'.$this->fm_path;
      }
      $dest .= '/'.basename($from);
      $move = isset($_GET['move']);
      if ($from != $dest) {
        $msg_from = trim($this->fm_path.'/'.basename($from), '/');
        if ($move) {
          $rename = $this->fm_rename($from, $dest);
          if ($rename) {
            $this->fm_set_msg(sprintf('Moved from <b>%s</b> to <b>%s</b>', $this->fm_enc($copy), $this->fm_enc($msg_from)));
          } elseif ($rename === null) {
            $this->fm_set_msg('File or folder with this path already exists', 'alert');
          } else {
            $this->fm_set_msg(sprintf('Error while moving from <b>%s</b> to <b>%s</b>', $this->fm_enc($copy), $this->fm_enc($msg_from)), 'error');
          }
        } else {
          if ($this->fm_rcopy($from, $dest)) {
            $this->fm_set_msg(sprintf('Copyied from <b>%s</b> to <b>%s</b>', $this->fm_enc($copy), $this->fm_enc($msg_from)));
          } else {
            $this->fm_set_msg(sprintf('Error while copying from <b>%s</b> to <b>%s</b>', $this->fm_enc($copy), $this->fm_enc($msg_from)), 'error');
          }
        }
      } else {
        $this->fm_set_msg('Paths must be not equal', 'alert');
      }
      $this->fm_redirect($this->fm_self_url.'?p='.$this->fm_urlencode($this->fm_path));
    }

    if (isset($_POST['file'], $_POST['copy_to'], $_POST['finish'])) {
      $path = $this->fm_root_path;
      if ($this->fm_path != '') {
        $path .= '/'.$this->fm_path;
      }
      $copy_to_path = $this->fm_root_path;
      $copy_to = $this->fm_clean_path($_POST['copy_to']);
      if ($copy_to != '') {
        $copy_to_path .= '/'.$copy_to;
      }
      if ($path == $copy_to_path) {
        $this->fm_set_msg('Paths must be not equal', 'alert');
        $this->fm_redirect($this->fm_self_url.'?p='.$this->fm_urlencode($this->fm_path));
      }
      if (!is_dir($copy_to_path)) {
        if (!$this->fm_mkdir($copy_to_path, true)) {
          $this->fm_set_msg('Unable to create destination folder', 'error');
          $this->fm_redirect($this->fm_self_url.'?p='.$this->fm_urlencode($this->fm_path));
        }
      }
      $move = isset($_POST['move']);
      $errors = 0;
      $files = $_POST['file'];
      if (is_array($files) && count($files)) {
        foreach ($files as $f) {
          if ($f != '') {
            $from = $path.'/'.$f;
            $dest = $copy_to_path.'/'.$f;
            if ($move) {
              $rename = $this->fm_rename($from, $dest);
              if ($rename === false) {
                $errors++;
              }
            } else {
              if (!$this->fm_rcopy($from, $dest)) {
                $errors++;
              }
            }
          }
        }
        if ($errors == 0) {
          $msg = $move ? 'Selected files and folders moved' : 'Selected files and folders copied';
          $this->fm_set_msg($msg);
        } else {
          $msg = $move ? 'Error while moving items' : 'Error while copying items';
          $this->fm_set_msg($msg, 'error');
        }
      } else {
        $this->fm_set_msg('Nothing selected', 'alert');
      }
      $this->fm_redirect($this->fm_self_url.'?p='.$this->fm_urlencode($this->fm_path));
    }

    if (isset($_GET['ren'], $_GET['to'])) {
      $old = $_GET['ren'];
      $old = $this->fm_clean_path($old);
      $old = str_replace('/', '', $old);
      $new = $_GET['to'];
      $new = $this->fm_clean_path($new);
      $new = str_replace('/', '', $new);
      $path = $this->fm_root_path;
      if ($this->fm_path != '') {
        $path .= '/'.$this->fm_path;
      }
      if ($old != '' && $new != '') {
        if ($this->fm_rename($path.'/'.$old, $path.'/'.$new)) {
          $this->fm_set_msg(sprintf('Renamed from <b>%s</b> to <b>%s</b>', $this->fm_enc($old), $this->fm_enc($new)));
        } else {
          $this->fm_set_msg(sprintf('Error while renaming from <b>%s</b> to <b>%s</b>', $this->fm_enc($old), $this->fm_enc($new)), 'error');
        }
      } else {
        $this->fm_set_msg('Names not set', 'error');
      }
      $this->fm_redirect($this->fm_self_url.'?p='.$this->fm_urlencode($this->fm_path));
    }

    if (isset($_GET['dl'])) {
      $dl = $_GET['dl'];
      $dl = $this->fm_clean_path($dl);
      $dl = str_replace('/', '', $dl);
      $path = $this->fm_root_path;
      if ($this->fm_path != '') {
        $path .= '/'.$this->fm_path;
      }
      if ($dl != '' && is_file($path.'/'.$dl)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.basename($path.'/'.$dl).'"');
        header('Content-Transfer-Encoding: binary');
        header('Connection: Keep-Alive');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Content-Length: '.filesize($path.'/'.$dl));
        if (ob_get_level()) {
          ob_end_clean();
        }
        readfile($path.'/'.$dl);
        exit;
      } else {
        $this->fm_set_msg('File not found', 'error');
        $this->fm_redirect($this->fm_self_url.'?p='.$this->fm_urlencode($this->fm_path));
      }
    }

    if (isset($_POST['upl'])) {
      $path = $this->fm_root_path;
      if ($this->fm_path != '') {
        $path .= '/'.$this->fm_path;
      }
      $errors = 0;
      $uploads = 0;
      $total = count($_FILES['upload']['name']);
      for ($i = 0; $i < $total; $i++) {
        $tmp_name = $_FILES['upload']['tmp_name'][$i];
        if (empty($_FILES['upload']['error'][$i]) && !empty($tmp_name) && $tmp_name != 'none') {
          if (move_uploaded_file($tmp_name, $path . '/' . $_FILES['upload']['name'][$i])) {
            $uploads++;
          } else {
            $errors++;
          }
        }
      }
      if ($errors == 0 && $uploads > 0) {
        $this->fm_set_msg(sprintf('All files uploaded to <b>%s</b>', $this->fm_enc($path)));
      } elseif ($errors == 0 && $uploads == 0) {
        $this->fm_set_msg('Nothing uploaded', 'alert');
      } else {
        $this->fm_set_msg(sprintf('Error while uploading files. Uploaded files: %s', $uploads), 'error');
      }
      $this->fm_redirect($this->fm_self_url.'?p='.$this->fm_urlencode($this->fm_path));
    }

    if (isset($_POST['group'], $_POST['delete'])) {
      $path = $this->fm_root_path;
      if ($this->fm_path != '') {
        $path .= '/'.$this->fm_path;
      }
      $errors = 0;
      $files = $_POST['file'];
      if (is_array($files) && count($files)) {
        foreach ($files as $f) {
          if ($f != '') {
            $new_path = $path . '/' . $f;
            if (!$this->fm_rdelete($new_path)) {
              $errors++;
            }
          }
        }
        if ($errors == 0) {
          $this->fm_set_msg('Selected files and folder deleted');
        } else {
          $this->fm_set_msg('Error while deleting items', 'error');
        }
      } else {
        $this->fm_set_msg('Nothing selected', 'alert');
      }
      $this->fm_redirect($this->fm_self_url.'?p='.$this->fm_urlencode($this->fm_path));
    }

    if (isset($_POST['group'], $_POST['zip'])) {
      $path = $this->fm_root_path;
      if ($this->fm_path != '') {
        $path .= '/'.$this->fm_path;
      }
      if (!class_exists('ZipArchive')) {
        $this->fm_set_msg('Operations with archives are not available', 'error');
        $this->fm_redirect(fm_self_url.'?p='.$this->fm_urlencode($this->fm_path));
      }
      $files = $_POST['file'];
      if (!empty($files)) {
        chdir($path);
        if (count($files) == 1) {
          $one_file = reset($files);
          $one_file = basename($one_file);
          $zipname = $one_file.'_'.date('ymd_His').'.zip';
        } else {
          $zipname = 'archive_'.date('ymd_His').'.zip';
        }
        if (file_exists(__DIR__.'/fmzipper.php')) {
          require_once(__DIR__.'/fmzipper.php');
          $zipper = new \ProcessFileManager\FM_Zipper();
          $res = $zipper->create($zipname, $files);
        } else {
          $res = false;
        }
        if ($res) {
          $this->fm_set_msg(sprintf('Archive <b>%s</b> created', $this->fm_enc($zipname)));
        } else {
          $this->fm_set_msg('Archive not created', 'error');
        }
      } else {
        $this->fm_set_msg('Nothing selected', 'alert');
      }
      $this->fm_redirect($this->fm_self_url.'?p='.$this->fm_urlencode($this->fm_path));
    }

    if (isset($_GET['unzip'])) {
      $unzip = $_GET['unzip'];
      $unzip = $this->fm_clean_path($unzip);
      $unzip = str_replace('/', '', $unzip);
      $path = $this->fm_root_path;
      if ($this->fm_path != '') {
        $path .= '/'.$this->fm_path;
      }
      if (!class_exists('ZipArchive')) {
        $this->fm_set_msg('Operations with archives are not available', 'error');
        $this->fm_redirect($this->fm_self_url.'?p='.$this->fm_urlencode($this->fm_path));
      }
      if ($unzip != '' && is_file($path.'/'.$unzip)) {
        $zip_path = $path.'/'.$unzip;
        $tofolder = '';
        if (isset($_GET['tofolder'])) {
          $tofolder = pathinfo($zip_path, PATHINFO_FILENAME);
          if ($this->fm_mkdir($path.'/'.$tofolder, true)) {
            $path .= '/'.$tofolder;
          }
        }
        if (file_exists(__DIR__.'/fmzipper.php')) {
          require_once(__DIR__.'/fmzipper.php');
          $zipper = new \ProcessFileManager\FM_Zipper();
          $res = $zipper->unzip($zip_path, $path);
        } else {
          $res = false;
        }
        if ($res) {
          $this->fm_set_msg('Archive unpacked');
        } else {
          $this->fm_set_msg('Archive not unpacked', 'error');
        }
      } else {
        $this->fm_set_msg('File not found', 'error');
      }
      $this->fm_redirect($this->fm_self_url.'?p='.$this->fm_urlencode($this->fm_path));
    }

    if (isset($_POST['chmod']) && !$this->fm_is_win) {
      $path = $this->fm_root_path;
      if ($this->fm_path != '') {
        $path .= '/'.$this->fm_path;
      }
      $file = $_POST['chmod'];
      $file = $this->fm_clean_path($file);
      $file = str_replace('/', '', $file);
      if ($file == '' || (!is_file($path . '/' . $file) && !is_dir($path . '/' . $file))) {
        $this->fm_set_msg('File not found', 'error');
        $this->fm_redirect($this->fm_self_url.'?p='.$this->fm_urlencode($this->fm_path));
      }
      $mode = 0;
      if (!empty($_POST['ur'])) {
        $mode |= 0400;
      }
      if (!empty($_POST['uw'])) {
        $mode |= 0200;
      }
      if (!empty($_POST['ux'])) {
        $mode |= 0100;
      }
      if (!empty($_POST['gr'])) {
        $mode |= 0040;
      }
      if (!empty($_POST['gw'])) {
        $mode |= 0020;
      }
      if (!empty($_POST['gx'])) {
        $mode |= 0010;
      }
      if (!empty($_POST['or'])) {
        $mode |= 0004;
      }
      if (!empty($_POST['ow'])) {
        $mode |= 0002;
      }
      if (!empty($_POST['ox'])) {
        $mode |= 0001;
      }
      if (@chmod($path . '/' . $file, $mode)) {
        $this->fm_set_msg('Permissions changed');
      } else {
        $this->fm_set_msg('Permissions not changed', 'error');
      }
      $this->fm_redirect($this->fm_self_url.'?p='.$this->fm_urlencode($this->fm_path));
    }

    $path = $this->fm_root_path;
    if ($this->fm_path != '') {
      $path .= '/'.$this->fm_path;
    }
    if (!is_dir($path)) {
      $this->fm_redirect($this->fm_self_url.'?p=');
    }
    $parent = $this->fm_get_parent_path($this->fm_path);
    $objects = is_readable($path) ? scandir($path) : array();
    $folders = array();
    $files = array();
    if (is_array($objects)) {
      foreach ($objects as $file) {
        if ($file == '.' || $file == '..') {
          continue;
        }
        $new_path = $path . '/' . $file;
        if (is_file($new_path)) {
          $files[] = $file;
        } elseif (is_dir($new_path) && $file != '.' && $file != '..') {
          $folders[] = $file;
        }
      }
    }
    if (!empty($files)) {
      natcasesort($files);
    }
    if (!empty($folders)) {
      natcasesort($folders);
    }

    $out = '';

    if (isset($_GET['upload'])) {
      $out .= $this->fm_show_header();
      $out .= $this->fm_show_nav_path($this->fm_path);
      $out .= '<div class="path">';
      $out .= '<p><b>Uploading files</b></p>';
      $out .= '<p class="break-word">Destination folder: ';
      $out .= $this->fm_enc($this->fm_convert_win($this->fm_root_path.'/' .$this->fm_path));
      $out .= '</p>';
      $out .= '<form action="" method="post" enctype="multipart/form-data">';
      $out .= '<input type="hidden" name="p" value="'.$this->fm_enc($this->fm_path).'">';
      $out .= '<input type="hidden" name="upl" value="1">';
      $out .= '<input type="file" name="upload[]"><br>';
      $out .= '<input type="file" name="upload[]"><br>';
      $out .= '<input type="file" name="upload[]"><br>';
      $out .= '<input type="file" name="upload[]"><br>';
      $out .= '<input type="file" name="upload[]"><br>';
      $out .= '<br />';
      $out .= '<p>';
      $out .= '<button class="btn"><i class="icon-apply"></i> Upload</button> &nbsp;<b><a href="?p='.$this->fm_urlencode($this->fm_path).'"><i class="icon-cancel"></i> Cancel</a></b>';
      $out .= '</p>';
      $out .= '</form>';
      $out .= '</div>';
      $out .= $this->fm_show_footer();
      return $out;
    }

    if (isset($_POST['copy'])) {
      $copy_files = $_POST['file'];
      if (!is_array($copy_files) || empty($copy_files)) {
        $this->fm_set_msg('Nothing selected', 'alert');
        $this->fm_redirect($this->fm_self_url.'?p='.$this->fm_urlencode($this->fm_path));
      }
      $out .= $this->fm_show_header();
      $out .= $this->fm_show_nav_path($this->fm_path);
      $out .= '<div class="path">';
      $out .= '<p><b>Copying</b></p>';
      $out .= '<form action="" method="post">';
      $out .= '<input type="hidden" name="p" value="'.$this->fm_enc($this->fm_path).'">';
      $out .= '<input type="hidden" name="finish" value="1">';
      foreach ($copy_files as $cf) {
        $out .= '<input type="hidden" name="file[]" value="'.$this->fm_enc($cf).'">'.PHP_EOL;
      }
      $copy_files_enc = array_map(array($this, 'fm_enc'), $copy_files);
      $out .= '<p class="break-word">Files: <b>'.implode('</b>, <b>', $copy_files_enc).'</b></p>';
      $out .= '<p class="break-word">Source folder: '.$this->fm_enc($this->fm_convert_win($this->fm_root_path.'/' .$this->fm_path)).'<br>';
      $out .= '<label for="inp_copy_to">Destination folder:</label>';
      $out .= $this->fm_root_path.'/<input name="copy_to" id="inp_copy_to" value="'.$this->fm_enc($this->fm_path).'">';
      $out .= '</p>';
      $out .= '<p><label><input type="checkbox" name="move" value="1"> Move</label></p>';
      $out .= '<p>';
      $out .= '<button class="btn"><i class="icon-apply"></i> Copy</button> &nbsp;';
      $out .= '<b><a href="?p='.$this->fm_urlencode($this->fm_path).'"><i class="icon-cancel"></i> Cancel</a></b>';
      $out .= '</p>';
      $out .= '</form>';
      $out .= '</div>';
      $out .= $this->fm_show_footer();
      return $out;
    }

    if (isset($_GET['copy']) && !isset($_GET['finish'])) {
      $copy = $_GET['copy'];
      $copy = $this->fm_clean_path($copy);
      if ($copy == '' || !file_exists($this->fm_root_path.'/'.$copy)) {
        $this->fm_set_msg('File not found', 'error');
        $this->fm_redirect($this->fm_self_url.'?p='.$this->fm_urlencode($this->fm_path));
      }
      $out .= $this->fm_show_header();
      $out .= $this->fm_show_nav_path($this->fm_path);
      $out .= '<div class="path">';
      $out .= '<p><b>Copying</b></p>';
      $out .= '<p class="break-word">Source path: '.$this->fm_enc($this->fm_convert_win($this->fm_root_path.'/'.$copy)).'<br>Destination folder: '.$this->fm_enc($this->fm_convert_win($this->fm_root_path.'/'.$this->fm_path)).'</p>';
      $out .= '<p>';
      $out .= '<b><a href="?p='.$this->fm_urlencode($this->fm_path).'&amp;copy='.$this->fm_urlencode($copy).'&amp;finish=1"><i class="icon-apply"></i> Copy</a></b> &nbsp;';
      $out .= '<b><a href="?p='.$this->fm_urlencode($this->fm_path).'&amp;copy='.$this->fm_urlencode($copy).'&amp;finish=1&amp;move=1"><i class="icon-apply"></i> Move</a></b> &nbsp;';
      $out .= '<b><a href="?p='.$this->fm_urlencode($this->fm_path).'"><i class="icon-cancel"></i> Cancel</a></b>';
      $out .= '</p>';
      $out .= '<p><i>Select folder:</i></p>';
      $out .= '<ul class="folders break-word">';
      if ($parent !== false) {
        $out .= '<li><a href="?p='.$this->fm_urlencode($parent).'&amp;copy='.$this->fm_urlencode($copy).'"><i class="icon-arrow_up"></i> ..</a></li>';
      }
      foreach ($folders as $f) {
        $out .= '<li><a href="?p='.$this->fm_urlencode(trim($this->fm_path.'/'.$f, '/')).'&amp;copy='.$this->fm_urlencode($copy).'"><i class="icon-folder"></i> '.$this->fm_enc($this->fm_convert_win($f)).'</a></li>';
      }
      $out .= '</ul>';
      $out .= '</div>';
      $out .= $this->fm_show_footer();
      return $out;
    }

    // chmod (not for Windows)
    if (isset($_GET['chmod']) && !$this->fm_is_win) {
      $file = $_GET['chmod'];
      $file = $this->fm_clean_path($file);
      $file = str_replace('/', '', $file);
      if ($file == '' || (!is_file($path . '/' . $file) && !is_dir($path . '/' . $file))) {
        $this->fm_set_msg('File not found', 'error');
        $this->fm_redirect($this->fm_self_url.'?p='.$this->fm_urlencode($this->fm_path));
      }

      $out .= $this->fm_show_header();
      $out .= $this->fm_show_nav_path($this->fm_path);

      $file_url = $this->fm_root_url.($this->fm_path != '' ? '/'.$this->fm_path : '').'/'.$file;
      $file_path = $path.'/'.$file;

      $mode = fileperms($path.'/'.$file);

      $out .= '<div class="path">';
      $out .= '<p><b>Change Permissions</b></p>';
      $out .= '<p>';
      $out .= 'Full path: '.$this->fm_enc($file_path).'<br>';
      $out .= '</p>';
      $out .= '<form action="" method="post">';
      $out .= '<input type="hidden" name="p" value="'.$this->fm_enc($this->fm_path).'">';
      $out .= '<input type="hidden" name="chmod" value="'.$this->fm_enc($file).'">';

      $out .= '<table class="compact-table">';
        $out .= '<tr>';
          $out .= '<td></td>';
          $out .= '<td><b>Owner</b></td>';
          $out .= '<td><b>Group</b></td>';
          $out .= '<td><b>Other</b></td>';
        $out .= '</tr>';
        $out .= '<tr>';
          $out .= '<td style="text-align: right"><b>Read</b></td>';
          $out .= '<td><label><input type="checkbox" name="ur" value="1"';
          if ($mode & 00400) $out .= ' checked';
          $out .= '></label></td>';
          $out .= '<td><label><input type="checkbox" name="gr" value="1"';
          if ($mode & 00040) $out .= ' checked';
          $out .= '></label></td>';
          $out .= '<td><label><input type="checkbox" name="or" value="1"';
          if ($mode & 00004) $out .= ' checked';
          $out .= '></label></td>';
        $out .= '</tr>';
        $out .= '<tr>';
          $out .= '<td style="text-align: right"><b>Write</b></td>';
          $out .= '<td><label><input type="checkbox" name="uw" value="1"';
          if ($mode & 00200) $out .= ' checked';
          $out .= '></label></td>';
          $out .= '<td><label><input type="checkbox" name="gw" value="1"';
          if ($mode & 00020) $out .= ' checked';
          $out .= '></label></td>';
          $out .= '<td><label><input type="checkbox" name="ow" value="1"';
          if ($mode & 00002) $out .= ' checked';
          $out .= '></label></td>';
        $out .= '</tr>';
        $out .= '<tr>';
          $out .= '<td style="text-align: right"><b>Execute</b></td>';
          $out .= '<td><label><input type="checkbox" name="ux" value="1"';
          if ($mode & 00100) $out .= ' checked';
          $out .= '></label></td>';
          $out .= '<td><label><input type="checkbox" name="gx" value="1"';
          if ($mode & 00010) $out .= ' checked';
          $out .= '></label></td>';
          $out .= '<td><label><input type="checkbox" name="ox" value="1"';
          if ($mode & 00001) $out .= ' checked';
          $out .= '></label></td>';
        $out .= '</tr>';
      $out .= '</table>';

      $out .= '<p>';
        $out .= '<button class="btn"><i class="icon-apply"></i> Change</button> &nbsp;';
        $out .= '<b><a href="?p='.$this->fm_urlencode($this->fm_path).'"><i class="icon-cancel"></i> Cancel</a></b>';
      $out .= '</p>';

      $out .= '</form>';
      $out .= '</div>';
      $out .= $this->fm_show_footer();
      return $out;
    }

    if (isset($_GET['view'])) {
      $file = $_GET['view'];
      $file = $this->fm_clean_path($file);
      $file = str_replace('/', '', $file);
      if ($file == '' || !is_file($path.'/'.$file)) {
        $this->fm_set_msg('File not found', 'error');
        $this->fm_redirect($this->fm_self_url.'?p='.$this->fm_urlencode($this->fm_path));
      }
      $out .= $this->fm_show_header();
      $out .= $this->fm_show_nav_path($this->fm_path);
      
      $file_url = $this->fm_root_url.$this->fm_convert_win(($this->fm_path != '' ? '/'.$this->fm_path : '').'/'.$file);
      $file_path = $path.'/'.$file;
      
      $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
      $mime_type = $this->fm_get_mime_type($file_path);
      $filesize = filesize($file_path);
      
      $is_zip = false;
      $is_image = false;
      $is_audio = false;
      $is_video = false;
      $is_text = false;
      $view_title = 'File';
      $filenames = false; // for zip
      $content = ''; // for text
      
      if ($ext == 'zip') {
        $is_zip = true;
        $view_title = 'Archive';
        $filenames = $this->fm_get_zif_info($file_path);
      } elseif (in_array($ext, $this->fm_image_exts)) {
        $is_image = true;
        $view_title = 'Image';
      } elseif (in_array($ext, $this->fm_audio_exts)) {
        $is_audio = true;
        $view_title = 'Audio';
      } elseif (in_array($ext, $this->fm_video_exts)) {
        $is_video = true;
        $view_title = 'Video';
      } elseif (in_array($ext, $this->fm_text_exts) || substr($mime_type, 0, 4) == 'text' || in_array($mime_type, $this->fm_text_mimes)) {
        if ((isset($_POST['action'])) && (isset($_POST['ace_code']))) {
          if ($_POST['action'] == 'save') {
            if (file_put_contents($file_path, $_POST['ace_code']) === false) {
              $out .= '<p class="message error">'.sprintf('File <b>%s</b> not saved', $this->fm_enc($file)).'</p>';
            } else {
              $out .= '<p class="message ok">'.sprintf('File <b>%s</b> saved', $this->fm_enc($file)).'</p>';
            }
          }
        }
        $is_text = true;
        $content = file_get_contents($file_path);
      }

      $out .= '<div class="path">';
      $out .= '<p class="break-word"><b>'.$view_title.' "'.$this->fm_enc($this->fm_convert_win($file)).'"</b></p>';
      $out .= '<p class="break-word">';
      $out .= 'Full path: '.$this->fm_enc($this->fm_convert_win($file_path)).'<br>';
      $out .= 'File size: '.$this->fm_get_filesize($filesize);
      if ($filesize >= 1000) {
        $out .= ' '.sprintf('%s bytes', $filesize);
      }
      $out .= '<br>';
      $out .= 'MIME-type: '.$mime_type.'<br>';

      // ZIP info
      if ($is_zip && $filenames !== false) {
        $total_files = 0;
        $total_comp = 0;
        $total_uncomp = 0;
        foreach ($filenames as $fn) {
          if (!$fn['folder']) {
            $total_files++;
          }
          $total_comp += $fn['compressed_size'];
          $total_uncomp += $fn['filesize'];
        }
      $out .= 'Files in archive: '.$total_files.'<br>';
      $out .= 'Total size: '.$this->fm_get_filesize($total_uncomp).'<br>';
      $out .= 'Size in archive: '.$this->fm_get_filesize($total_comp).'<br>';
      $out .= 'Compression: '.round(($total_comp / $total_uncomp) * 100).'%<br>';
      }

      // Image info
      if ($is_image) {
        $image_size = getimagesize($file_path);
        $out .= 'Image sizes: '.(isset($image_size[0]) ? $image_size[0] : '0').' x '.(isset($image_size[1]) ? $image_size[1] : '0').'<br>';
      }

      // Text info
      if ($is_text) {
        $is_utf8 = $this->fm_is_utf8($content);
        if (function_exists('iconv')) {
          if (!$is_utf8) {
            $content = iconv($this->fm_iconv_input_enc, 'UTF-8//IGNORE', $content);
          }
        }
        $out .= 'Charset: '.($is_utf8 ? 'utf-8' : '8 bit').'<br>';
      }
      $out .= '</p>';
      $out .= '<p>';
      $out .= '<b><a href="?p='.$this->fm_urlencode($this->fm_path).'&amp;dl='.$this->fm_urlencode($file).'"><i class="icon-download"></i> Download</a></b> &nbsp;';
      $out .= '<b><a href="'.$this->fm_enc($file_url).'" target="_blank"><i class="icon-chain"></i> Open</a></b> &nbsp;';

      // ZIP actions
      if ($is_zip && $filenames !== false) {
        $zip_name = pathinfo($file_path, PATHINFO_FILENAME);
        $out .= '<b><a href="?p='.$this->fm_urlencode($this->fm_path).'&amp;unzip='.$this->fm_urlencode($file).'"><i class="icon-apply"></i> Unpack</a></b> &nbsp;';
        $out .= '<b><a href="?p='.$this->fm_urlencode($this->fm_path).'&amp;unzip='.$this->fm_urlencode($file).'&amp;tofolder=1" title="Unpack to '.$this->fm_enc($zip_name).'"><i class="icon-apply"></i>';
        $out .= 'Unpack to folder</a></b> &nbsp;';
      }

      $out .= '<b><a href="?p='.$this->fm_urlencode($this->fm_path).'"><i class="icon-goback"></i> Back</a></b>';
      $out .= '</p>';
      if ($is_zip) {
        // ZIP content
        if ($filenames !== false) {
          $out .= '<code class="maxheight">';
          foreach ($filenames as $fn) {
            if ($fn['folder']) {
              $out .= '<b>'.$this->fm_enc($fn['name']).'</b><br>';
            } else {
              $out .= $fn['name'].' ('.$this->fm_get_filesize($fn['filesize']).')<br>';
            }
          }
          $out .= '</code>';
        } else {
          $out .= '<p>Error while fetching archive info</p>';
        }
      } elseif ($is_image) {
        // Image content
        if (in_array($ext, array('gif', 'jpg', 'jpeg', 'png', 'bmp', 'ico'))) {
          $out .= '<p><img src="'.$this->fm_enc($file_url).'" alt="" class="preview-img"></p>';
        }
      } elseif ($is_audio) {
        // Audio content
        $out .= '<p><audio src="'.$this->fm_enc($file_url).'" controls preload="metadata"></audio></p>';
      } elseif ($is_video) {
        // Video content
        $out .= '<div class="preview-video"><video src="'.$this->fm_enc($file_url).'" width="640" height="360" controls preload="metadata"></video></div>';
      } elseif ($is_text) {
        if ($this->use_ace) {
          $out .= $this->showEditor($content, $this->fm_urlencode($this->fm_path), $file, $ext);
        } else {
          if (in_array($ext, array('php', 'php4', 'php5', 'phtml', 'phps'))) {
            $content = highlight_string($content, true);
          } else {
            $content = '<pre>'.$this->fm_enc($content).'</pre>';
          }
          $out .= $content;
        }
      }
      $out .= '</div>';
      $out .= $this->fm_show_footer();
      return $out;
    }

    //--- FILEMANAGER MAIN
    $out .= $this->fm_show_header();
    $out .= $this->fm_show_nav_path($this->fm_path);
    $out .= $this->fm_show_message();
    
    $num_files = count($files);
    $num_folders = count($folders);
    $all_files_size = 0;

    $out .= '<form action="" method="post">';
    $out .= '<input type="hidden" name="p" value="'.$this->fm_enc($this->fm_path).'">';
    $out .= '<input type="hidden" name="group" value="1">';
    $out .= '<div class="dragscroll">';
    $out .= '<table><tr>';
    $out .= '<th style="width:3%"><label><input type="checkbox" title="Invert selection" onclick="checkbox_toggle()"></label></th>';
    $out .= '<th>Name</th><th style="width:10%">Size</th>';
    $out .= '<th style="width:12%">Modified</th>';
    if (!$this->fm_is_win) {
      $out .= '<th style="width:6%">Perms</th><th style="width:10%">Owner</th>';
    }
    $out .= '<th style="width:13%"></th></tr>';
    if ($parent !== false) {
      $out .= '<tr><td></td><td colspan="';
      if (!$this->fm_is_win) {
        $out .= '6';
      } else {
        $out .= '4';
      }
      $out .= '"><a href="?p='.$this->fm_urlencode($parent).'"><i class="icon-arrow_up"></i> ..</a></td></tr>';
    }
    $datetime = new \DateTime();
    foreach ($folders as $f) {
      $is_link = is_link($path.'/'.$f);
      $img = $is_link ? 'icon-link_folder' : 'icon-folder';
      $datetime->setTimezone(new \DateTimeZone($this->fm_datetime_zone));
      $datetime->setTimestamp(filemtime($path.'/'.$f));
      $modif = $datetime->format($this->fm_datetime_format);
      $perms = substr(decoct(fileperms($path.'/'.$f)), -4);
      if (function_exists('posix_getpwuid') && function_exists('posix_getgrgid')) {
        $owner = posix_getpwuid(fileowner($path . '/' . $f));
        $group = posix_getgrgid(filegroup($path . '/' . $f));
      } else {
        $owner = array(
          'name' => '?'
        );
        $group = array(
          'name' => '?'
        );
      }
      $out .= '<tr>';
      $out .= '<td><label><input type="checkbox" name="file[]" value="'.$this->fm_enc($f).'"></label></td>';
      $out .= '<td><div class="filename"><a href="?p='.$this->fm_urlencode(trim($this->fm_path.'/'.$f, '/')).'"><i class="'.$img.'"></i> '.$this->fm_enc($this->fm_convert_win($f)).'</a>';
      
      if ($is_link) {
        $out .= ' &rarr; <i>'.$this->fm_enc(readlink($path.'/'.$f)).'</i>';
      }
      $out .= '</div></td>';
      $out .= '<td>Folder</td><td>'.$modif.'</td>';
      if (!$this->fm_is_win) {
        $out .= '<td><a title="Change Permissions" href="?p='.$this->fm_urlencode($this->fm_path).'&amp;chmod='.$this->fm_urlencode($f).'">'.$perms.'</a></td>';
        $out .= '<td>';
        $out .= $this->fm_enc($owner['name'].':'.$group['name']);
        $out .= '</td>';
      }

      $out .= '<td>';
      $out .= '<a title="Delete" href="?p='.$this->fm_urlencode($this->fm_path).'&amp;del='.$this->fm_urlencode($f).'" onclick="return confirm(\'Delete folder?\');"><i class="icon-cross"></i> </a>';
      $out .= '<a title="Rename" href="#" onclick="rename(\''.$this->fm_urlencode($this->fm_path).'\', \''.$this->fm_urlencode($f).'\');return false;"><i class="icon-rename"></i> </a>';
      $out .= '<a title="Copy to..." href="?p=&amp;copy='.$this->fm_urlencode(trim($this->fm_path . '/' . $f, '/')).'"><i class="icon-copy"></i> </a>';
      $out .= '<a title="Direct link" href="'.$this->fm_enc($this->fm_root_url.($this->fm_path != '' ? '/'.$this->fm_path : '').'/'.$f.'/').'" target="_blank"><i class="icon-chain"></i></a>';
      $out .= '</td></tr>';
    }
    
    $datetime = new \DateTime();
    foreach ($files as $f) {
      $is_link = is_link($path . '/' . $f);
      $img = $is_link ? 'icon-link_file' : $this->fm_get_file_icon_class($path . '/' . $f);
      $datetime->setTimezone(new \DateTimeZone($this->fm_datetime_zone));
      $datetime->setTimestamp(filemtime($path . '/' . $f));
      $modif = $datetime->format($this->fm_datetime_format);
      $filesize_raw = filesize($path . '/' . $f);
      $filesize = $this->fm_get_filesize($filesize_raw);
      $filelink = '?p='.$this->fm_urlencode($this->fm_path).'&view='.$this->fm_urlencode($f);
      $all_files_size += $filesize_raw;
      $perms = substr(decoct(fileperms($path.'/'.$f)), -4);
      if (function_exists('posix_getpwuid') && function_exists('posix_getgrgid')) {
        $owner = posix_getpwuid(fileowner($path.'/'.$f));
        $group = posix_getgrgid(filegroup($path.'/'.$f));
      } else {
        $owner = array('name' => '?');
        $group = array('name' => '?');
      }
      $out .= '<tr>';
      $out .= '<td><label><input type="checkbox" name="file[]" value="'.$this->fm_enc($f).'"></label></td>';
      $out .= '<td><div class="filename"><a href="'.$this->fm_enc($filelink).'" title="File info"><i class="'.$img.'" style="color-scheme:light dark"></i> '.$this->fm_enc($this->fm_convert_win($f)).'</a>';
      if ($is_link) {
        $out .= ' &rarr; <i>'.$this->fm_enc(readlink($path.'/'.$f)).'</i>';
      }
      $out .= '</div></td>';
      $out .= '<td><span class="gray" title="'.sprintf('%s bytes', $filesize_raw).'">'.$filesize.'</span></td>';
      $out .= '<td>'.$modif.'</td>';

      if (!$this->fm_is_win) {
        $out .= '<td><a title="Change Permissions" href="?p='.$this->fm_urlencode($this->fm_path).'&amp;chmod='.$this->fm_urlencode($f).'">'.$perms.'</a></td>';
        $out .= '<td>'.$this->fm_enc($owner['name'].':'.$group['name']).'</td>';
      }

      $out .= '<td>';
      $out .= '<a title="Delete" href="?p='.$this->fm_urlencode($this->fm_path).'&amp;del='.$this->fm_urlencode($f).'" onclick="return confirm(\'Delete file?\');"><i class="icon-cross"></i> </a>';
      $out .= '<a title="Rename" href="#" onclick="rename(\''.$this->fm_enc($this->fm_path).'\', \''.$this->fm_enc($f).'\');return false;"><i class="icon-rename"></i> </a>';
      $out .= '<a title="Copy to..." href="?p='.$this->fm_urlencode($this->fm_path).'&amp;copy='.$this->fm_urlencode(trim($this->fm_path . '/' . $f, '/')).'"><i class="icon-copy"></i> </a>';
      $out .= '<a title="Direct link" href="'.$this->fm_enc($this->fm_root_url.($this->fm_path != '' ? '/'.$this->fm_path : '').'/'.$f).'" target="_blank"><i class="icon-chain"></i> </a>';
      $out .= '<a title="Download" href="?p='.$this->fm_urlencode($this->fm_path).'&amp;dl='.$this->fm_urlencode($f).'"><i class="icon-download"></i> </a>';
      $out .= '</td></tr>';
    }

    if (empty($folders) && empty($files)) {
      $out .= '<tr><td></td><td colspan="';
      if (!$this->fm_is_win) {
        $out .= '6';
      } else {
        $out .= '4';
      }
      $out .= '"><em>Folder is empty</em></td></tr>';
    } else {
      $out .= '<tr><td class="gray"></td><td class="gray" colspan="';
      if (!$this->fm_is_win) {
        $out .= '6';
      } else {
        $out .= '4';
      }
      $out .= '">';
      $out .= 'Full size: <span title="'.sprintf('%s bytes', $all_files_size).'">'.$this->fm_get_filesize($all_files_size).'</span>, Files: '.$num_files.', Folders: '.$num_folders.'</td></tr>';
    }
    $out .= '</table>';
    $out .= '</div>';
    $out .= '<p class="path"><a href="#" onclick="select_all();return false;"><i class="icon-checkbox"></i> Select all</a> &nbsp;
  <a href="#" onclick="unselect_all();return false;"><i class="icon-checkbox_uncheck"></i> Unselect all</a> &nbsp;
  <a href="#" onclick="invert_all();return false;"><i class="icon-checkbox_invert"></i> Invert selection</a></p>';
    $out .= '<p>';
    $out .= '<button type="submit" name="delete" value="Delete" type="button" class="ui-button ui-widget ui-state-default ui-corner-all" onclick="return confirm(\'Delete selected files and folders?\');">Delete</button>&nbsp';
    $out .= '<button type="submit" name="zip" value="Pack" type="button" class="ui-button ui-widget ui-state-default ui-corner-all" onclick="return confirm(\'Create archive?\');">Pack</button>&nbsp';
    $out .= '<button type="submit" name="copy" value="Copy" type="button" class="ui-button ui-widget ui-state-default ui-corner-all">Copy</button>';
    $out .= '</p>';
    $out .= '</form>';

    $out .= $this->fm_show_footer();
    return $out;
  }

  function fm_redirect($url, $code = 302) {
    header('Location: '.$url, true, $code);
    exit;
  }

  function fm_clean_path($path) {
    $path = trim($path);
    $path = trim($path, '\\/');
    $path = str_replace(array('../', '..\\'), '', $path);
    if ($path == '..') {
      $path = '';
    }
    return str_replace('\\', '/', $path);
  }

  function fm_rdelete($path) {
    if (is_link($path)) {
      return unlink($path);
    } elseif (is_dir($path)) {
      $objects = scandir($path);
      $ok = true;
      if (is_array($objects)) {
        foreach ($objects as $file) {
          if ($file != '.' && $file != '..') {
            if (!$this->fm_rdelete($path.'/'.$file)) {
              $ok = false;
            }
          }
        }
      }
      return ($ok) ? rmdir($path) : false;
    } elseif (is_file($path)) {
      return unlink($path);
    }
    return false;
  }

  function fm_set_msg($msg, $status = 'ok') {
    $_SESSION['message'] = $msg;
    $_SESSION['status']  = $status;
  }

  function fm_enc($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
  }

  function fm_mkdir($dir, $force) {
    if (file_exists($dir)) {
      if (is_dir($dir)) {
        return $dir;
      } elseif (!$force) {
        return false;
      }
      unlink($dir);
    }
    return mkdir($dir, 0777, true);
  }

  function fm_rename($old, $new) {
    return (!file_exists($new) && file_exists($old)) ? rename($old, $new) : null;
  }

  function fm_rcopy($path, $dest, $upd = true, $force = true) {
    if (is_dir($path)) {
      if (!$this->fm_mkdir($dest, $force)) {
        return false;
      }
      $objects = scandir($path);
      $ok = true;
      if (is_array($objects)) {
        foreach ($objects as $file) {
          if ($file != '.' && $file != '..') {
            if (!$this->fm_rcopy($path.'/'.$file, $dest.'/'.$file)) {
              $ok = false;
            }
          }
        }
      }
      return $ok;
    } elseif (is_file($path)) {
      return $this->fm_copy($path, $dest, $upd);
    }
    return false;
  }

  function fm_copy($f1, $f2, $upd) {
    $time1 = filemtime($f1);
    if (file_exists($f2)) {
      $time2 = filemtime($f2);
      if ($time2 >= $time1 && $upd) {
        return false;
      }
    }
    $ok = copy($f1, $f2);
    if ($ok) {
      touch($f2, $time1);
    }
    return $ok;
  }

  function fm_get_parent_path($path) {
    $path = $this->fm_clean_path($path);
    if ($path != '') {
      $array = explode('/', $path);
      if (count($array) > 1) {
        $array = array_slice($array, 0, -1);
        return implode('/', $array);
      }
      return '';
    }
    return false;
  }

  function fm_show_header() {
    return '<div id="wrapper">';
  }

  function fm_urlencode($s) {
    return str_replace('.', '%2E', urlencode($s));
  }

  function fm_show_footer() {
    $out = '</div>';
    $out .= '<script>';
    $out .= 'function newfolder(p){var n=prompt(\'New folder name\',\'folder\');if(n!==null&&n!==\'\'){window.location.search=\'p=\'+encodeURIComponent(p).replace(/\./g, \'%2E\')+\'&newfolder=\'+encodeURIComponent(n).replace(/\./g, \'%2E\');}}';
    $out .= 'function newfile(p){var n=prompt(\'New file name\',\'file\');if(n!==null&&n!==\'\'){window.location.search=\'p=\'+encodeURIComponent(p).replace(/\./g, \'%2E\')+\'&newfile=\'+encodeURIComponent(n).replace(/\./g, \'%2E\');}}';
    $out .= 'function rename(p,f){var n=prompt(\'New name\',f);if(n!==null&&n!==\'\'&&n!=f){window.location.search=\'p=\'+encodeURIComponent(p).replace(/\./g, \'%2E\')+\'&ren=\'+encodeURIComponent(f).replace(/\./g, \'%2E\')+\'&to=\'+encodeURIComponent(n).replace(/\./g, \'%2E\');}}';
    $out .= 'function change_checkboxes(l,v){for(var i=l.length-1;i>=0;i--){l[i].checked=(typeof v===\'boolean\')?v:!l[i].checked;}}';
    $out .= 'function get_checkboxes(){var i=document.getElementsByName(\'file[]\'),a=[];for(var j=i.length-1;j>=0;j--){if(i[j].type=\'checkbox\'){a.push(i[j]);}}return a;}';
    $out .= 'function select_all(){var l=get_checkboxes();change_checkboxes(l,true);}';
    $out .= 'function unselect_all(){var l=get_checkboxes();change_checkboxes(l,false);}';
    $out .= 'function invert_all(){var l=get_checkboxes();change_checkboxes(l);}';
    $out .= 'function checkbox_toggle(){var l=get_checkboxes();l.push(this);change_checkboxes(l);}';
    $out .= '</script>';
    return $out;
  }

  function fm_get_zif_info($path) {
    $arch = new \ZipArchive();
    $arch->open($path, \ZipArchive::RDONLY);
    if ($arch) {
      $filenames = array();
      for ($i=0; $i<$arch->numFiles; $i++) {
        $stat = $arch->statIndex($i);
        $zip_name = $stat['name'];
        $zip_folder = substr($zip_name, -1) == '/';
        $filenames[] = array(
          'name' => $zip_name,
          'filesize' => $stat['size'],
          'compressed_size' => $stat['comp_size'],
          'folder' => $zip_folder
        );
      }
      $arch->close();
      return $filenames;
    }
    return false;
  }

  function fm_get_filesize($size) {
    if ($size < 1000) {
      return sprintf('%s B', $size);
    } elseif (($size / 1024) < 1000) {
      return sprintf('%s KiB', round(($size / 1024), 2));
    } elseif (($size / 1024 / 1024) < 1000) {
      return sprintf('%s MiB', round(($size / 1024 / 1024), 2));
    } elseif (($size / 1024 / 1024 / 1024) < 1000) {
      return sprintf('%s GiB', round(($size / 1024 / 1024 / 1024), 2));
    } else {
      return sprintf('%s TiB', round(($size / 1024 / 1024 / 1024 / 1024), 2));
    }
  }

  function fm_is_utf8($string) {
    return preg_match('//u', $string);
  }
  
  function fm_show_message() {
    if (isset($_SESSION['message'])) {
      $class = isset($_SESSION['status']) ? $_SESSION['status'] : 'ok';
      $out = '<p class="message '.$class.'">'.$_SESSION['message'].'</p>';
      unset($_SESSION['message']);
      unset($_SESSION['status']);
      return $out;
    }
  }

  function fm_show_nav_path($path) {
    $out = '<div class="path">';
    $out .= '<div class="float-right">';
    $out .= '<a title="Upload files" href="?p='.$this->fm_urlencode($this->fm_path).'&amp;upload"><i class="icon-upload"></i> </a>';
    $out .= '<a title="New folder" href="#" onclick="newfolder(\''.$this->fm_enc($this->fm_path).'\');return false;"><i class="icon-folder_add"></i> </a>';
    $out .= '<a title="New file" href="#" onclick="newfile(\''.$this->fm_enc($this->fm_path).'\');return false;"><i class="icon-file_add"></i></a>';
    $out .= '</div>';
    $path = $this->fm_clean_path($path);
    $root_url = "<a href='?p='><i class='icon-home' title='".$this->fm_root_path."'></i></a>";
    $sep = '<i class="icon-separator"></i>';
    if ($path != '') {
      $exploded = explode('/', $path);
      $count = count($exploded);
      $array = array();
      $parent = '';
      for ($i = 0; $i < $count; $i++) {
        $parent = trim($parent.'/'.$exploded[$i], '/');
        $parent_enc = $this->fm_urlencode($parent);
        $array[] = "<a href='?p={$parent_enc}'>".$this->fm_enc($this->fm_convert_win($exploded[$i]))."</a>";
        //$array[] = '<a href="?p='.$parent_enc.'">'.$this->fm_enc($this->fm_convert_win($exploded[$i]))."</a>";
      }
      $root_url .= $sep.implode($sep, $array);
    }
    $out .= '<div class="break-word">'.$root_url.'</div>';
    $out .= '</div>';
    return $out;
  }

  function fm_convert_win($filename) {
    if ($this->fm_is_win && function_exists('iconv')) {
      $filename = iconv($this->fm_iconv_input_enc, 'UTF-8//IGNORE', $filename);
    }
    return $filename;
  }

  function fm_get_mime_type($file_path) {
    if (function_exists('finfo_open')) {
      $finfo = finfo_open(FILEINFO_MIME_TYPE);
      $mime = finfo_file($finfo, $file_path);
      finfo_close($finfo);
      return $mime;
    } elseif (function_exists('mime_content_type')) {
      return mime_content_type($file_path);
    } elseif (!stristr(ini_get('disable_functions'), 'shell_exec')) {
      $file = escapeshellarg($file_path);
      $mime = shell_exec('file -bi '.$file);
      return $mime;
    } else {
      return '--';
    }
  }

  function showEditor($content, $url, $file_name, $file_ext) {
    $out = '<form method="POST" action="'.'?p='.$url.'&amp;view='.$this->fm_urlencode($file_name).'">';
    
    $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__.'/ace/', \RecursiveDirectoryIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
    $exts = array();
    foreach ($files as $file) {
      if (!$file->isDir()) {
        if (substr($file->getFilename(), 0, 5) === 'mode-') {
          $ext = substr($file->getFilename(), 5);
          if (substr($ext, -3) === '.js') {
            $ext = substr($ext, 0, -3);
            if ($ext != 'plain_text') {
              $exts[] = $ext;
            }
          }
        }
      }
    }
    sort($exts);

    switch ($file_ext) {
      case 'js':
        $file_ext = 'javascript';
        break;
      case 'pas':
        $file_ext = 'pascal';
        break;
      case 'pl':
        $file_ext = 'perl';
        break;
      case 'c':
      case 'cpp':
        $file_ext = 'c_cpp';
        break;
      case 'htaccess':
        $file_ext = 'apache_conf';
        break;
    }

    $isselected = false;
    $out .= '<label>Type: <select name="ace_type" id="ace_type">';
    foreach ($exts as $ext) {
      if ($ext == $file_ext) {
        $selected = ' selected="selected"';
        $isselected = true;
      } else {
        $selected = '';
      }
      $out .= '<option value="'.$ext.'"'.$selected.'>'.$ext.'</option>';
    }
    if ($isselected == true) {
      $out .= '<option value="plain_text">Plain text</option>';
    } else {
      $out .= '<option value="plain_text" selected="selected">Plain text</option>';
    }
    $out .= '</select></label>';

    $out .= '<div id="ace_code" style="height:'.$this->fm_ace_height.'px" data-theme="'.$this->fm_ace_theme.'" data-keybinding="'.$this->fm_ace_keybinding.'" data-behaviors-enabled="'.$this->fm_ace_behaviors_enabled.'" data-wrap-behaviors-enabled="'.$this->fm_ace_wrap_behaviors_enabled.'"></div>';

    $out .= '<input type="hidden" name="ace_code" value="'.htmlspecialchars($content).'" />';
    $out .= '<input type="hidden" name="action" value="save" />';
    $out .= '<button class="ui-button ui-state-default">Save</button>';
    $out .= '</form>';
    return $out;
  }

  function fm_get_file_icon_class($path) {
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    switch ($ext) {
      case 'ico':
      case 'gif':
      case 'jpg':
      case 'jpeg':
      case 'jpc':
      case 'jp2':
      case 'jpx':
      case 'xbm':
      case 'wbmp':
      case 'png':
      case 'bmp':
      case 'tif':
      case 'tiff':
        $img = 'icon-file_image';
        break;
      case 'txt':
      case 'css':
      case 'ini':
      case 'conf':
      case 'log':
      case 'htaccess':
      case 'passwd':
      case 'ftpquota':
      case 'sql':
      case 'js':
      case 'json':
      case 'sh':
      case 'config':
      case 'twig':
      case 'tpl':
      case 'md':
      case 'gitignore':
      case 'less':
      case 'sass':
      case 'scss':
      case 'c':
      case 'cpp':
      case 'cs':
      case 'py':
      case 'map':
      case 'lock':
      case 'dtd':
        $img = 'icon-file_text';
        break;
      case 'zip':
      case 'rar':
      case 'gz':
      case 'tar':
      case '7z':
        $img = 'icon-file_zip';
        break;
      case 'php':
      case 'php4':
      case 'php5':
      case 'phps':
      case 'phtml':
        $img = 'icon-file_php';
        break;
      case 'htm':
      case 'html':
      case 'shtml':
      case 'xhtml':
        $img = 'icon-file_html';
        break;
      case 'xml':
      case 'xsl':
      case 'svg':
        $img = 'icon-file_code';
        break;
      case 'wav':
      case 'mp3':
      case 'mp2':
      case 'm4a':
      case 'aac':
      case 'ogg':
      case 'oga':
      case 'wma':
      case 'mka':
      case 'flac':
      case 'ac3':
      case 'tds':
        $img = 'icon-file_music';
        break;
      case 'm3u':
      case 'm3u8':
      case 'pls':
      case 'cue':
        $img = 'icon-file_playlist';
        break;
      case 'avi':
      case 'mpg':
      case 'mpeg':
      case 'mp4':
      case 'm4v':
      case 'flv':
      case 'f4v':
      case 'ogm':
      case 'ogv':
      case 'mov':
      case 'mkv':
      case '3gp':
      case 'asf':
      case 'wmv':
        $img = 'icon-file_film';
        break;
      case 'eml':
      case 'msg':
        $img = 'icon-file_outlook';
        break;
      case 'xls':
      case 'xlsx':
        $img = 'icon-file_excel';
        break;
      case 'csv':
        $img = 'icon-file_csv';
        break;
      case 'doc':
      case 'docx':
        $img = 'icon-file_word';
        break;
      case 'ppt':
      case 'pptx':
        $img = 'icon-file_powerpoint';
        break;
      case 'ttf':
      case 'ttc':
      case 'otf':
      case 'woff':
      case 'woff2':
      case 'eot':
      case 'fon':
        $img = 'icon-file_font';
        break;
      case 'pdf':
        $img = 'icon-file_pdf';
        break;
      case 'psd':
        $img = 'icon-file_photoshop';
        break;
      case 'ai':
      case 'eps':
        $img = 'icon-file_illustrator';
        break;
      case 'fla':
        $img = 'icon-file_flash';
        break;
      case 'swf':
        $img = 'icon-file_swf';
        break;
      case 'exe':
      case 'msi':
        $img = 'icon-file_application';
        break;
      case 'bat':
        $img = 'icon-file_terminal';
        break;
      default:
        $img = 'icon-document';
    }
    return $img;
  }
}
?>
