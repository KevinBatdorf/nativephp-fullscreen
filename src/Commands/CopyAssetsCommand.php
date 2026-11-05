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
            $this->writeAndroidLaunchSetting();
        }

        if ($this->isIos()) {
            $this->info('No iOS assets to copy for Fullscreen.');
        }

        return self::SUCCESS;
    }

    // Written when off too, so turning the setting off takes effect.
    private function writeAndroidLaunchSetting(): void
    {
        $value = config('fullscreen.on_launch') ? 'true' : 'false';
        $dir = $this->buildPath().'/app/src/main/res/values';

        if (! $this->ensureDirectory($dir)) {
            return;
        }

        file_put_contents($dir.'/nativephp_fullscreen.xml', <<<XML
        <?xml version="1.0" encoding="utf-8"?>
        <resources>
            <bool name="nativephp_fullscreen_on_launch">{$value}</bool>
        </resources>

        XML);

        $this->info("Fullscreen on launch: {$value}");
    }
}
