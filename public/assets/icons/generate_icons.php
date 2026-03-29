<?php
/**
 * Generate simple branded PNG icons for PWA
 * Run this once: php generate_icons.php
 */

$sizes = [72, 96, 128, 144, 152, 192, 384, 512];
$output_dir = __DIR__;

foreach ($sizes as $size) {
    $img = imagecreatetruecolor($size, $size);
    if (!$img) continue;

    // Background gradient (dark blue)
    $bg = imagecolorallocate($img, 26, 26, 46); // #1a1a2e
    imagefill($img, 0, 0, $bg);

    // Rounded corners by drawing rounded rectangle
    $accent = imagecolorallocate($img, 102, 126, 234); // #667eea
    $radius = (int)($size * 0.2);
    imagefilledarc($img, $radius, $radius, $radius*2, $radius*2, 180, 270, $accent, IMG_ARC_PIE);
    imagefilledarc($img, $size-$radius, $radius, $radius*2, $radius*2, 270, 360, $accent, IMG_ARC_PIE);
    imagefilledarc($img, $radius, $size-$radius, $radius*2, $radius*2, 90, 180, $accent, IMG_ARC_PIE);
    imagefilledarc($img, $size-$radius, $size-$radius, $radius*2, $radius*2, 0, 90, $accent, IMG_ARC_PIE);
    imagefilledrectangle($img, $radius, 0, $size-$radius, $size, $accent);
    imagefilledrectangle($img, 0, $radius, $size, $size-$radius, $accent);

    // Draw "S" letter centered
    $text_color = imagecolorallocate($img, 255, 255, 255);
    $font_size = (int)($size * 0.5);
    $cx = (int)($size / 2);
    $cy = (int)($size / 2);

    // Use built-in GD font (no TTF needed)
    $gd_font = 5; // largest built-in font
    $char_w = imagefontwidth($gd_font);
    $char_h = imagefontheight($gd_font);
    $scale = max(1, (int)($font_size / $char_h));
    // Draw scaled "S" using simple rectangles (logo substitute)
    $bar_w = (int)($size * 0.08);
    $block = (int)($size * 0.38);
    $ox = (int)($size * 0.3);
    $oy = (int)($size * 0.2);
    // Top horizontal
    imagefilledrectangle($img, $ox, $oy, $ox+$block, $oy+$bar_w, $text_color);
    // Top vertical left
    imagefilledrectangle($img, $ox, $oy, $ox+$bar_w, $oy+$block/2, $text_color);
    // Middle horizontal
    imagefilledrectangle($img, $ox, $oy+(int)($block/2), $ox+$block, $oy+(int)($block/2)+$bar_w, $text_color);
    // Bottom vertical right
    imagefilledrectangle($img, $ox+$block-$bar_w, $oy+(int)($block/2), $ox+$block, $oy+$block, $text_color);
    // Bottom horizontal
    imagefilledrectangle($img, $ox, $oy+$block-$bar_w, $ox+$block, $oy+$block, $text_color);

    $path = $output_dir . "/icon-{$size}x{$size}.png";
    imagepng($img, $path);
    imagedestroy($img);
    echo "Generated: $path\n";
}

echo "Done!\n";
