<?php


namespace EscolaLms\Pages\Http\Exceptions;


use EscolaLms\Pages\Models\Page;

class PageAlreadyExistsException extends \Exception
{
    public function __construct(Page $page)
    {
        parent::__construct(sprintf("Page with slug '%s' already exists", $page->slug));
    }
}
