<?php
require_once 'AbstractSpritifier.php';

class SpritifierIcons extends AbstractSpritifier
{
  protected $conf = array(
    'DEFAULT_WIDTH' => 384,
    'DEFAULT_HEIGHT' => 282,
    'SPRITE_METHOD' => AbstractSpritifier::LIB_IMAGE_GD,
    'cssInput'  => 'spritifier_def.css',
    'cssOutput' => 'spritifier.css',
    'pngOutput' => 'spritifier.png',
    'pngWebUrl' => 'spritifier.png',
    'dpis' => array(/* dpi name => ratio */),
    'imgRootPath' => '.',
    'compaction' => AbstractSpritifier::COMPACTION_FLOAT_LEFT
  );

  function __construct($conf)
  {
    foreach($conf as $k => $v)
    {
      $this->conf[$k] = $v;
    }
    parent::__construct();
  }

  protected function getMode()
  {
    return 'icons';
  }
}
