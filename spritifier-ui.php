<?php

$filePath = dirname(__FILE__);
$fileName = basename(__FILE__);

$currentPath = './classes';
$scriptPath  = $filePath . '/classes';
$sharePath   = realpath($filePath . '/../share/spritifier');
$sharePathLong = realpath($filePath . '/../share/spritifier/classes');
$newPaths = $currentPath . PATH_SEPARATOR . $scriptPath;
if($sharePath) $newPaths .= PATH_SEPARATOR . $sharePath;
if($sharePathLong) $newPaths .= PATH_SEPARATOR . $sharePathLong;
set_include_path($newPaths . PATH_SEPARATOR . get_include_path());


function displayPositions($positions, $spriteWidth) {

}


// CONFIGURATION
require_once 'AbstractSpritifier.php';
require_once 'SpritifierUI.php';

$usedConfFile = NULL;
if (is_array($_GET) && isset($_GET['userConfFile'])) {
  $confFileName = basename($_GET['userConfFile']);
  if (file_exists($filePath . DIRECTORY_SEPARATOR . $confFileName)) {
    $usedConfFile = $confFileName;
  }
}
if ($usedConfFile === NULL) {
  $usedConfFile = 'userConf-default.php';
}
require_once $usedConfFile;

$mode = 'icons';
$modeConf = $spf_conf[$mode];

$conf = array();
foreach($modeConf as $key => $value) {
  if (is_array($_GET) && isset($_GET[$key]) && !empty($_GET[$key])) {
    if (is_numeric($_GET[$key])) {
      $conf[$key] = intval($_GET[$key], 10);
    }
    else {
      $conf[$key] = $_GET[$key];
    }
  }
  else {
    $conf[$key] = $value;
  }
}

$conf['userConf-files'] = glob($filePath . DIRECTORY_SEPARATOR . 'userConf-*.php');
$conf['userConf-included'] = $usedConfFile;

$spriteWidth = $conf['DEFAULT_WIDTH'];

// EXECUTE
$ret = true;
/**
 * @var AbstractSpritifier
 */
$spriter = null;
switch($mode)
{
  case 'icons':
  default:
    require_once 'SpritifierIcons.php';
    $spriter = new SpritifierIcons($conf);
    if (isset($_GET['export']) && $_GET['export'] === '1') {
      $spriter->setCliOptions(false, true, false);
      $spriter->setOptions(0);
      $ret = $spriter->run($spriteWidth);
    }
    else {
      $spriter->setCliOptions(true, false, false);
      $spriter->setOptions(0);
      list($positions, $imageInfo) = $spriter->getPositionsAndInfos($spriteWidth);
      $spritifierUI = new SpritifierUI();
      $spritifierUI->display($positions, $imageInfo, $conf);
    }
    break;
}


