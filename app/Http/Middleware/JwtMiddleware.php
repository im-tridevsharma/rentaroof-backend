<?php

namespace App\Http\Middleware;

use Closure;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\TokenBlacklistedException;

use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class JwtMiddleware
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
            if (!$user = JWTAuth::parseToken()->authenticate()) {
                return response([
                    'status' => false,
                    'message' => 'User not found (it might be deleted).'
                ], 404);
            }
        } catch (TokenBlacklistedException $e) {

            return response([
                'status' => false,
                'message' => 'Token expired.'
            ], 401);
        } catch (TokenExpiredException $e) {

            return response([
                'status' => false,
                'message' => 'Token expired.'
            ], 401);
        } catch (TokenInvalidException $e) {

            return response([
                'status' => false,
                'message' => 'Token invalid.'
            ], 401);
        } catch (JWTException $e) {

            return response([
                'status' => false,
                'message' => 'Token absent.'
            ], 401);
        }
        return $next($request);
    }
}
