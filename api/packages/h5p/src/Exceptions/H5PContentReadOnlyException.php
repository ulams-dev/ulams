<?php

namespace Ulams\H5P\Exceptions;

use LogicException;

class H5PContentReadOnlyException extends LogicException
{
    public function __construct()
    {
        parent::__construct('h5p.contents is owned by the H5P service; write through H5PServiceClientContract instead of Eloquent.');
    }
}
