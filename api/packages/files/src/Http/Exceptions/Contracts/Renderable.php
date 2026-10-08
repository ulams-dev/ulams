<?php


namespace EscolaLms\Files\Http\Exceptions\Contracts;


use Symfony\Component\HttpFoundation\Response;

interface Renderable
{
    function render(): Response;
}
