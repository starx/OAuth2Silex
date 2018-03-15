<?php
/**
 * @author      Starx <contact@starx.io>
 * @copyright   Copyright (c) Nabin Nepal
 * @license     http://mit-license.org/
 *
 * @link        https://github.com/starx/OAuth2Silex
 */

date_default_timezone_set('UTC');
use Symfony\Component\Debug\Debug;

require_once __DIR__.'/../vendor/autoload.php';
Debug::enable();
/** @var \Silex\Application $app */
$app = require __DIR__.'/../src/app.php';
$app->run();
