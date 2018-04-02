<?php

namespace OAuth2ServerExamples\Controllers\OAuth2;


use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use OAuth2ServerExamples\Controllers\AbstractController;
use OAuth2ServerExamples\Entities\UserEntity;
use Symfony\Bridge\PsrHttpMessage\Factory\DiactorosFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Zend\Diactoros\Stream;

class AuthorisationController extends AbstractController
{
    public function authorizeAction(Request $request)
    {
        $app = $this->getApp();

        /* @var AuthorizationServer $server */
        $server = $app['authorization.server'];
        /*
         * Factory class to convert Symfony Request to PSR7 complaint Request
         */
        $diactorosFactory = new DiactorosFactory();

        // Create the request using GLOBALS and convert
        $psr7Request = $diactorosFactory->createRequest($request);

        // Response
        $psr7Response = $diactorosFactory->createResponse(new Response());

        try {
            // Validate the HTTP request and return an AuthorizationRequest object.
            // The auth request object can be serialized into a user's session
            $authRequest = $server->validateAuthorizationRequest($psr7Request);

            // Once the user has logged in set the user on the AuthorizationRequest
            $authRequest->setUser(new UserEntity());

            // Once the user has approved or denied the client update the status
            // (true = approved, false = denied)
            $authRequest->setAuthorizationApproved(true);

            // Return the HTTP redirect response
            $psr7Response = $server->completeAuthorizationRequest($authRequest, $psr7Response);
        } catch (OAuthServerException $exception) {
            $psr7Response = $exception->generateHttpResponse($psr7Response);
        } catch (\Exception $exception) {
            $body = new Stream('php://temp', 'r+');
            $body->write($exception->getMessage());

            $psr7Response = $psr7Response->withStatus(500)->withBody($body);
        }

        /*
         * Factory class to convert PSR7 complaint response to Symfony response
         */
        $httpFoundationFactory = new HttpFoundationFactory();

        // Return the Symfony response
        return $httpFoundationFactory->createResponse($psr7Response);
    }


    public function accessTokenAction() {
        $app = $this->getApp();

        /* @var AuthorizationServer $server */
        $server = $app['authorization.server'];

        /*
         * Factory class to convert Symfony Request to PSR7 complaint Request
         */
        $diactorosFactory = new DiactorosFactory();

        // Create the request using GLOBALS and convert
        $request = Request::createFromGlobals();
        $psr7Request = $diactorosFactory->createRequest($request);

        // Response
        $psr7Response = $diactorosFactory->createResponse(new Response());
        try {

            // Try to respond to the request
            $psr7Response = $server->respondToAccessTokenRequest($psr7Request, $psr7Response);
        } catch (OAuthServerException $exception) {

            // All instances of OAuthServerException can be formatted into a HTTP response
            $psr7Response = $exception->generateHttpResponse($psr7Response);
        } catch (\Exception $exception) {
            // Unknown exception
            $body = new Stream('php://temp', 'r+');
            $body->write($exception->getMessage());

            $psr7Response = $psr7Response->withStatus(500)->withBody($body);
        }

        /*
         * Factory class to convert PSR7 complaint response to Symfony response
         */
        $httpFoundationFactory = new HttpFoundationFactory();

        // Return the Symfony response
        return $httpFoundationFactory->createResponse($psr7Response);
    }
}