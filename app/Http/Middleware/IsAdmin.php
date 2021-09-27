<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

class IsAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        try {
            if (JWTAuth::parseToken()->authenticate()->role !== 'admin') {
                return response([
                    'status' => false,
                    'message' => 'Permission denied.'
                ], 401);
            }
        } catch (JWTException $e) {

            return response([
                'status' => false,
                'message' => 'Token expired.'
            ], 401);
        }

        return $next($request);
    }
}
