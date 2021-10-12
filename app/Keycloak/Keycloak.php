<?php

namespace App\Keycloak;

use App\Models\KeycloakSession;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

class Keycloak
{
    const KEYCLOAK_SESSION_STATE = "_keycloak_state";
    const APP_STATE = "_app_state";
    const INTENDED_URL = "_intended_url";

    protected $host;
    protected $realm;
    protected $clientId;
    protected $callbackUrl;
    protected $state;

    public function __construct($host, $realm, $clientId)
    {
        $this->host = $host;
        $this->realm = $realm;
        $this->clientId = $clientId;
        $this->callbackUrl = url('/keycloak/callback');
    }

    public function getOpenIdConfig()
    {
        $host = $this->host;
        $realm = $this->realm;
        $response = Http::get("$host/auth/realms/$realm/.well-known/openid-configuration");

        return $response->json();
    }

    public function login()
    {
        $this->saveState();

        $config = $this->getOpenIdConfig();

        $query = http_build_query([
            'scope' => 'openid',
            'response_type' => 'code',
            'client_id' => $this->clientId,
            'redirect_uri' => $this->callbackUrl,
            'state' => $this->state,
        ]);

        $endpoint = $config['authorization_endpoint'].'?'.$query;

        return redirect($endpoint);
    }

    public function callback()
    {
        $appState = request()->query('state');
        $keycloakState = request()->query('session_state');
        $code = request()->query('code');

        if (empty($appState) || empty($keycloakState) || empty($code)) {
            abort(400);
        }

        if ($appState !== session()->get(self::APP_STATE)) {
            abort(400, 'invalid audience');
        }

        $user = $this->validateIdToken($keycloakState, $code);

        if (session()->has(self::INTENDED_URL)) {
            return redirect(session()->get(self::INTENDED_URL));
        }

        $this->resetState();

        return $user;
    }

    public function getLogoutUrl()
    {
        $config = $this->getOpenIdConfig();
        $query = http_build_query([
            'redirect_uri' => url('/')
        ]);
        return $config['end_session_endpoint'].'?'.$query;
    }

    public function getUser()
    {
        if (session()->has(self::KEYCLOAK_SESSION_STATE)) {
            $keycloakSession = KeycloakSession::firstWhere('keycloak_session_id', session()->get(self::KEYCLOAK_SESSION_STATE));

            if (!$keycloakSession) {
                // backchannel logout already happened
                session()->forget(self::KEYCLOAK_SESSION_STATE);
                return null;
            }
            return $keycloakSession->user;
        }
        return null;
    }

    protected function saveState()
    {
        $newState = $this->generateRandomState();

        $this->state = $newState;

        session()->put(self::APP_STATE, $newState);

        if (request()->isMethod('get') && request()->path() !== '/keycloak/login' && $redirectUrl = request()->url()) {
            session()->put(self::INTENDED_URL, $redirectUrl);
        }
    }

    protected function resetState()
    {
        $this->state = null;

        session()->forget(self::APP_STATE);
        session()->forget(self::INTENDED_URL);
    }

    public function validateIdToken($keyclockState, $code)
    {
        $config = $this->getOpenIdConfig();

        $endpoint = $config['token_endpoint'];

        $response = Http::asForm()->post($endpoint, [
            'code' => $code,
            'client_session_state' => $keyclockState,
            'client_id' => $this->clientId,
            'redirect_uri' => $this->callbackUrl,
            'grant_type' => 'authorization_code'
        ]);

        if (!$response->ok()) {
            abort(500);
        }

        $response = $response->json();

        // at this point, we trust the result since we query the server
        $segments = explode('.',  $response['id_token']);
        $payload = json_decode(base64_decode($segments[1]), true);

        $user = array_merge(Arr::only($payload, ['email', 'email_verified', 'preferred_username', 'given_name', 'family_name']), [
            'id' => $payload['sub']
        ]);
        $sessionId = $payload['sid'];

        $keycloakSession = KeycloakSession::firstOrCreate([
            'keycloak_session_id' => $sessionId
        ], [
            'user' => $user
        ]);

        session()->put(self::KEYCLOAK_SESSION_STATE, $sessionId);

        return $user;
    }

    protected function generateRandomState()
    {
        return bin2hex(random_bytes(16));
    }
}
