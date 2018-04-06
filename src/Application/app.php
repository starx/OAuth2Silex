<?php
/*
 * Register Core Functionality
 */
require __DIR__ . "/core_providers.php";

/*
 * Registers OAuth2 Authorization resources
 */
$oauth2AuthProvider = new \OAuth2ServerExamples\Providers\OAuth2AuthProvider();
$app->register($oauth2AuthProvider);
$app->mount("/auth", $oauth2AuthProvider);

/*
 * Register API resources
 */
$oauth2APIProvider = new \OAuth2ServerExamples\Providers\OAuth2APIProvider();
$app->register($oauth2APIProvider);

$APIProvider = new \OAuth2ServerExamples\Providers\APIProvider();
$app->register($APIProvider);
$app->mount("/api", $APIProvider);

/**
 * Register OAuth2 Client resources
 */
$oauth2ClientProvider = new \OAuth2ServerExamples\Providers\OAuth2ClientProvider();
$app->register($oauth2ClientProvider);
$app->mount("/", $oauth2ClientProvider);

return $app;
