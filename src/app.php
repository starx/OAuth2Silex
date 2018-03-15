<?php

use Silex\Provider\ServiceControllerServiceProvider;

$app = new \Silex\Application(['debug' => true]);
$app->register(new ServiceControllerServiceProvider());

$app->register(new Silex\Provider\SecurityServiceProvider());
$app['app.token_authenticator'] = function ($app) {
    return new App\Security\TokenAuthenticator($app['security.encoder_factory']);
};

$app->get('/api/test', function() use ($app) {
    /** @var \App\Services\UserService $userService */
    $userService = $app['service.user'];
    $allDetails = $userService->getAllUsers();
    return new \Symfony\Component\HttpFoundation\Response("Hello from API " . json_encode($allDetails));
});

return $app;
