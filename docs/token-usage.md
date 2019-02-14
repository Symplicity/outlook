# Getting Token

- AuthorizationURL(#authorization)
- Request(#request)
- Refresh(#refresh)

## Authorization
```php
<?php
require 'vendor/autoload.php';

$token = new \Symplicity\Outlook\Token([outlookClientId], [outlookClientSecret], ['logger' => $log]);
$token->getAuthorizationUrl($state, $redirectUrl);
```

## Request

Every request either requesting or refreshing a token will return a Token Entity

```php
<?php
require 'vendor/autoload.php';

$token = new \Symplicity\Outlook\Token([outlookClientId], [outlookClientSecret], ['logger' => $log]);
$tokenEntity = $token->request($code, $redirectUrl);
$accessToken = $tokenEntity->getAccessToken(); 
```

## Refresh

```php
<?php
require 'vendor/autoload.php';

$token = new \Symplicity\Outlook\Token([outlookClientId], [outlookClientSecret], ['logger' => $log]);
$tokenEntity = $token->refresh($refreshToken, $redirectUrl);
$newAccessToken = $tokenEntity->getAccessToken(); 
```