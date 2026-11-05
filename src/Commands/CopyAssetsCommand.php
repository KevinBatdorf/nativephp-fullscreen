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
            $this->writeIosLaunchSetting();
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

    private function writeIosLaunchSetting(): void
    {
        $on = (bool) config('fullscreen.on_launch');

        foreach (['NativePHP/Info.plist', 'NativePHP-simulator-Info.plist'] as $file) {
            $path = $this->buildPath().'/'.$file;

            if (! is_file($path)) {
                continue;
            }

            $plist = new \DOMDocument;
            $plist->load($path);
            $dict = $plist->getElementsByTagName('dict')->item(0);

            // Only remove UIStatusBarHidden if this setting added it, so an app's own value survives.
            $wasOn = $this->plistBool($dict, 'NativePHPFullscreenOnLaunch');

            $this->setPlistBool($plist, $dict, 'NativePHPFullscreenOnLaunch', $on);

            if ($on) {
                $this->setPlistBool($plist, $dict, 'UIStatusBarHidden', true);
            } elseif ($wasOn) {
                $this->removePlistKey($dict, 'UIStatusBarHidden');
            }

            $plist->save($path);
        }

        $this->info('Fullscreen on launch: '.($on ? 'true' : 'false'));
    }

    private function plistKey(\DOMElement $dict, string $key): ?\DOMElement
    {
        foreach ($dict->childNodes as $node) {
            if ($node instanceof \DOMElement && $node->tagName === 'key' && $node->textContent === $key) {
                return $node;
            }
        }

        return null;
    }

    private function plistBool(\DOMElement $dict, string $key): bool
    {
        $value = $this->plistKey($dict, $key)?->nextElementSibling;

        return $value?->tagName === 'true';
    }

    private function setPlistBool(\DOMDocument $plist, \DOMElement $dict, string $key, bool $value): void
    {
        $this->removePlistKey($dict, $key);

        $dict->appendChild($plist->createElement('key', $key));
        $dict->appendChild($plist->createElement($value ? 'true' : 'false'));
    }

    private function removePlistKey(\DOMElement $dict, string $key): void
    {
        $node = $this->plistKey($dict, $key);

        if ($node === null) {
            return;
        }

        if ($node->nextElementSibling) {
            $dict->removeChild($node->nextElementSibling);
        }

        $dict->removeChild($node);
    }
}
