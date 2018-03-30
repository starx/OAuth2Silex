# OAuth 2 Silex

This repository contains all the examples shown using Slim framework on PHP League's OAuth2 and show how to do them in Silex.

## Setup

It's very simple:

- Checkout the repository
- Setup a web server vhost to point to the `/web` folder of the project. (For the examples, I have used `http://www.oauth2-silex.test` as my host)
- Thats' it

----

Below you will find curl request showing different stuff from the library.

## Grant Types

### Auth Code Grant

#### Authorise

The following call will give the Authorisation Code

    curl --request GET \
      --url 'http://www.oauth2-silex.test/auth_code.php/authorize?response_type=code&client_id=myawesomeapp&scope=basic%20email&state=randomcsrftoken&redirect_uri=http%3A%2F%2Fwww.oauth2-silex.test%2Fauth_code.php%2Fredirect_uri' \
      --header 'accept: 1.0'
      
#### Access token

    curl --request POST \
      --url http://www.oauth2-silex.test/auth_code.php/access_token \
      --header 'accept: 1.0' \
      --header 'content-type: application/x-www-form-urlencoded' \
      --data 'grant_type=authorization_code&client_id=myawesomeapp&client_secret=abc123&code=<Auth Code>&redirect_uri=http%3A%2F%2Fwww.oauth2-silex.test%2Fauth_code.php%2Fredirect_uri'

### Client Credentials Grant

#### Access token      

    curl --request POST \
      --url http://www.oauth2-silex.test/client_credentials.php/access_token \
      --header 'accept: 1.0' \
      --header 'content-type: application/x-www-form-urlencoded' \
      --data 'grant_type=client_credentials&client_id=myawesomeapp&client_secret=abc123&scope=basic%20email'
      
### Password Grant

#### Access token

    curl --request POST \
      --url http://www.oauth2-silex.test/password.php/access_token \
      --header 'accept: 1.0' \
      --header 'content-type: application/x-www-form-urlencoded' \
      --data 'grant_type=password&client_id=myawesomeapp&client_secret=abc123&username=alex&password=whisky&scope=basic%20email'
      
### Refresh Token Grant

_(Note: Replace the place holder "<Refresh Token>" with the refresh token)_

    curl --request POST \
      --url 'http://www.oauth2-silex.test/refresh_token.php/access_token?=' \
      --header 'accept: 1.0' \
      --header 'content-type: application/x-www-form-urlencoded' \
      --data 'grant_type=refresh_token&client_id=myawesomeapp&client_secret=abc123&refresh_token=<Refresh Token>'


## Other Examples

### API

The following lists the users. _(Note: Replace the place holder "<Access Token>" with the access token)_

    curl --request GET \
      --url http://www.oauth2-silex.test/api.php/users \
      --header 'accept: 1.0' \
      --header 'authorization: Bearer <Access Token>'
      
### Middleware

This example shows how to use OAuth2 middleware to serve requests.

#### Access Token

The following call with give the access token using the middleware provided in the OAuth2 Library _(Note: Replace the place holder "<Auth Code>" with the authorisation code)_

    curl --request POST \
      --url http://www.oauth2-silex.test/middleware_use.php/access_token \
      --header 'accept: 1.0' \
      --header 'content-type: application/x-www-form-urlencoded' \
      --data 'grant_type=authorization_code&client_id=myawesomeapp&client_secret=abc123&code=<Auth Code>&redirect_uri=http%3A%2F%2Fwww.oauth2-silex.test%2Fauth_code.php%2Fredirect_uri'
      
#### User Details

The following call will give the user detail of the verifying user detail. _(Note: Replace the place holder "<Access Token>" with the access token)_

    curl --request GET \
      --url http://www.oauth2-silex.test/middleware_use.php/api/user \
      --header 'accept: 1.0' \
      --header 'authorization: Bearer <Access Token>'
      

Enjoy! 

~[Starx](http://mrnepal.com)
