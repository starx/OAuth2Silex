<?php
namespace OAuth2ServerExamples\Controllers\OAuth2Client;

use OAuth2ServerExamples\Controllers\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class OAuth2ClientController extends AbstractController
{
    /**
     * This action will show the code and state, passed from
     * the OAuth Authorisation server
     *
     * @param Request $request
     * @return Response
     */
    public function demoRedirectUriAction(Request $request) {
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
    }

}