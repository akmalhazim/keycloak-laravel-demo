<?php

namespace App\Http\Middleware;

use App\Keycloak\Keycloak;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;


class KeycloakAuthenticatedMiddleware
{
    protected $keycloak;

    public function __construct(Keycloak $keycloak)
    {
        $this->keycloak = $keycloak;
    }


    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if ($user = $this->keycloak->getUser()) {
            return $next($request);
        }

        return $this->keycloak->login();
    }
}
