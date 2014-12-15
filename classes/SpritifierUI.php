<?php
class SpritifierUI {

  protected $positions;
  protected $imageInfo;
  protected $conf;

  public function display($positions, $imageInfo, $conf) {

    $this->positions = $positions;
    $this->imageInfo = $imageInfo;
    $this->conf = $conf;

    echo '<!DOCTYPE html><html>'
        . $this->getHead()
        . $this->getBody()
        . '</html>';

  }

  private function getHead() {

    $styles = $this->getStyles();

    return <<<TEXT
<head>
<title>Spritifier UI</title>
$styles
</head>
TEXT;
  }

  private function getStyles() {
    return <<<TEXT
<style type="text/css">
BODY {
  font-family:"Helvetica Neue",Helvetica,Arial,sans-serif;
  margin:20px;
  padding:0;
}

H1 {
  font-size:26px;
}
H2 {
  font-size:22px;
}
H1, H2, P {
  margin:0.5em 0;
}


INPUT,
TEXTAREA,
SELECT {
  border: 1px solid #CCCCCC;
  border-radius:3px;
  color: #555555;
  display: inline-block;
  font-size: 13px;
  height: 18px;
  line-height: 18px;
  margin-bottom: 9px;
  padding: 4px;
  width: 210px;
}


INPUT:focus,
TEXTAREA:focus {
  border-color: rgba(82, 168, 236, 0.8);
  box-shadow: 0 1px 1px rgba(0, 0, 0, 0.075) inset, 0 0 8px rgba(82, 168, 236, 0.6);
  outline: 0 none;
}


INPUT[type="button"],
INPUT[type="reset"],
INPUT[type="submit"] {
  height: auto;
  width: auto;
}


.btn {
  -moz-border-bottom-colors: none;
  -moz-border-left-colors: none;
  -moz-border-right-colors: none;
  -moz-border-top-colors: none;
  background-color: #FAFAFA;
  background-image: linear-gradient(#FFFFFF, #FFFFFF 25%, #E6E6E6);
  background-repeat: no-repeat;
  border-color: #CCCCCC #CCCCCC #BBBBBB;
  border-image: none;
  border-radius: 4px 4px 4px 4px;
  border-style: solid;
  border-width: 1px;
  box-shadow: 0 1px 0 rgba(255, 255, 255, 0.2) inset, 0 1px 2px rgba(0, 0, 0, 0.05);
  color: #333333;
  cursor: pointer;
  display: inline-block;
  font-size: 13px;
  line-height: 18px;
  padding: 4px 10px;
  text-align: center;
  text-shadow: 0 1px 1px rgba(255, 255, 255, 0.75);
}

.btn-red {
  background-color: #993129;
  background-image: -moz-linear-gradient(center top , #B74F47, #7B130B);
  border-color: rgba(0, 0, 0, 0.25) rgba(0, 0, 0, 0.35) rgba(0, 0, 0, 0.35) rgba(0, 0, 0, 0.25);
  color: #FFFFFF;
  text-shadow: 0 -1px 0 #7B130B;
}

.btn-green {
  background-color: #549E39;
  background-image: -moz-linear-gradient(center top , #72BC57, #36801B);
  border-color: rgba(0, 0, 0, 0.25) rgba(0, 0, 0, 0.35) rgba(0, 0, 0, 0.35) rgba(0, 0, 0, 0.25);
  color: #FFFFFF;
  text-shadow: 0 -1px 0 #36801B;
}

.btn-marron {
  background-color: #7F6D54;
  background-image: -moz-linear-gradient(center top , #9D8B72, #614F36);
  border-color: rgba(0, 0, 0, 0.25) rgba(0, 0, 0, 0.35) rgba(0, 0, 0, 0.35) rgba(0, 0, 0, 0.25);
  color: #FFFFFF;
  text-shadow: 0 -1px 0 #614F36;
}

.spritifier-preview {
  max-width: 1200px;
  min-width:30%;
  margin-right:20px;
}
.spritifier-preview,
.spritifier-conf {
  float:left;
}

</style>
TEXT;
  }

  private function getBody() {

    // generate image positions for base dpi
    $dpi = $this->getBaseDpi();
    $imageDiv = '';
    if ($dpi) {
      $imageDiv = $this->getImageRectangle($dpi);
    }

    // configuration file
    $configuration = $this->getConfigurationForm();

    // options
    $options = $this->getOptionsForm();


return <<<TEXT
<body>
<h1>Spritifier UI</h1>
<div class="spritifier-preview">
$imageDiv
</div>
<div class="spritifier-conf">
$configuration
$options
</div>
</body>
TEXT;
  }

  protected function getImageRectangle($dpi) {
    $positions = $this->positions[$dpi]['positions'];
    $spriteWidth = $this->positions[$dpi]['width'];
    $spriteHeight = $this->positions[$dpi]['height'];
    $lostPixels = $this->positions[$dpi]['lostPixels'];
    $lostPixelsPC = round($lostPixels / ($spriteWidth*$spriteHeight) * 100, 2);
    $infos = $this->imageInfo[$dpi];

    $rect = '<h2>Preview</h2>';
    $rect .= '<div style="width:'.$spriteWidth.'px;height:'.$spriteHeight.'px;border:1px solid #000;position:relative;">';
    foreach ($positions as $selector => $xy) {
      list($x, $y) = $xy;
      $info = $infos[$selector];
      $src = $info['sprite-img'];
      $rect .= '<img src="'.$src.'" style="left:'.$x.'px;top:'.$y.'px;position:absolute;" />';
    }
    $rect .= '</div>';
    $rect .= '<p>Lost pixels in image: '.$lostPixels.' ('.$lostPixelsPC.' %)</p>';
    return $rect;
  }

  private function getBaseDpi() {
    // find dpis
    $dpis = array_keys($this->positions);
    if (in_array(AbstractSpritifier::DPI_BASE, $dpis)) {
      return AbstractSpritifier::DPI_BASE;
    }
    if (count($dpis) > 0) {
      return $dpis[0];
    }
    return NULL;
  }

  protected function getConfigurationForm() {
    $conf = $this->conf;

    //  configuration files
    $confFileHtml = '';
    $userConfFiles = isset($conf['userConf-files']) && !empty($conf['userConf-files'])
      ? $conf['userConf-files']
      : array();
    $userConfIncluded = $conf['userConf-included'];
    foreach ($userConfFiles as $confName) {
      $confBasename = basename($confName);
      $checked = ($confBasename === $userConfIncluded ? 'checked="checked"' : '');
      $confFileHtml .= '<br /><label><input type="radio" name="userConfFile" value="'.$confBasename.'" '.$checked.'>'.$confBasename.'</label>';
    }
    if ($confFileHtml != '') {
      $confFileHtml = '<p>Configuration file'.$confFileHtml.'</p>';
    }

    // -- display form
    $form = <<<TEXT
<h2>Choose configuration file</h2>
<form method="GET" action="">
<p>
$confFileHtml
</p>
<input type="submit" value="Choose"  class="btn btn-green" />
</form>
TEXT;
    return $form;
  }

  protected function getOptionsForm() {
    $conf = $this->conf;

    // configuration file
    $userConfIncluded = $conf['userConf-included'];
    $configFileHtml = '<input type="hidden" name="userConfFile" value="'.$userConfIncluded.'">';

    // width
    $width = $conf['DEFAULT_WIDTH'];

    // compaction method
    $compaction_value = $conf['compaction'];
    $compaction_list = array(
      AbstractSpritifier::COMPACTION_FLOAT_LEFT,
      AbstractSpritifier::COMPACTION_FILL_HOLES,
    );
    $compaction = array();
    foreach ($compaction_list as $cptval) {
      $compaction[$cptval] = array(
        $cptval,
        ($cptval == $compaction_value) ? 'checked="checked"' : ''
      );
    }

    // -- display form
    $form = <<<TEXT
<h2>Manually change options</h2>
<form method="GET" action="">
$configFileHtml
<p>
Sprite width:<br />
<input type="text" name="DEFAULT_WIDTH" value="$width" />
</p>

<p>
<label><input type="radio" name="compaction" value="{$compaction[AbstractSpritifier::COMPACTION_FLOAT_LEFT][0]}" {$compaction[AbstractSpritifier::COMPACTION_FLOAT_LEFT][1]} />Method float-left</label><br />
<label><input type="radio" name="compaction" value="{$compaction[AbstractSpritifier::COMPACTION_FILL_HOLES][0]}" {$compaction[AbstractSpritifier::COMPACTION_FILL_HOLES][1]} />Method fill-holes</label>
</p>

<p>
<input type="button" value="∅ Reset all" class="btn btn-red" onclick="window.location.href=window.location.pathname" />
<input type="submit" value="↺ Refresh"  class="btn btn-green"/>
</p>

<h2>Export Sprite & Css</h2>
<p>
<input type="button" value="Export ↠"  class="btn btn-green" onclick="window.location.href=window.location.href+(window.location.search?'&':'?')+'export=1'" />
</p>

</form>
TEXT;
    return $form;
  }
}

?>