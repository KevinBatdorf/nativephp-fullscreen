<?php

namespace KevinBatdorf\Fullscreen\Commands;

use KevinBatdorf\Fullscreen\LaunchSetting;
use Native\Mobile\Plugins\Commands\NativePluginHookCommand;

class CopyAssetsCommand extends NativePluginHookCommand
{
    protected $signature = 'nativephp:fullscreen:copy-assets';

    protected $description = 'Copy assets for Fullscreen plugin';

    public function handle(): int
    {
        $on = (bool) config('fullscreen.on_launch');

        if ($this->isAndroid()) {
            LaunchSetting::writeAndroid($this->buildPath(), $on);
        }

        if ($this->isIos()) {
            LaunchSetting::writeIos($this->buildPath(), $on);
        }

        if ($this->isAndroid() || $this->isIos()) {
            $this->info('Fullscreen on launch: '.($on ? 'true' : 'false'));
        }

        return self::SUCCESS;
    }
}
