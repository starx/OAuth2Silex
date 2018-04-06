<?php
/**
 * @author      Starx <contact@starx.io>
 * @copyright   Copyright (c) Nabin Nepal
 * @license     http://mit-license.org/
 *
 * @link        https://github.com/starx/OAuth2Silex
 */
// include the prod configuration
require __DIR__.'/prod.php';

// enable the debug mode
$app['debug'] = true;
// enable the dev mode
$app['dev'] = true;
