<?php
namespace EscolaLms\Files\Http\Exceptions;

use EscolaLms\Files\Http\Exceptions\Contracts\Renderable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Throwable;

class Handler extends \Illuminate\Foundation\Exceptions\Handler
{
    /**
     * Render an exception into an HTTP response.
     *
     * @param Request $request
     * @param Throwable $exception
     * @return Response
     *
     * @throws Throwable
     */
    public function render($request, Throwable $exception)
    {
        if ($exception instanceof Renderable) {
            return $exception->render();
        }
        else
        {
            return parent::render($request, $exception);
        }
    }

    /**
     * Report or log an exception.
     *
     * @param Throwable $exception
     * @return void
     * @throws Throwable
     */
    public function report(Throwable $exception)
    {
        if (app()->bound('sentry') && $this->shouldReport($exception)) {
            app('sentry')->captureException($exception);
        }

        parent::report($exception);
    }

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->renderable(function (AccessDeniedHttpException $exception, $request) {
            return response()->json(['error' => $exception->getMessage()], 403);
        });
    }
}
