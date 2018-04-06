<?php
/**
 * @author      Starx <contact@starx.io>
 * @copyright   Copyright (c) Nabin Nepal
 * @license     http://mit-license.org/
 *
 * @link        https://github.com/starx/OAuth2Silex
 */
use Symfony\Component\Debug\Debug;
require_once __DIR__.'/../vendor/autoload.php';

Debug::enable();

$app = new \Silex\Application();
require __DIR__.'/../config/dev.php';
require __DIR__ . '/../src/Application/app.php';
$app->run();