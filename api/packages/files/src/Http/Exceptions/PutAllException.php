<?php


namespace Ulams\Files\Http\Exceptions;


use Ulams\Files\Http\Exceptions\Contracts\Renderable;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class PutAllException extends \Exception implements Renderable
{
    /**
     * PutAllException constructor.
     * @param string $filename
     * @param string $directory
     */
    public function __construct(string $filename, string $directory)
    {
        parent::__construct(sprintf('Cannot put file %s to %s', $filename, $directory));
    }

    /**
     * @return Response
     */
    function render(): Response
    {
        return new JsonResponse([
            'message' => $this->getMessage(),
        ], 422);
    }
}
