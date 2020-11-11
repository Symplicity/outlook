<?php
declare(strict_types=1);

namespace Symplicity\Outlook\Interfaces\Http;

interface BatchConnectionInterface extends ConnectionInterface
{
    /**
     * Batch post/get/patch using the guzzle pool handler.
     * @param RequestOptionsInterface $requestOptions
     * @return mixed
     */
    public function batch(RequestOptionsInterface $requestOptions);
}
