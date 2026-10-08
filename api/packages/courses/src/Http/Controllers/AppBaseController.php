<?php

namespace Ulams\Courses\Http\Controllers;

use Ulams\Core\Http\Controllers\UlamsBaseController;

/**
 * SWAGGER_VERSION
 * This class should be parent class for other API controllers
 * Class AppBaseController
 */
class AppBaseController extends UlamsBaseController
{
    public function sendDataError($error, $data, $code = 422)
    {
        return $this->sendResponse($data, $error, $code);
    }
}
