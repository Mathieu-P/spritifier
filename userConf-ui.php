<?php

$filePath = dirname(__FILE__);

$WWW_ROOT_DIR = realpath($filePath . '/');


$CSS_ROOT_DIR = $WWW_ROOT_DIR . '/testProject/css/';
$IMG_ROOT_DIR = $WWW_ROOT_DIR . '';

$spf_conf = array(
  'icons' => array(
    // the path of the css file describing the icons to put in the sprite
    'cssInput'  => $CSS_ROOT_DIR . 'sprite_def.css',
    // the path for the css file written by the spritifier
    'cssOutput' => '/tmp/sprite_new.css',

    // the path for the png file written by the spritifier
    'pngOutput' => '/tmp/sprite.png',
    // the url used to load the png file (from the css file)
    'pngWebUrl' => '/img/sprite.png',

    'imgRootPath' => $IMG_ROOT_DIR,

    'dpis' => array(/*dpi name => ratio*/),
    'DEFAULT_WIDTH' => 152,
    'compaction' => AbstractSpritifier::COMPACTION_FILL_HOLES
  ),

);