<?php
/**
 * @author      Starx <contact@starx.io>
 * @copyright   Copyright (c) Nabin Nepal
 * @license     http://mit-license.org/
 *
 * @link        https://github.com/starx/OAuth2Silex
 */
ini_set('display_errors', 0);
require_once __DIR__.'/../vendor/autoload.php';

$app = new \Silex\Application();
require __DIR__.'/../config/prod.php';
require __DIR__ . '/../src/Application/app.php';
$app->run();