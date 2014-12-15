#!/usr/bin/env php
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

if($argc <= 1)
{
  echo <<<TEXT
=====
Usage :
php5 {$fileName} -mode={icon} [-width=(int)] [-sortByColor={0|1|2}]
      [-spritifierConf=/path/to/userconf.php]
      [-cssInput=/path/to/definition.css]
      [-cssOutput=/path/to/sprite.css]
      [-pngOutput=/path/to/sprite.png]
      [-pngWebUrl=url/to/sprite.png]
      [-imgRootPath=/path/to/imgDir/]
      [-runPngcrush={true|false}]
      [-runOptipng={true|false}]
      [-verbose={true|false}]
      [-dry-run={true|false}]
      [-libpath=/path/to/lib/dir/]

-mode:
    Indicates which sprite has to be generated.
    Note: only the "icon" mode is supported for now.
-width:
    Indicates the width the sprite must have.
    The height of the sprite depends on the content of the sprite.
-sortByColor:
    That is a secondary sort. The primary sort is always the icons' height.
    if 0, no particular sub-sort is performed.
    if 1, the icons will be sorted by hue.
    if 2, the icons will be sorted by hue only if they have same size (ensure
      to keep sprite image dimensions).
    This sort may help enhance compression ratio.

-spritifierConf=[conf.php]:
    Reads the conf.php file to load some configuration variables (see below).
-cssInput=[file]:
    The path where the definition css file has to be read.
    It overrides the spritifierConf default rule.
-cssOutput=[file]:
    The path where the css has to be written.
    It overrides the userConf default rule.
-pngOutput=[file]:
    The path where the png has to be written.
    It overrides the spritifierConf default rule.
-pngWebUrl=[url] :
    The url used by the generated css file to load the png sprite.
    if the -pngOutput parameter is provided, default is pngOutput filename.
    It overrides the spritifierConf default rule.
-imgRootPath=[directory]:
    The path where all the images that have to appear in the sprite are stored.
    Images are read by concatenation of imgRootPath with the background-image
    url info found in the css.
    It overrides the spritifierConf default rule.
-runPngcrush=[boolean]
    If true and the command pngcrush is available, run pngcrush on "pngOutput".
    The best crushed image will replace the non-crushed image.
-runOptipng=[boolean]
    If true and the command optipng is available, run optipng on "pngOutput".
    The best crushed image will replace the non-crushed image.

-verbose=[boolean]:
    Make output verbose or silent. Default is verbose (true).
-dryrun=[boolean]:
    Run the script but do not output any file.
-libpath=[directory]:
    Specify the directory where the spritifier libs have to be found (but
    default should be ok).
\n
TEXT;
  exit(64);
}

// READ ARGUMENTS, STORE THEM IN $options
$options = array();
for($i=1; $i<$argc; $i++)
{
  $value = $argv[$i];
  if(strpos($value, '=') === FALSE)
  {
    continue;
  }

  list($k, $v) = explode('=', $value);
  $k2 = trim($k, '-');
  $options[$k2] = $v;
}

// GENERIC OPTIONS
$verbose = (!array_key_exists('verbose', $options) || $options['verbose'] != 'false');
$dryrun  = (array_key_exists('dryrun', $options) && $options['dryrun'] === 'true');
$runPngcrush = (array_key_exists('runPngcrush', $options) && $options['runPngcrush'] === 'true');
$runOptipng  = (array_key_exists('runOptipng', $options) && $options['runOptipng'] === 'true');

// INCLUDE PATH
if(array_key_exists('libpath', $options) && !empty($options['libpath']))
{
  $libPath = $options['libpath'];
  set_include_path($libPath . PATH_SEPARATOR . get_include_path());
}


// OPTIONS
$validModes = array('icons');
$mode = array_key_exists('mode', $options) && in_array($options['mode'], $validModes) ? $options['mode'] : $validModes[0];

$validColorSort = array('0','1','2');
$sortByColor = array_key_exists('sortByColor', $options) && in_array($options['sortByColor'], $validColorSort) ? $options['sortByColor'] : $validColorSort[0];

$spriteWidth = array_key_exists('width', $options) && $options['width'] > 0
             ? (int) $options['width']
             : -1;

// CONFIGURATION
require_once 'AbstractSpritifier.php';
$modeConf = array();
// -- CONF FILE
if(array_key_exists('spritifierConf', $options) && !empty($options['spritifierConf']))
{
  require_once $options['spritifierConf'];
  $modeConf = $spf_conf[$mode];
}

// -- COMMAND LINE CONF
$copy = array('cssInput', 'cssOutput', 'pngOutput', 'pngWebUrl', 'imgRootPath');
foreach($copy as $name)
{
  if(array_key_exists($name, $options) && !empty($options[$name]))
  {
    $modeConf[$name] = $options[$name];
    // special case for pngWebUrl which default to pngOutput
    if($name == 'pngOutput')
    {
      $modeConf['pngWebUrl'] = basename($options[$name]);
    }
  }
}

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
    $spriter = new SpritifierIcons($modeConf);
    $spriter->setCliOptions(true, $verbose, $dryrun);
    $spriter->setOptions($sortByColor);
    $ret = $spriter->run($spriteWidth);
    break;
}

// IMAGE OPTIMIZATION
if ($spriter && ($runPngcrush || $runOptipng)) {
  $outputFiles = $spriter->getOutputFiles();
  foreach ($outputFiles as $spritename) {
    $fileToSize = array();
    if ($runPngcrush) {
      $outFile = $spritename.'.pngcrush';
      if ($verbose) {
        $cmd = 'command -v pngcrush && pngcrush '.$spritename.' '.$outFile;
        system($cmd, $exitCode); // system echo to stdout
      }
      else {
        $cmd = 'command -v pngcrush && pngcrush -q '.$spritename.' '.$outFile;
        exec($cmd); // with exec, nothing is echo'ed
      }
      if (file_exists($outFile)) {
        $fileToSize[$outFile] = filesize($outFile);
      }
    }
    if ($runOptipng) {
      $outFile = $spritename.'.optipng';
      if ($verbose) {
        $cmd = 'command -v optipng && optipng -out  '.$outFile.' '.$spritename;
        system($cmd, $exitCode); // system echo to stdout
      }
      else {
        $cmd = 'command -v optipng && optipng -quiet -out  '.$outFile.' '.$spritename;
        exec($cmd); // with exec, nothing is echo'ed
      }
      if (file_exists($outFile)) {
        $fileToSize[$outFile] = filesize($outFile);
      }
    }
    // choose the best optimization
    $bestFile = $spritename;
    $bestSize = filesize($spritename);
    foreach ($fileToSize as $name => $size) {
      if ($size < $bestSize) {
        $bestSize = $size;
        $bestFile = $name;
      }
    }
    if ($bestFile != $spritename) {
      // save the best file
      exec('cp '.$bestFile.' '.$spritename);
    }
    // remove temporary optimization files
    if (count($fileToSize) > 0) {
      exec('rm '.implode(' ', array_keys($fileToSize)));
    }
  }
}

exit($ret ? 0 : 1);
