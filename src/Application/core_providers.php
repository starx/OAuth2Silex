<?php
/**
 * @author      Starx <contact@starx.io>
 * @copyright   Copyright (c) Nabin Nepal
 * @license     http://mit-license.org/
 *
 * @link        https://github.com/starx/OAuth2Silex
 */
/*
 * Provides controllers
 */
$app->register(new \Silex\Provider\ServiceControllerServiceProvider());
/*
 * Provides Session
 */
$app->register(new \Silex\Provider\SessionServiceProvider());
/*
 * Provides Routing and also URL Generator
 */
$app->register(new \Silex\Provider\RoutingServiceProvider());
/*
 * Provides form services
 */
$app->register(new \Silex\Provider\FormServiceProvider());
/*
 * Provides Twig Templating
 */
$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => $app['base_path'].'/src/Views',
));
/*
 * Provider translation services
 */
$app->register(new Silex\Provider\LocaleServiceProvider());
$app->register(new Silex\Provider\TranslationServiceProvider(), array(
    'locale_fallbacks' => array('en'),
    'translator.messages' => array(),
));