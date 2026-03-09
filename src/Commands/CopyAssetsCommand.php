<?php

namespace KevinBatdorf\Fullscreen\Commands;

use Native\Mobile\Plugins\Commands\NativePluginHookCommand;

class CopyAssetsCommand extends NativePluginHookCommand
{
    protected $signature = 'nativephp:fullscreen:copy-assets';

    protected $description = 'Copy assets for Fullscreen plugin';

    public function handle(): int
    {
        if ($this->isAndroid()) {
            $this->info('No Android assets to copy for Fullscreen.');
        }

        if ($this->isIos()) {
            $this->info('No iOS assets to copy for Fullscreen.');
        }

        return self::SUCCESS;
    }
}
