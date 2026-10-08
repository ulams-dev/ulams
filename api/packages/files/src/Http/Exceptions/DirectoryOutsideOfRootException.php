<?php
namespace EscolaLms\Files\Http\Exceptions;


use EscolaLms\Files\Http\Exceptions\Contracts\Renderable;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class DirectoryOutsideOfRootException extends \LogicException implements Renderable
{
    public function __construct(string $directory)
    {
        parent::__construct(sprintf('Directory "%s" is outside of allowed root', $directory));
    }

    function render(): Response
    {
        return new JsonResponse([
            'message' => $this->getMessage(),
        ], 405);
    }
}
