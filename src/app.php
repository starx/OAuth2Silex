<?php
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

$app = new \Silex\Application(['debug' => true]);
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
    'twig.path' => __DIR__.'/views',
));

$oauth2AuthProvider = new \OAuth2ServerExamples\Providers\OAuth2AuthProvider();
$app->register($oauth2AuthProvider);
$app->mount("/auth", $oauth2AuthProvider);

$oauth2APIProvider = new \OAuth2ServerExamples\Providers\OAuth2APIProvider();
$app->register($oauth2APIProvider);

$APIProvider = new \OAuth2ServerExamples\Providers\APIProvider();
$app->register($APIProvider);
$app->mount("/api", $APIProvider);

$app['base_path'] = function() {
    return realpath(__DIR__ . "/..");
};

/*
 * An example redirect URI to be redirected from the authorize code
 */
$app->get('/redirect_uri', function (Request $request) use ($app) {
    return new Response(<<<HTML
<html>
    <body>
    <table width="400px">
        <tr>
            <td>Code: </td>
            <td>{$request->get('code')}</td>
        </tr>
        <tr>
            <td>State: </td>
            <td>{$request->get('state')}</td>
        </tr>
    </table>
    </body>
</html>
HTML
    );
});

return $app;
