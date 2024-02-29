# Migration

#### Handling migration from deprecated https://outlook.office.com to https://graph.microsoft.com
- [Concept](#concept)
- [Example](#example)

## Concept

If the access token was generated with the deprecated apis then the audience set in the access token jwt is https://outlook.office.com. In order to transfer the authorization to graph apis we need to refresh the token. Check for audience via the migration service and then force refresh the token.

```
{
    "aud": "https://outlook.office.com",
    "iat": 1607715484,
    "nbf": 1607715484,
    "exp": 1607719384,
}
```

## example

```php
$invalidToken = \Symplicity\Outlook\Utilities\MigrationService::isTokenInvalid('access_token');
if ($invalidToken) {
    // Token was generated using deprecated api
    $tokenHandler = new \Symplicity\Outlook\Token('clientId', 'clientSecret');
    // The new token generated will be issued by graph.microsoft.com
    $token = $tokenHandler->refresh('refresh_token', 'redirect_url')
}
```
