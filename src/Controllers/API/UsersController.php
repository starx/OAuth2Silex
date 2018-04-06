<?php
/**
 * @author      Starx <contact@starx.io>
 * @copyright   Copyright (c) Nabin Nepal
 * @license     http://mit-license.org/
 *
 * @link        https://github.com/starx/OAuth2Silex
 */
namespace OAuth2ServerExamples\Controllers\API;

use OAuth2ServerExamples\Controllers\AbstractController;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Bridge\PsrHttpMessage\Factory\DiactorosFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class UsersController extends AbstractController
{

    public function getUsersAction(Request $request)
    {
        /*
         * Factory class to convert Symfony Request to PSR7 complaint Request
         */
        $diactorosFactory = new DiactorosFactory();
        /** @var ServerRequestInterface $psr7Request */
        $psr7Request = $diactorosFactory->createRequest($request);

        $users = [
            [
                'id' => 123,
                'name' => 'Alex',
                'email' => 'alex@thephpleague.com',
            ],
            [
                'id' => 124,
                'name' => 'Frank',
                'email' => 'frank@thephpleague.com',
            ],
            [
                'id' => 125,
                'name' => 'Phil',
                'email' => 'phil@thephpleague.com',
            ],
        ];

        $totalUsers = count($users);

        // If the access token doesn't have the `basic` scope hide users' names
        if (in_array('basic', $psr7Request->getAttribute('oauth_scopes', [])) === false) {
            for ($i = 0; $i < $totalUsers; $i++) {
                unset($users[$i]['name']);
            }
        }

        // If the access token doesn't have the `email` scope hide users' email addresses
        if (in_array('email', $psr7Request->getAttribute('oauth_scopes', [])) === false) {
            for ($i = 0; $i < $totalUsers; $i++) {
                unset($users[$i]['email']);
            }
        }

        // Response
        return new JsonResponse($users);
    }

    public function getUserAction(Request $request)
    {
        /*
         * Factory class to convert Symfony Request to PSR7 complaint Request
         */
        $diactorosFactory = new DiactorosFactory();
        /** @var ServerRequestInterface $psr7Request */
        $psr7Request = $diactorosFactory->createRequest($request);

        $params = [];

        if (in_array('basic', $psr7Request->getAttribute('oauth_scopes', []))) {
            $params = [
                'id' => 1,
                'name' => 'Alex',
                'city' => 'London',
            ];
        }

        if (in_array('email', $psr7Request->getAttribute('oauth_scopes', []))) {
            $params['email'] = 'alex@example.com';
        }

        // Response
        return new JsonResponse($params);
    }
}