<?php

/**
 * MD.Scribe 3.4 — updater entry for docroot-at-public installs (layout A).
 *
 * The updater lives at the project root, next to `src/`, because it needs the
 * whole tree. In the recommended layout the document root is `public/`, so the
 * root file was unreachable over HTTP: `GET /updater.php` answered 404 and the
 * machine API could not be used at all on such an install.
 *
 * This shim is the smallest possible bridge: it forwards to the real file, which
 * computes every path from its own `__DIR__` and therefore behaves identically
 * whether it is reached directly (layout B) or through here (layout A).
 *
 *   GET /updater.php                                    landing page (status)
 *   GET /updater.php?api_key=…&action=status&format=json machine API
 *   GET /updater.php?update=true                         apply (ALLOW_UPDATE)
 *   GET /updater.php?restore=latest                      restore (ALLOW_RESTORE)
 */

declare(strict_types=1);

require dirname(__DIR__) . '/updater.php';
