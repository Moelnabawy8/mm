<?php

/**
 * MCS — Subfolder Entry Point
 * 
 * This file acts as a bridge for shared hosting (LiteSpeed/Apache)
 * where the root .htaccess rewrite to public/ may not work.
 * It simply loads the real entry point from the public directory.
 */

// Change to the public directory so all relative paths work
chdir(__DIR__ . '/public');

// Load the real Laravel entry point
require __DIR__ . '/public/index.php';
