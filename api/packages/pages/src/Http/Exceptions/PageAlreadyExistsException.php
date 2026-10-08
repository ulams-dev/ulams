<?php


namespace Ulams\Pages\Http\Exceptions;


use Ulams\Pages\Models\Page;

class PageAlreadyExistsException extends \Exception
{
    public function __construct(Page $page)
    {
        parent::__construct(sprintf("Page with slug '%s' already exists", $page->slug));
    }
}
