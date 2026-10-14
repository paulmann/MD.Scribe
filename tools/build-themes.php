<?php
/**
 * MD.Viewer 3.0 — compile the theme catalog into public/assets/css/themes.css.
 * Run: php tools/build-themes.php   (also executed by tools/frontend/build.mjs)
 */
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';

use MdViewer\Theme\ThemeCatalog;

$catalog = new ThemeCatalog();
$css = $catalog->buildCss();
$out = __DIR__ . '/../public/assets/css/themes.css';
file_put_contents($out, $css);
echo "wrote {$out} (" . strlen($css) . " bytes)\n";
