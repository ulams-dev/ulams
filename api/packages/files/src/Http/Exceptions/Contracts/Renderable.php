<?php


namespace Ulams\Files\Http\Exceptions\Contracts;


use Symfony\Component\HttpFoundation\Response;

interface Renderable
{
    function render(): Response;
}
