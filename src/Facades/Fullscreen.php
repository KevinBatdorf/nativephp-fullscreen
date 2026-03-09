<?php

namespace KevinBatdorf\Fullscreen\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static bool enter()
 * @method static bool exit()
 * @method static bool isActive()
 *
 * @see \KevinBatdorf\Fullscreen\Fullscreen
 */
class Fullscreen extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \KevinBatdorf\Fullscreen\Fullscreen::class;
    }
}
