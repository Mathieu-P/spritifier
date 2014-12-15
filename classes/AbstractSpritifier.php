<?php
require_once 'cssparser/cssparser.php';

//define('SPRITE_GD', 0);
//define('SPRITE_IMAGICK', 1);

abstract class AbstractSpritifier
{
  const LIB_IMAGE_GD = 0;
  const CSS_NS = '-spritifier-';
  const DPI_BASE = 'baseDpi';

  const COMPACTION_FLOAT_LEFT = 1;
  const COMPACTION_FILL_HOLES = 2;

  protected $isCli = false;
  protected $isVerbose = true;
  protected $isDryRun = false;

  protected $outputPngPath = '_';
  protected $pngWebUrl = '_';
  protected $inputCssPath = '_';
  protected $outputCssPath = '_';
  protected $imgRootDir = '/';

  protected $conf = array();

  // internal variables
  protected $cssRules = array();
  protected $imageInfos = array();
  protected $positionsDpi = array();

  protected $outputFiles = array();

  /**
   * @var int 0 (no sort) 1 (brut sort) 2 (switch only)
   */
  protected $sortByColor = 0;

  protected $hasGD = false;

  function __construct()
  {
    $this->hasGD  = function_exists('imagecreatefrompng');

    $this->outputPngPath = $this->conf['pngOutput'];
    $this->pngWebUrl     = $this->conf['pngWebUrl'];
    $this->inputCssPath  = $this->conf['cssInput'];
    $this->outputCssPath = $this->conf['cssOutput'];
    $this->imgRootDir    = $this->conf['imgRootPath'];

    if (!preg_match('/\/$/', $this->imgRootDir))
    {
      $this->imgRootDir .= '/';
    }

  }

  abstract protected function getMode();

  public function setCliOptions($isCli = null, $isVerbose = null, $isDryRun = null)
  {
    if($isCli !== null)     $this->isCli = $isCli;
    if($isVerbose !== null) $this->isVerbose = $isVerbose;
    if($isDryRun !== null)  $this->isDryRun = $isDryRun;
  }

  public function setOptions($sortByColor = 0)
  {
    $this->sortByColor = $sortByColor;
  }

  protected function displayError($text)
  {
    if($this->isCli)
    {
      if(!$this->isVerbose)
      {
        return false;
      }
      fwrite(STDERR, "$text \n");
    }
    else
    {
      error_log($text);
    }
    return true;
  }
  protected function displayText($text, $addEOF = true)
  {
    if($this->isCli && !$this->isVerbose)
    {
      return false;
    }
    $eof = '';
    if($addEOF) $eof = $this->isCli ? "\n" : '<br />';
    echo $text . $eof;
    return true;
  }

  /*-----------*/

  public function run($maxWidth) {
    if($maxWidth <= 0)
    {
      $maxWidth = $this->conf['DEFAULT_WIDTH'];
    }

    $this->displayText("> Parse Input CSS file {$this->inputCssPath}");
    $this->parseInputCSS();

    $this->displayText("> Extract image information from CSS rules");
    $this->extractImageInfos();

    $this->displayText("> Create image positions");
    $this->createImagePositions($maxWidth);

    $this->displayText("> Generate output CSS file {$this->outputCssPath}");
    $this->generateOutputCSS();
    $this->displayText("> Generate output PNG files {$this->outputPngPath}");
    $this->generateOutputDpiImages();

    $this->displayText("> Now you may try to optimize the PNG through pngcrush or optipng");

    return true;
  }

  public function getPositionsAndInfos($maxWidth) {
    if($maxWidth <= 0)
    {
      $maxWidth = $this->conf['DEFAULT_WIDTH'];
    }

    $this->parseInputCSS();
    $this->extractImageInfos();
    $this->createImagePositions($maxWidth);
    return array($this->positionsDpi, $this->imageInfos);
  }

  /**
   * Parse the css file and find rules
   */
  protected function parseInputCSS() {
    if(!file_exists($this->inputCssPath))
    {
      $this->displayError("ERROR: File not found. Cannot load CSS definition file: {$this->inputCssPath}");
      exit(66);
    }
    $parser = new cssparser(false);
    $parser->Parse($this->inputCssPath);
    $this->cssRules = $parser->css;
  }

  /**
   * Extract image url, dimension and display properties from cssRules.
   * Create a separate info structure for each dpi encountered.
   */
  protected function extractImageInfos() {
    $infos = array(
      self::DPI_BASE => array()
    );
    foreach ($this->cssRules as $selector => $rules) {
      // find dpis
      $dpis = array(self::DPI_BASE);
      if(isset($rules[self::CSS_NS.'dpi'])) {
        $dpis = explode(' ', $rules[self::CSS_NS.'dpi']);
        $dpis[] = self::DPI_BASE;
        foreach($dpis as $dpi) {
          if (!isset($infos[$dpi])) {
            $infos[$dpi] = array();
          }
        }
      }

      // store infos for each dpi
      foreach($dpis as $dpi) {
        $hasError = false;
        if (isset($rules['background'])) {
          $imageUrl = $this->getImgFromBgRule($rules['background']);
        }
        else if (isset($rules['background-image'])) {
          $imageUrl = $this->getImgFromBgRule($rules['background-image']);
        }
        else {
          $this->displayError("WARNING: missing background or background-image rule for selector: $selector");
          $hasError = true;
        }

        if (!isset($rules['width'])) {
          $this->displayError("WARNING: missing width rule for selector: $selector");
          $hasError = true;
        }
        if (!isset($rules['height'])) {
          $this->displayError("WARNING: missing height rule for selector: $selector");
          $hasError = true;
        }
        if (!isset($rules['display'])) {
          $this->displayError("WARNING: missing display rule for selector: $selector");
          $hasError = true;
        }

        if ($hasError) {
          $this->displayError("WARNING: skipped selector: $selector");
          continue;
        }

        $dpiImageUrl = $imageUrl;
        if ($dpi != self::DPI_BASE) {
          $imageParts = explode('.', $imageUrl);
          $partToChange = count($imageParts) > 1 ? count($imageParts) - 2 : 0;
          $imageParts[$partToChange] = $imageParts[$partToChange] . $dpi;
          $dpiImageUrl = implode('.', $imageParts);
        }

        $customRules = array(
          'sprite-img' => $dpiImageUrl,
        ) + $rules;

        unset($customRules['background']);
        unset($customRules['background-image']);

        $infos[$dpi][$selector] = $customRules;
      }

    }

    $this->imageInfos = $infos;
  }

  private function getImgFromBgRule($bgRule)
  {
    $pattern = '/.*url\([\"\']?([a-zA-Z0-9\/\_\.\-]+)[\"\']?\).*/';
    preg_match($pattern, $bgRule, $matches);
    return isset($matches[1]) ? $matches[1] : '';
  }

  /**
   * sort images from $imageInfos according to their place in the final sprite
   * images
   */
  protected function sortImageInfos($reversed = false) {
    foreach($this->imageInfos as $dpi => $infos) {
      uasort($this->imageInfos[$dpi], array($this, "imageComp"));
      if ($reversed) {
        $this->imageInfos[$dpi] = array_reverse($this->imageInfos[$dpi]);
      }
    }
  }

  private function imageComp($a, $b)
  {
    // sort by height
    if(empty($a['height'])) return -1;
    if(empty($b['height'])) return 1;
    $dh = intval($a['height']) - intval($b['height']);
    $dw = intval($a['width']) - intval($b['width']);
    if($dh != 0) return $dh;
    // sort by colors
    if($this->sortByColor == 1
    || ($this->sortByColor == 2 && $dw == 0))
    {
      if($this->hasGD && !empty($a['sprite-img']) && !empty($b['sprite-img']))
      {
        $ia = $this->openFile($this->imgRootDir . $a['sprite-img']);
        $ib = $this->openFile($this->imgRootDir . $b['sprite-img']);
        if(!empty($ia) && !empty($ib))
        {
          // grayscale first
          $iag = $this->isGrayScale($ia, $a['width'], $a['height']);
          $ibg = $this->isGrayScale($ib, $b['width'], $b['height']);
          if($iag != $ibg) return ($iag ? -1 : 1);

          // sort by mean color
          $iam = $this->meanColorHsl($ia, $a['width'], $a['height']);
          $ibm = $this->meanColorHsl($ib, $b['width'], $b['height']);
          // compare hue only
          if($iam[0] != $ibm[0]) return ($iam[0] > $ibm[0] ? 1 : -1);
        }
      }
    }
    // sort by width
    return $dw;
  }

  private function openFile($path)
  {
    $ext = substr($path, -3);
    switch($ext)
    {
      case 'png': return imagecreatefrompng($path);
      case 'gif': return imagecreatefromgif($path);
    }
    return null;
  }

  private function isGrayScale($im, $w, $h)
  {
    for($i=0;$i<$h;$i++)
    {
      for($j=0;$j<$w;$j++)
      {
        $rgb = imagecolorat($im, $j, $i);
        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;
        if($r != $g || $g != $b) return false;
      }
    }
    return true;
  }

  private function meanColor($im, $w, $h)
  {
    $tr = 0;
    $tg = 0;
    $tb = 0;
    for($i=0;$i<$h;$i++)
    {
      for($j=0;$j<$w;$j++)
      {
        $rgb = imagecolorat($im, $j, $i);
        $tr += ($rgb >> 16) & 0xFF;
        $tg += ($rgb >> 8) & 0xFF;
        $tb += $rgb & 0xFF;
      }
    }
    $pix = $w * $h;
    $r = round($tr / $pix) << 16;
    $g = round($tg / $pix) << 8;
    $b = round($tb / $pix);
    return array($r, $g, $b);
  }

  private function meanColorHsl($im, $w, $h)
  {
    list($r, $g, $b) = $this->meanColor($im, $w, $h);
    return $this->rgb2hsl($r, $g, $b);
  }

  private function rgb2hsl($clrR, $clrG, $clrB)
  {
    $clrMin = min($clrR, $clrG, $clrB);
    $clrMax = max($clrR, $clrG, $clrB);
    $deltaMax = $clrMax - $clrMin;

    $L = ($clrMax + $clrMin) / 510;

    if (0 == $deltaMax){
      $H = 0;
      $S = 0;
    }
    else{
      if (0.5 > $L){
          $S = $deltaMax / ($clrMax + $clrMin);
      }
      else{
          $S = $deltaMax / (510 - $clrMax - $clrMin);
      }

      if ($clrMax == $clrR) {
          $H = ($clrG - $clrB) / (6.0 * $deltaMax);
      }
      else if ($clrMax == $clrG) {
          $H = 1/3 + ($clrB - $clrR) / (6.0 * $deltaMax);
      }
      else {
          $H = 2 / 3 + ($clrR - $clrG) / (6.0 * $deltaMax);
      }

      if (0 > $H) $H += 1;
      if (1 < $H) $H -= 1;
    }
    return array($H, $S, $L);
  }


  /**
   * Interpolate imageInfo to find what is their position into the sprite
   */
  public function createImagePositions($maxWidth) {
    $method = isset($this->conf['compaction'])
            ? $this->conf['compaction']
            : self::COMPACTION_FLOAT_LEFT;

    switch($method) {
      case self::COMPACTION_FILL_HOLES:
        $this->createImagePositions_fillHoles($maxWidth);
        break;
      case self::COMPACTION_FLOAT_LEFT:
        default:
        $this->createImagePositions_floatLeft($maxWidth);
        break;
    }
  }

  public function createImagePositions_floatLeft($maxWidth) {
    $this->displayText("> Sort images");
    $this->sortImageInfos(false);

    $dpiConfs = $this->conf['dpis'];
    $positionsDpi = array();

    // there is one sprite per dpi
    foreach($this->imageInfos as $dpi => $infos) {

      $ratio = isset($dpiConfs[$dpi]) ? $dpiConfs[$dpi] : 1;

      $displayedImg = array();
      $positions = array();
      $height = 0;

      $x = 0;
      $y = 0;
      $lineHeight = 0;
      $lostPixels = 0;

      foreach($infos as $selector => $rules)
      {
        $imgPath = $rules['sprite-img'];
        if(isset($displayedImg[$imgPath])) {
          // the image is already put in the sprite image. Add the known
          // position for the selector
          $oldSelector = $displayedImg[$imgPath];
          $positions[$selector] = $positions[$oldSelector];
          continue;
        }
        $displayedImg[$imgPath] = $selector;

        $imgFullPath = $this->getImagePath($imgPath);
        list($imgWidth, $imgHeight, $_type, $_attr) = getimagesize($imgFullPath);

        // end of line
        if($x + $imgWidth > $maxWidth * $ratio)
        {
          $x = 0;
          $y += $lineHeight;
        }

        $positions[$selector] = array($x, $y);

        $x += $imgWidth;

        $newLineHeight = max($lineHeight, $imgHeight);
        // add lost pixels in the current line
        $lostPixels += ($newLineHeight - $lineHeight) * $x;
        $lineHeight = $newLineHeight;
      }
      $y += $lineHeight;

      // add lost pixels at the end of the last line
      $lostPixels += ($maxWidth * $ratio - $x) * $lineHeight;

      $positionsDpi[$dpi] = array(
        'width' => $maxWidth * $ratio,
        'height' => $y,
        'lostPixels' => $lostPixels,
        'positions' => $positions
      );

    }

    $this->positionsDpi = $positionsDpi;
  }

  public function createImagePositions_fillHoles($maxWidth) {

    $this->displayText("> Sort images");
    $this->sortImageInfos(true);

    $dpiConfs = $this->conf['dpis'];
    $positionsDpi = array();

    // there is one sprite per dpi
    foreach($this->imageInfos as $dpi => $infos) {
      $ratio = isset($dpiConfs[$dpi]) ? $dpiConfs[$dpi] : 1;

      $displayedImg = array();
      $positions = array();

      $totalW = $maxWidth * $ratio;
      $totalH = 0;
      $lostPixels = 0;

      $availableSpaces = array();

      foreach($infos as $selector => $rules)
      {
        $imgPath = $rules['sprite-img'];
        if(isset($displayedImg[$imgPath])) {
          // the image is already put in the sprite image. Add the known
          // position for the selector
          $oldSelector = $displayedImg[$imgPath];
          $positions[$selector] = $positions[$oldSelector];
          continue;
        }
        $displayedImg[$imgPath] = $selector;

        $imgFullPath = $this->getImagePath($imgPath);
        list($imgWidth, $imgHeight, $_type, $_attr) = getimagesize($imgFullPath);

        $done = false;

        // search for the first available space big enough
        foreach($availableSpaces as $i => $space) {
          list($spaceX, $spaceY, $spaceW, $spaceH) = $space;
          if ($imgWidth <= $spaceW && $imgHeight <= $spaceH) {
            // ok enough room in this space
            $positions[$selector] = array($spaceX, $spaceY);
            // the chosen space is no more available.
            // but up to 2 new (smaller) spaces can be created
            $newSpaces = array();
            if ($imgHeight < $spaceH) {
              $newSpaces[] = array($spaceX, $spaceY+$imgHeight, $imgWidth, $spaceH-$imgHeight);
            }
            if ($imgWidth < $spaceW) {
              $newSpaces[] = array($spaceX+$imgWidth, $spaceY, $spaceW-$imgWidth, $spaceH);
            }
            array_splice($availableSpaces, $i, 1, $newSpaces);
            // go on with next image
            $done = true;
            break;
          }
        }
        if ($done) {
          continue;
        }

        // there is not enough available space. Create a new one to store image.
        $positions[$selector] = array(0, $totalH);
        if ($imgWidth < $totalW) {
          $availableSpaces[] = array($imgWidth, $totalH, $totalW-$imgWidth, $imgHeight);
        }
        $totalH += $imgHeight;
        // go on with next image
      }

      // count remaining available spaces
      foreach ($availableSpaces as $space) {
        list($spaceX, $spaceY, $spaceW, $spaceH) = $space;
        $lostPixels += ($spaceW*$spaceH);
      }

      $positionsDpi[$dpi] = array(
        'width' => $totalW,
        'height' => $totalH,
        'lostPixels' => $lostPixels,
        'positions' => $positions
      );

    }

    $this->positionsDpi = $positionsDpi;
  }

  /**
   * Generate and write the output css file
   */
  protected function generateOutputCSS() {
    $dpis = array_keys($this->imageInfos);
    if (in_array(self::DPI_BASE, $dpis)) {
      while ($dpis[0] !== self::DPI_BASE) {
        array_push(array_shift($dpis));
      }
    }

    $mainDpi = $dpis[0];

    $outputFile = $this->outputCssPath;
    $fileContent = '';

    /* all selectors : assign to sprite image dpi-dependent */
    $fileContent .= '/* Selectors assigned to sprite images */' . PHP_EOL;
    foreach ($dpis as $dpi) {
      $styleContent = '';

      // web url of the sprite image
      $weburlPngPath = $this->makeWebUrlImageFilename($dpi);

      $spriteRules = array(
        'background-image' => "url($weburlPngPath)"
      );

      if ($dpi == $mainDpi) {
        $spriteRules['display'] = 'inline-block';
        $spriteRules['background-repeat'] = 'no-repeat';

        // dimension of the sprite image
        $spriteInfo = $this->positionsDpi[$dpi];
        $spriteW = $spriteInfo['width'];
        $spriteH = $spriteInfo['height'];
        $spriteRules['background-size'] = $spriteW.'px '.$spriteH.'px';
      }


      // collect all selectors
      $selectors = array_keys($this->imageInfos[$dpi]);
      $selectorsString = implode(',', $selectors);

      $styleContent .= $this->cssToString(array(
        $selectorsString => $spriteRules
      ), true);


      // add dpi condition or not
      if ($dpi !== self::DPI_BASE) {
        $fileContent .= '@media(-webkit-min-device-pixel-ratio: 1.5), (min--moz-device-pixel-ratio: 1.5), (-o-min-device-pixel-ratio: 3/2), (min-resolution: 1.5dppx){' . PHP_EOL;
        $fileContent .= $styleContent;
        $fileContent .= '}' . PHP_EOL;
      }
      else {
        $fileContent .= $styleContent;
      }
    }

    /* each selector : assign dimension, display, position in sprite, dpi-agnostic */
    $fileContent .= '/* Selectors assigned to a position in sprite images */' . PHP_EOL;
    $selectorToRules = array();
    foreach ($this->imageInfos[$mainDpi] as $selector => $rules) {

      $selectorPosition = $this->positionsDpi[$mainDpi]['positions'][$selector];
      list($x, $y) = $selectorPosition;

      $newRules = array(
        'width' => $rules['width'],
        'height' => $rules['height'],
        'background-position' => '-' . $x . 'px -' . $y . 'px'
      );
      if (isset($rules['display']) && $rules['display'] !== 'inline-block') {
        $newRules['display'] = $rules['display'];
      }

      $selectorToRules[$selector] = $newRules;
    }
    $fileContent .= $this->cssToString($selectorToRules, true);

    if(!$this->isDryRun)
    {
      $this->displayText("Writing file {$outputFile} ...");
      $ret = file_put_contents($outputFile, $fileContent);
      if (!$ret)
      {
        return false;
      }
    }

    return true;
  }

  /**
   * Generate and write the output png files (one for each dpi)
   */
  protected function generateOutputDpiImages() {
    foreach($this->positionsDpi as $dpi => $description) {
      $this->generateOutputDpiImage($dpi);
    }
  }

  private function generateOutputDpiImage($dpi) {
    $imageInfos = $this->imageInfos[$dpi];
    $description = $this->positionsDpi[$dpi];
    $w = $description['width'];
    $h = $description['height'];
    $positions = $description['positions'];
    $lostPixels = $description['lostPixels'];
    $lostPixelsPC = round($lostPixels / ($w*$h) * 100, 2);

    // create new sprite
    if($this->conf['SPRITE_METHOD'] == self::LIB_IMAGE_GD)
    {
      $sprite = imagecreatetruecolor($w, $h);
      imagealphablending($sprite, TRUE);
      $bg = ImageColorAllocateAlpha($sprite, 255, 0, 0, 127);
      ImageFill($sprite, 0, 0 , $bg);
    }
    else
    {
      $this->displayError("ERROR: no method to create sprite image !");
      exit(66);
    }

    $imgAllSizes = 0;

    // copy images inside the sprite
    $countOK = 0;
    $countKO = 0;
    foreach($positions as $selector => $xy)
    {
      list($imageX, $imageY) = $xy;
      $imageRules = $imageInfos[$selector];
      $imagePath = $imageRules['sprite-img'];

      $img = $this->getImagePath($imagePath);
      list($imgWidth, $imgHeight, $_type, $_attr) = getimagesize($img);

      // copy
      if($this->conf['SPRITE_METHOD'] == self::LIB_IMAGE_GD)
      {
        $imgError = false;
        if(!file_exists($img))
        {
          $this->displayError("WARNING File not found: $img");
          $imgError = true;
        }
        else
        {
          $imgAllSizes += filesize($img);
          $ext = substr($img, -3);
          switch($ext)
          {
            case 'png' :
              $imagePart = imagecreatefrompng($img);
              imagealphablending($imagePart, TRUE);
              break;
            case 'gif' :
              $imagePart = imagecreatefromgif($img);
              break;
          }

          if($imagePart === false)
          {
            $this->displayError("WARNING Cannot load file: $img");
            $imgError = true;
          }
          else
          {
            $ok = imagecopy($sprite, $imagePart, $imageX, $imageY, 0, 0, $imgWidth, $imgHeight);
            if(!$ok)
            {
              $this->displayError("WARNING Cannot copy file to sprite: $img");
              $imgError = true;
            }
          }
        }

        $imgError ? $countKO++ : $countOK++;
      }
      //else
      //{
      //  $exec = "composite -verbose -format PNG32 -type TrueColor -quality 0 -geometry +{$left}+{$top} {$img} {$pathDst} {$outputPngPath}";
      //  $gen .= $exec . "\n";
      //  exec($exec);
      //}
    }

    if($this->conf['SPRITE_METHOD'] == self::LIB_IMAGE_GD)
    {
      imagesavealpha($sprite, TRUE);
      if(!$this->isDryRun)
      {
        $outputPngPath = $this->makeOutputImageFilename($dpi);

        $this->displayText("* Writing file {$outputPngPath} ...");
        $ret = imagepng($sprite, $outputPngPath);
        $this->addOutputFile($outputPngPath);
        if (!$ret)
        {
          return false;
        }
      }
      $this->displayText("Png dimensions (WxH) : $w x $h");
      $this->displayText("Inserted images : success $countOK ; failure $countKO");
      $this->displayText("Lost pixels in image: $lostPixels ($lostPixelsPC %)");
    }

    return true;
  }

  protected function addOutputFile($outputFile) {
    $this->outputFiles[] = $outputFile;
  }

  public function getOutputFiles() {
    return $this->outputFiles;
  }

  private function makeOutputImageFilename($dpi) {
    $outputPngPath = $this->outputPngPath;
    if ($dpi != self::DPI_BASE) {
      $outputPngParts = explode('.', $outputPngPath);
      $partToChange = 0; //count($outputPngParts) > 1 ? count($outputPngParts) - 2 : 0;
      $outputPngParts[$partToChange] = $outputPngParts[$partToChange] . $dpi;
      $outputPngPath = implode('.', $outputPngParts);
    }
    return $outputPngPath;
  }

  private function makeWebUrlImageFilename($dpi) {
    $weburlPngPath = $this->pngWebUrl;
    if ($dpi != self::DPI_BASE) {
      $weburlPngParts = explode('.', $weburlPngPath);
      $partToChange = 0; //count($weburlPngParts) > 1 ? count($weburlPngParts) - 2 : 0;
      $weburlPngParts[$partToChange] = $weburlPngParts[$partToChange] . $dpi;
      $weburlPngPath = implode('.', $weburlPngParts);
    }
    return $weburlPngPath;
  }

  private function getImagePath($imgPath) {
    $imgFullPath = $this->imgRootDir;
    if (substr($imgFullPath, -1, 1) === '/' && substr($imgPath, 0, 1) === '/') {
      $imgFullPath .= substr($imgPath, 1);
    }
    else {
      $imgFullPath .= $imgPath;
    }
    return $imgFullPath;
  }

  /**
   * Convert an array of css rules to string
   * @param array $css array($selector => array($keyRule1 => $valueRule1, $keyRule2 => $valueRule2) )
   * @param int $factorizeSelectors false : no css factorization,
   *                                 true  : group selectors with exact same rules
   */
  protected function cssToString($css, $factorizeSelectors = false) {
    if ($factorizeSelectors) {
      // store the different selectors for a given rule
      $rulestoSelectors = array();
    }
    // store the rule string for a selector
    $compactedRuleCss = array();

    foreach ($css as $selector => $rules) {
      $compactedRules = array();
      foreach($rules as $key => $value) {
        $compactedRules[] = $key . ':' . $value;
      }
      $rulesStr = '{' . implode(';', $compactedRules) . '}';

      if ($factorizeSelectors) {
        if (!isset($rulestoSelectors[$rulesStr])) {
          $rulestoSelectors[$rulesStr] = array();
        }
        $rulestoSelectors[$rulesStr][] = $selector;
      }
      else {
        $compactedRuleCss[] = $selector . $rulesStr . PHP_EOL;
      }
    }

    // build output string
    if ($factorizeSelectors) {
      foreach ($rulestoSelectors as $rulesStr => $selectors) {
        $selectorsStr = implode(',', $selectors);
        $compactedRuleCss[] = $selectorsStr . $rulesStr . PHP_EOL;
      }
    }

    return implode('',$compactedRuleCss);
  }

}