<?php

namespace App\ViewiLaravel;

use Illuminate\Http\JsonResponse;
use Viewi\Bridge\DefaultBridge;
use Illuminate\Http\Request;
use App\Http\Kernel;

class ViewiLaravelBridge extends DefaultBridge
{
    public function request(\Viewi\Components\Http\Message\Request $request): mixed
    {
        if ($request->isExternal) {
            return parent::request($request);
        }
        $laravelRequest = Request::create($request->url, strtoupper($request->method), [], $_COOKIE, [], $_SERVER, $request->body);
        $kernel = resolve(Kernel::class);
        $response = $kernel->handle($laravelRequest);
        if ($response instanceof JsonResponse) {
            return $response->original;
        } else {
            /** @var Response $response */
            return $response->getContent();
        }
    }
}
