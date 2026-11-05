<?php

namespace KevinBatdorf\Fullscreen;

use DOMDocument;
use DOMElement;

/**
 * Both natives read this before PHP exists, so it has to land in a file the
 * app can read at launch: an Android bool resource, Info.plist on iOS.
 */
class LaunchSetting
{
    /** Read by FullscreenLaunchProvider via Resources.getIdentifier(). */
    public const ANDROID_RESOURCE = 'nativephp_fullscreen_on_launch';

    /** Read by registerFullscreenOnLaunch() from the main bundle. */
    public const IOS_PLIST_KEY = 'NativePHPFullscreenOnLaunch';

    /** Hides the status bar on the launch screen, before any app code runs. */
    public const IOS_STATUS_BAR_KEY = 'UIStatusBarHidden';

    private const ANDROID_RESOURCE_FILE = 'app/src/main/res/values/nativephp_fullscreen.xml';

    private const IOS_PLISTS = ['NativePHP/Info.plist', 'NativePHP-simulator-Info.plist'];

    /** Written when off too, so turning the setting off takes effect on the next build. */
    public static function writeAndroid(string $buildPath, bool $on): ?string
    {
        $path = $buildPath.'/'.self::ANDROID_RESOURCE_FILE;
        $dir = dirname($path);

        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            return null;
        }

        $xml = implode("\n", [
            '<?xml version="1.0" encoding="utf-8"?>',
            '<resources>',
            '    <bool name="'.self::ANDROID_RESOURCE.'">'.($on ? 'true' : 'false').'</bool>',
            '</resources>',
            '',
        ]);

        return file_put_contents($path, $xml) === false ? null : $path;
    }

    public static function writeIos(string $buildPath, bool $on): array
    {
        $written = [];

        foreach (self::IOS_PLISTS as $file) {
            $path = $buildPath.'/'.$file;

            if (! is_file($path)) {
                continue;
            }

            $plist = new DOMDocument;

            if (! @$plist->load($path)) {
                continue;
            }

            $dict = $plist->getElementsByTagName('dict')->item(0);

            if (! $dict instanceof DOMElement) {
                continue;
            }

            // Only drop UIStatusBarHidden if this setting added it; an app's own value stays.
            $wasOn = self::readBool($dict, self::IOS_PLIST_KEY);

            self::writeBool($plist, $dict, self::IOS_PLIST_KEY, $on);

            if ($on) {
                self::writeBool($plist, $dict, self::IOS_STATUS_BAR_KEY, true);
            } elseif ($wasOn) {
                self::removeKey($dict, self::IOS_STATUS_BAR_KEY);
            }

            $plist->save($path);
            $written[] = $path;
        }

        return $written;
    }

    private static function findKey(DOMElement $dict, string $key): ?DOMElement
    {
        foreach ($dict->childNodes as $node) {
            if ($node instanceof DOMElement && $node->tagName === 'key' && $node->textContent === $key) {
                return $node;
            }
        }

        return null;
    }

    private static function readBool(DOMElement $dict, string $key): bool
    {
        return self::findKey($dict, $key)?->nextElementSibling?->tagName === 'true';
    }

    private static function writeBool(DOMDocument $plist, DOMElement $dict, string $key, bool $value): void
    {
        self::removeKey($dict, $key);

        $dict->appendChild($plist->createElement('key', $key));
        $dict->appendChild($plist->createElement($value ? 'true' : 'false'));
    }

    private static function removeKey(DOMElement $dict, string $key): void
    {
        $node = self::findKey($dict, $key);

        if ($node === null) {
            return;
        }

        if ($node->nextElementSibling) {
            $dict->removeChild($node->nextElementSibling);
        }

        $dict->removeChild($node);
    }
}
