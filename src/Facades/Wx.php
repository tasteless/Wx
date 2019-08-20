<?php

namespace Hujing\Wx\Facades;

use Illuminate\Support\Facades\Facade;

class Wx extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'wx';
    }
}
