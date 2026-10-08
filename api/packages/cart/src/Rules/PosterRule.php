<?php

namespace Ulams\Cart\Rules;

use Ulams\Cart\Enums\ConstantEnum;
use Ulams\Files\Rules\FileOrStringRule;

class PosterRule extends FileOrStringRule
{
    public function __construct($productId)
    {
        $prefixPath = ConstantEnum::DIRECTORY . '/' . $productId;

        parent::__construct(['image'], $prefixPath);
    }
}