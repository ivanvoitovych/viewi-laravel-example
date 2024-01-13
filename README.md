# Viewi Laravel integration

## Create Laravel project

`composer create-project laravel/laravel app`

## Install viewi

`composer require viewi/viewi -W`

## Create Viewi app

Use `-a` parameter.

`vendor/bin/viewi new -a`



## Run web-server

`php -S localhost:8000 -t public/`

## Run NPM watch

In another terminal.

`cd viewi-app/js`

`npm run watch`



# Details

## Viewi routing

`routes\web.php`

Integrates Viewi routing.

You must remove a default `/` route and allow Viewi to handle your home page.

```php
<?php

use App\ViewiLaravel\ViewiLaravelBridge;
use Illuminate\Support\Facades\Route;
use Viewi\App;
use Viewi\Router\ComponentRoute;
use Illuminate\Http\Request;
use Viewi\Bridge\IViewiBridge;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/api', function () {
    return ['data' => ['Testing Viewi']];
});

function setUpViewi()
{
    /**
     * @var App
     */
    $app = require __DIR__ . '/../viewi-app/viewi.php';
    require __DIR__ . '/../viewi-app/routes.php';
    $viewiRouter = $app->router();
    $bridge = new ViewiLaravelBridge($app);
    $app->factory()->add(IViewiBridge::class, function () use ($bridge) {
        return $bridge;
    });

    Route::fallback(static function (Request $request)  use ($app, $viewiRouter) {
        $urlPath = $request->path();
        $requestMethod = $request->method();
        $match = $viewiRouter->resolve($urlPath, $requestMethod);
        if ($match === null) {
            throw new Exception('No route was matched!');
        }
        /** @var RouteItem */
        $routeItem = $match['item'];
        $action = $routeItem->action;

        if ($action instanceof ComponentRoute) {
            $viewiRequest = new Viewi\Components\Http\Message\Request($urlPath, strtolower($requestMethod));
            $viewiResponse = $app->engine()->render($action->component, $match['params'], $viewiRequest);
            if ($routeItem->transformCallback !== null && $viewiResponse instanceof Viewi\Components\Http\Message\Response) {
                $viewiResponse = ($routeItem->transformCallback)($viewiResponse);
            }
            $laravelResponse = response($viewiResponse->body, isset($viewiResponse->headers['Location']) ? 302 : $viewiResponse->status, $viewiResponse->headers);
            return $laravelResponse;
        } else {
            throw new Exception('Unknown action type.');
        }
    });
}

setUpViewi();
```

## API routes

`routes\api.php`

You should use `JsonResponse` in order to supply typed response to Viewi component.

You must use Viewi model classes defined within Viewi application, use mapping if necessary.

```php
<?php

use Components\Models\PostModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});


Route::get('posts/{id}', function (string $id) {
    $postModel          = new PostModel();
    $postModel->Id      = (int) $id;
    $postModel->Name    = 'Laravel ft. Viewi';
    $postModel->Version = 1;
    return new JsonResponse($postModel);
});
```

## Laravel bridge

`app\ViewiLaravel\ViewiLaravelBridge.php`

This allows you to handle internal requests during server-side rendering (SSR).

```php
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
```