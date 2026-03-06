<?php
/* ===========================================================
   ID Generator — Photo inside circle + centered, clear text
   - Locks to your template's outer ring (manual geometry)
   - Inner photo circle + pixel inset (neat rim, no spill)
   - Text centered horizontally and placed relative to circle
   - Saves PNG to /output and returns JSON
   =========================================================== */

define('TEMPLATE', __DIR__ . '/template/template.png');
define('FONT_BOLD', __DIR__ . '/fonts/Poppins-Bold.ttf');
define('FONT_REG',  __DIR__ . '/fonts/Poppins-Regular.ttf');

$CANVAS_W = 1080;
$CANVAS_H = 1080;

/* ---------------- OUTER ring (measure ONCE) ----------------
   These describe the big white ring printed on the card.
   Nudge CENTER_BIAS_* by ±1 if you ever see a 1–2 px offset.
----------------------------------------------------------- */
$OUTER_CX   = 540;    // center X of outer ring
$OUTER_CY   = 372;    // center Y of outer ring
$OUTER_DIAM = 700;    // diameter of outer ring

$CENTER_BIAS_X = 0;   // + right, − left (tiny nudge if needed)
$CENTER_BIAS_Y = 0;   // + down, − up   (tiny nudge if needed)

/* ---------------- Inner circle + photo size ----------------
   INNER = OUTER − 2*BORDER_SHRINK_PX     (keeps clear of ring thickness)
   PHOTO = INNER − 2*PHOTO_INSET_PX       (leaves small, even rim)
   Increase BORDER_SHRINK_PX or PHOTO_INSET_PX to make photo smaller.
----------------------------------------------------------- */
$BORDER_SHRINK_PX = 38;   // 26–40 typical; larger = smaller inner circle (safer)
$PHOTO_INSET_PX   = 22;   // larger = smaller photo (more rim inside the inner circle)

// Keep a bit more headroom in crop (negative = crop slightly higher)
$CROP_Y_BIAS_RATIO = -0.10;

/* ---------------- Text layout (centered, relative to circle) ----------------
   We place NAME and DESIGNATION relative to the circle bottom so the stack
   always looks optically centered, regardless of photo scale.
--------------------------------------------------------------------------- */
$NAME_SIZE_PT  = 40;
$DESIG_SIZE_PT = 30;

// Vertical gaps (in px) below the circle:
$NAME_GAP_BELOW_CIRCLE = 70;   // circle bottom -> NAME baseline
$LINE_GAP              = 60;   // NAME -> DESIGNATION baseline

// Hard floor to keep text above the logo area:
$SAFE_TEXT_BOTTOM = 864;       // DESIGNATION baseline will not go below this

// Long text will auto-fit to this width:
$TEXT_MAX_WIDTH_RATIO = 0.80;  // 80% of canvas width

// One-time guides (red=outer, green=inner, blue=photo):
$DEBUG_OVERLAY = false;

/* ---------------- Helpers ---------------- */
function load_image_any($path) {
    $info = getimagesize($path);
    if (!$info) return null;
    switch ($info['mime']) {
        case 'image/jpeg':
        case 'image/jpg': return imagecreatefromjpeg($path);
        case 'image/png': return imagecreatefrompng($path);
        case 'image/webp':
            return function_exists('imagecreatefromwebp') ? imagecreatefromwebp($path) : null;
        default: return null;
    }
}

function draw_centered_text($img, $text, $y, $size, $font, $color, $maxWidth = null, $minSize = 18) {
    if ($maxWidth !== null) {
        $s = $size;
        do {
            $bbox  = imagettfbbox($s, 0, $font, $text);
            $textW = abs($bbox[2] - $bbox[0]);
            if ($textW <= $maxWidth) break;
            $s--;
        } while ($s >= $minSize);
        $size = $s;
    }
    $bbox  = imagettfbbox($size, 0, $font, $text);
    $textW = abs($bbox[2] - $bbox[0]);
    $x     = (imagesx($img) - $textW) / 2;
    imagettftext($img, $size, 0, (int)$x, (int)$y, $color, $font, $text);
    return $size;
}

/* ---------------- Inputs & validation ---------------- */
if (!file_exists(TEMPLATE)) {
    http_response_code(500);
    exit('Template not found.');
}
if (!file_exists(FONT_BOLD) || !file_exists(FONT_REG)) {
    http_response_code(500);
    exit('Font files missing.');
}
if (empty($_FILES['photo']['tmp_name'])) {
    http_response_code(400);
    exit('Please upload a photo.');
}

$name        = strtoupper(trim($_POST['name'] ?? 'NAME'));
$designation = strtoupper(trim($_POST['designation'] ?? 'DESIGNATION'));

/* ---------------- Load template ---------------- */
$base = imagecreatefrompng(TEMPLATE);
imagesavealpha($base, true);

/* ---------------- Circle math ---------------- */
// Outer ring
$cxOuter   = (int)$OUTER_CX + (int)$CENTER_BIAS_X;
$cyOuter   = (int)$OUTER_CY + (int)$CENTER_BIAS_Y;
$diamOuter = (int)$OUTER_DIAM;

// Inner circle (true photo area)
$diamInner   = max(10, $diamOuter - 2 * (int)$BORDER_SHRINK_PX);
$radiusInner = (int)floor($diamInner / 2);

// Photo circle (slightly smaller than inner circle)
$targetDiam  = max(10, $diamInner - 2 * (int)$PHOTO_INSET_PX);
$radiusPhoto = (int)floor($targetDiam / 2);

/* ---------------- Prepare & scale uploaded photo ---------------- */
$src = load_image_any($_FILES['photo']['tmp_name']);
if (!$src) {
    http_response_code(400);
    exit('Unsupported photo format. Use JPG/PNG/WEBP.');
}

$sw = imagesx($src);
$sh = imagesy($src);
$side = min($sw, $sh);
$srcX = (int)(($sw - $side) / 2);
$srcY = (int)(($sh - $side) / 2);

// small upward bias for better headroom
$srcY = max(0, min($srcY + (int)round($CROP_Y_BIAS_RATIO * $side), $sh - $side));

// scale to target square with alpha
$photo = imagecreatetruecolor($targetDiam, $targetDiam);
imagealphablending($photo, false);
imagesavealpha($photo, true);
$transparent = imagecolorallocatealpha($photo, 0, 0, 0, 127);
imagefilledrectangle($photo, 0, 0, $targetDiam, $targetDiam, $transparent);
imagecopyresampled($photo, $src, 0, 0, $srcX, $srcY, $targetDiam, $targetDiam, $side, $side);
imagedestroy($src);

// clip to a perfect circle
for ($y = 0; $y < $targetDiam; $y++) {
    for ($x = 0; $x < $targetDiam; $x++) {
        $dx = $x - $radiusPhoto; $dy = $y - $radiusPhoto;
        if ($dx*$dx + $dy*$dy > $radiusPhoto*$radiusPhoto) {
            imagesetpixel($photo, $x, $y, $transparent);
        }
    }
}

/* ---------------- Composite inside INNER circle ---------------- */
$photoX = $cxOuter - $radiusPhoto;
$photoY = $cyOuter - $radiusPhoto;
imagecopy($base, $photo, $photoX, $photoY, 0, 0, $targetDiam, $targetDiam);

// Safety: clear any pixels outside INNER circle on the base
for ($y = 0; $y < $targetDiam; $y++) {
    for ($x = 0; $x < $targetDiam; $x++) {
        $gx = $photoX + $x;
        $gy = $photoY + $y;
        if ($gx < 0 || $gx >= $CANVAS_W || $gy < 0 || $gy >= $CANVAS_H) continue;
        $dx = $gx - $cxOuter; $dy = $gy - $cyOuter;
        if ($dx*$dx + $dy*$dy > $radiusInner*$radiusInner) {
            $clear = imagecolorallocatealpha($base, 0, 0, 0, 127);
            imagesetpixel($base, $gx, $gy, $clear);
        }
    }
}

imagedestroy($photo);

/* ---------------- Optional debug rings ---------------- */
if ($DEBUG_OVERLAY) {
    $red   = imagecolorallocatealpha($base, 255, 0, 0, 60);   // OUTER
    $green = imagecolorallocatealpha($base, 0, 200, 0, 60);   // INNER
    $blue  = imagecolorallocatealpha($base, 0, 128, 255, 60); // PHOTO
    imageellipse($base, $cxOuter, $cyOuter, $diamOuter, $diamOuter, $red);
    imageellipse($base, $cxOuter, $cyOuter, $diamInner, $diamInner, $green);
    imageellipse($base, $cxOuter, $cyOuter, $targetDiam, $targetDiam, $blue);
}

/* ---------------- Text (centered + relative to circle) ---------------- */
$white = imagecolorallocate($base, 255, 255, 255);
$maxTextWidth = (int)round($CANVAS_W * $TEXT_MAX_WIDTH_RATIO);

// Baselines relative to circle bottom (keeps layout balanced)
$circleBottom = $cyOuter + $radiusInner;
$nameY        = $circleBottom + $NAME_GAP_BELOW_CIRCLE;
$desigY       = $nameY + $LINE_GAP;

// Keep both lines above the logo zone
$desigY = min($desigY, $SAFE_TEXT_BOTTOM);
$nameY  = min($nameY,  $SAFE_TEXT_BOTTOM - max(28, ($DESIG_SIZE_PT + 14)));

// Draw (centered, auto-fit width)
draw_centered_text($base, $name,        $nameY,  $NAME_SIZE_PT,  FONT_BOLD, $white, $maxTextWidth);
draw_centered_text($base, $designation, $desigY, $DESIG_SIZE_PT, FONT_REG,  $white, $maxTextWidth);

/* ---------------- Save & respond ---------------- */
$outDir = __DIR__ . '/output';
if (!is_dir($outDir)) mkdir($outDir, 0777, true);

$file = 'id-' . time() . '.png';
$path = $outDir . '/' . $file;

imagesavealpha($base, true);
imagepng($base, $path);
imagedestroy($base);

header('Content-Type: application/json');
echo json_encode(['ok' => true, 'file' => $file, 'path' => $path]);