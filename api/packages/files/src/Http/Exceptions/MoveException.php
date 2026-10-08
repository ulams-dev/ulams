<?php


namespace Ulams\Files\Http\Exceptions;


use Ulams\Files\Http\Exceptions\Contracts\Renderable;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class MoveException extends \Exception implements Renderable
{
    /**
     * MoveException constructor.
     *
     * @param string $sourceUrl
     * @param string $destinationUrl
     */
    public function __construct(string $sourceUrl, string $destinationUrl)
    {
        parent::__construct(sprintf('Cannot move file %s to %s', $sourceUrl, $destinationUrl));
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
