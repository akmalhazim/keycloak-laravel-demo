<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    $user = app(\App\Keycloak\Keycloak::class)->getUser();
    $logoutUrl = app(\App\Keycloak\Keycloak::class)->getLogoutUrl();

    return view('welcome')->with(['user' => $user, 'logoutUrl' => $logoutUrl]);
})->middleware(\App\Http\Middleware\KeycloakAuthenticatedMiddleware::class);

Route::get('/keycloak/login', function () {
   return app(\App\Keycloak\Keycloak::class)->login();
});

Route::get('/keycloak/callback', function(\Illuminate\Http\Request  $request) {
    return app(\App\Keycloak\Keycloak::class)->callback();
});

Route::post('/keycloak/backchannel-logout', function () {
    $logoutToken = request()->logout_token;
    $segments = explode('.', $logoutToken);

    // TODO many more assertions required, ie signature check. but now we assert it's a backchannel request
    $payload = json_decode(base64_decode($segments[1]), true);
    \Illuminate\Support\Facades\Log::info($logoutToken);
    if (!isset($payload['events']['http://schemas.openid.net/event/backchannel-logout'])) {
        abort(400);
    }

    // remove session
    $keyclockSessions = \App\Models\KeycloakSession::where('keycloak_session_id', $payload['sid'])->get();
    foreach ($keyclockSessions as $session) {
        $session->delete();
    }

    return response()->json();
});
