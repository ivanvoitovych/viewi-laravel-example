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
