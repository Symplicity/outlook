<?php

use Monolog\Logger;
use Symplicity\Outlook\Token;

// Get Authorization Url
$tokenAdapter = new Token('{{clientId}}', '{{clientSecret}}');
$url = $tokenAdapter->getAuthorizationUrl([
    'user_id' => '{{userId}}'
], '{{redirectUrl}}');

// Initialize token handler using the client-id and client-secret
$tokenHandler = new Token('{{clientId}}', '{{clientSecret}}', [
    'logger' => new Logger('symplicity_outlook_sync')
]);

// Get token
// Access token has a short lifespan, refresh token is valid for a longer period which allows you to
// get a new access token
// Use the refresh token method to get the new access token
try {
    $token = $tokenHandler->request('{{code_received_from_authorization_url}}', '{{redirect_url}}');

    // Get Required Info
    $accessToken = $token->getAccessToken();
    $refreshToken = $token->getRefreshToken();
    $expiresOn = $token->getExpiresIn();
    $email = $token->getEmailAddress();
    $name = $token->getDisplayName();
} catch (\Exception $e) {
    // Use logger to handle exception and retry.
}

// Refresh Token
try {
    $token = $tokenHandler->refresh('{{refresh_token_stored_in_persistent_db}}', '{{redirect_url}}');

    // Get Required Info
    $accessToken = $token->getAccessToken();
    $refreshToken = $token->getRefreshToken();
    $expiresOn = $token->getExpiresIn();
    $email = $token->getEmailAddress();
    $name = $token->getDisplayName();
} catch (\Exception $e) {
    // Use logger to handle exception and retry.
}
