<?php

define('FILESIZE_LIMIT', 10); // megabytes

header('Access-Control-Allow-Headers: content-type');
header('Access-Control-Allow-Methods: OPTIONS, POST');
header('Access-Control-Allow-Origin: ' . $_ENV['ACCESS_CONTROL_ALLOW_ORIGIN']);

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
  exit();
}

// Error handler
function error($message, $status = 400) {
  http_response_code($status);
  echo json_encode([
    'message' => $message
  ]);
  exit();
}

// Validate POST parameters exist.
$post_params = [ 'dimension', 'direction', 'duration', 'matte', 'perFrame' ];
foreach ($post_params as $post_param) {
  if (!array_key_exists($post_param, $_POST)) {
    error('`' . $post_param . '` must exist.');
  }
}

// Validate FILES parameters exist.
if (!array_key_exists('sheet', $_FILES)) {
  error('No file was selected.');
}

// Validate enumerable parameters.
$enums = [
  'direction' => [ 'automatic', 'horizontal', 'vertical' ],
  'perFrame' => [ true, false ]
];
foreach ($enums as $post_param => $enum) {
  if (!in_array($_POST[$post_param], $enum)) {
    error('`' . $post_param . '` is invalid.');
  }
}

// Validate RegExp parameters.
$regexps = [
  'dimension' => '/^\d+$/',
  'duration' => '/^\d+$/',
  'matte' => '/^\#[\da-zA-Z]{6}$/'
];
foreach ($regexps as $post_param => $regexp) {
	if (!preg_match($regexp, $_POST[$post_param])) {
    error('`' . $post_param . '` is invalid.');
  }
}

// If the file expired,
if (!file_exists($_FILES['sheet']['tmp_name']))
  error('The uploaded file has expired. Please try again.');

// If the file is larger than XkB, give an error.
if ($_FILES['sheet']['size'] > 1024 * 1024 * FILESIZE_LIMIT)
  error('The uploaded file is greater than the ' . FILESIZE_LIMIT . ' megabyte limit.');

// Check if the image is valid by collecting metadata.
$size = getimagesize($_FILES['sheet']['tmp_name']);
if ($size == false)
  error('The uploaded file is not a valid image.');

preg_match('/^image\/(gif|jpeg|png)$/', $size['mime'], $type);

// Not a valid file type.
if (!array_key_exists(1, $type))
  error('The uploaded file must be a GIF, JPEG, or PNG.');
$type = $type[1];

$direction =
  $_POST['direction'] == 'automatic' ?
    $size[0] >= $size[1] ?
      'horizontal' :
      'vertical' :
  $_POST['direction'];

$dimension =
  $_POST['dimension'] == 0 ?
    $size[0] >= $size[1] ?
      $size[1] :
      $size[0] :
  $_POST['dimension'];

// There must be more than one frame.
if (
  $size[0] == $dimension &&
  $size[1] == $dimension
)
  error('The sprite sheet only contains one frame.');

// Sheet has to be divisible by sprite size.
if ($size[$direction == 'horizontal' ? 0 : 1] % $dimension) {
  error(
    'The ' .
    (
      $direction == 'horizontal' ?
        'width' :
        'height'
    ) .
    ' of the image is not divisible by ' .
    (
      $_POST['direction'] == 'automatic' ?
        'the ' . (
          $direction == 'horizontal' ?
          'height' :
          'width'
        ) :
        $dimension
    ) .
    '.'
  );
}

$count_frames = $size[$direction == 'horizontal' ? 0 : 1] / $dimension;

// convert matte from hex to rgb
preg_match('/^\#([\da-zA-Z]{2})([\da-zA-Z]{2})([\da-zA-Z]{2})$/', $_POST['matte'], $matte);
array_shift($matte);
$matte = array_map('base_convert', $matte, [ 16, 16, 16 ], [ 10, 10, 10 ]);

$f = 'imagecreatefrom' . $type;
$sheet = $f($_FILES['sheet']['tmp_name']);
$frames = [];
$duration = round($_POST['duration'] / 10); // GifCreator is off by 10x for some reason; 1 unit = 10ms
$durations = [];

// Calculate frames for GIF.
$height = $direction == 'horizontal' ? $size[1] : $dimension;
$width = $direction == 'horizontal' ? $dimension : $size[0];
for ($x = 0; $x < $count_frames; $x++) {
  $frame = imagecreatetruecolor($width, $height);
  //imagesavealpha($frame, true);
  $transparent = imagecolorallocate($frame, $matte[0], $matte[1], $matte[2]);
  imagefill($frame, 0, 0, $transparent);
  imagecolortransparent($frame, $transparent);
  imagecopyresampled(
    $frame, $sheet, // to frame, from sheet
    0, 0, // to
    $direction == 'horizontal' ? $x * $dimension : 0, $direction == 'vertical' ? $x * $dimension : 0, // from
    $width, $height, // to width/height
    $width, $height // from width/height
  );
  array_push($frames, $frame);

  // Duration of frame
  if ($_POST['perFrame'])
    array_push($durations, $duration);
  else {
    $d = round($duration / ($count_frames - $x));
    $duration -= $d;
    array_push($durations, $d);
  }
}

// Frames -> GIF
include '../inc/gif-creator.php';
$gc = new GifCreator\GifCreator();
$gc->create($frames, $durations, 0);
$gif = $gc->getGif();

http_response_code(200);
echo json_encode([
  'height' => $height,
  'image' => 'data:image/gif;base64,' . base64_encode($gif),
  'width' => $width
]);

?>
