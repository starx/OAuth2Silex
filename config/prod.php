<?php
/**
 * Production specific configuration
 */

date_default_timezone_set('UTC');
$app['application.name'] = 'OAuth2';
$app['application.version'] = '2.1.1';

$app['base_path'] = realpath(__DIR__ . "/..");