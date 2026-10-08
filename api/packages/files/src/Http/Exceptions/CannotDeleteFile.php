<?php
namespace EscolaLms\Files\Http\Exceptions;

use EscolaLms\Files\Http\Exceptions\Contracts\Renderable;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class CannotDeleteFile extends \Exception implements Renderable
{
    /**
     * CannotDeleteFile constructor.
     * @param string $filename
     */
    public function __construct(string $filename)
    {
        parent::__construct(sprintf('Failed to delete the file "%s"', $filename));
    }

    function render(): Response
    {
        return new JsonResponse([
            'message' => $this->getMessage(),
        ], 400);
    }
}
