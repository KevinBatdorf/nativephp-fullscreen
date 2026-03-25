<?php

beforeEach(function () {
    $this->pluginPath = dirname(__DIR__);
    $this->manifestPath = $this->pluginPath.'/nativephp.json';

    // Helper: extract a rough section for a Kotlin inner class by name.
    $this->extractClassSection = function (string $code, string $className): string {
        $pattern = '/class\s+'.$className.'\(.*?\)\s*:\s*BridgeFunction\s*\{/s';
        if (! preg_match($pattern, $code, $match, PREG_OFFSET_CAPTURE)) {
            return '';
        }
        $start = $match[0][1];
        $depth = 0;
        $len = strlen($code);
        for ($i = $start; $i < $len; $i++) {
            if ($code[$i] === '{') {
                $depth++;
            }
            if ($code[$i] === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($code, $start, $i - $start + 1);
                }
            }
        }

        return substr($code, $start);
    };

    // Helper: extract a rough section for a Swift inner class by name.
    $this->extractSwiftClassSection = function (string $code, string $className): string {
        $pattern = '/class\s+'.$className.':\s*BridgeFunction\s*\{/s';
        if (! preg_match($pattern, $code, $match, PREG_OFFSET_CAPTURE)) {
            return '';
        }
        $start = $match[0][1];
        $depth = 0;
        $len = strlen($code);
        for ($i = $start; $i < $len; $i++) {
            if ($code[$i] === '{') {
                $depth++;
            }
            if ($code[$i] === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($code, $start, $i - $start + 1);
                }
            }
        }

        return substr($code, $start);
    };
});

// ---------------------------------------------------------------------------
// Plugin Manifest
// ---------------------------------------------------------------------------

describe('Plugin Manifest', function () {
    it('has a valid nativephp.json file', function () {
        expect(file_exists($this->manifestPath))->toBeTrue();

        $content = file_get_contents($this->manifestPath);
        $manifest = json_decode($content, true);

        expect(json_last_error())->toBe(JSON_ERROR_NONE);
    });

    it('has required fields', function () {
        $manifest = json_decode(file_get_contents($this->manifestPath), true);

        expect($manifest)->toHaveKeys(['namespace', 'bridge_functions']);
    });

    it('has the correct namespace', function () {
        $manifest = json_decode(file_get_contents($this->manifestPath), true);

        expect($manifest['namespace'])->toBe('Fullscreen');
    });

    it('has all three bridge functions', function () {
        $manifest = json_decode(file_get_contents($this->manifestPath), true);

        expect($manifest['bridge_functions'])->toHaveCount(3);

        $names = array_column($manifest['bridge_functions'], 'name');
        expect($names)->toContain('Fullscreen.Enter');
        expect($names)->toContain('Fullscreen.Exit');
        expect($names)->toContain('Fullscreen.IsActive');
    });

    it('has valid bridge functions with both platform targets', function () {
        $manifest = json_decode(file_get_contents($this->manifestPath), true);

        foreach ($manifest['bridge_functions'] as $function) {
            expect($function)->toHaveKeys(['name', 'android', 'ios']);
        }
    });

    it('has bridge function names following Namespace.Action convention', function () {
        $manifest = json_decode(file_get_contents($this->manifestPath), true);

        foreach ($manifest['bridge_functions'] as $function) {
            expect($function['name'])->toStartWith('Fullscreen.');
        }
    });

    it('has android bridge paths following Kotlin package convention', function () {
        $manifest = json_decode(file_get_contents($this->manifestPath), true);

        foreach ($manifest['bridge_functions'] as $function) {
            expect($function['android'])->toStartWith('com.kevinbatdorf.plugins.fullscreen.');
        }
    });

    it('has ios bridge paths following Swift convention', function () {
        $manifest = json_decode(file_get_contents($this->manifestPath), true);

        foreach ($manifest['bridge_functions'] as $function) {
            expect($function['ios'])->toStartWith('FullscreenFunctions.');
        }
    });

    it('has no events', function () {
        $manifest = json_decode(file_get_contents($this->manifestPath), true);

        expect($manifest['events'])->toBeEmpty();
    });

    it('has android configuration', function () {
        $manifest = json_decode(file_get_contents($this->manifestPath), true);

        expect($manifest['android'])->toHaveKeys(['permissions', 'min_version', 'dependencies']);
    });

    it('has ios configuration', function () {
        $manifest = json_decode(file_get_contents($this->manifestPath), true);

        expect($manifest['ios'])->toHaveKeys(['info_plist', 'min_version', 'dependencies']);
    });

    it('has copy_assets hook', function () {
        $manifest = json_decode(file_get_contents($this->manifestPath), true);

        expect($manifest['hooks']['copy_assets'])->toBe('nativephp:fullscreen:copy-assets');
    });
});

// ---------------------------------------------------------------------------
// Composer Configuration
// ---------------------------------------------------------------------------

describe('Composer Configuration', function () {
    it('has valid composer.json', function () {
        $composerPath = $this->pluginPath.'/composer.json';
        expect(file_exists($composerPath))->toBeTrue();

        $content = file_get_contents($composerPath);
        $composer = json_decode($content, true);

        expect(json_last_error())->toBe(JSON_ERROR_NONE);
        expect($composer['type'])->toBe('nativephp-plugin');
        expect($composer['extra']['nativephp']['manifest'])->toBe('nativephp.json');
    });

    it('requires php 8.2+', function () {
        $composer = json_decode(file_get_contents($this->pluginPath.'/composer.json'), true);

        expect($composer['require']['php'])->toBe('^8.2');
    });

    it('requires nativephp/mobile', function () {
        $composer = json_decode(file_get_contents($this->pluginPath.'/composer.json'), true);

        expect($composer['require'])->toHaveKey('nativephp/mobile');
    });

    it('registers service provider for auto-discovery', function () {
        $composer = json_decode(file_get_contents($this->pluginPath.'/composer.json'), true);

        expect($composer['extra']['laravel']['providers'])->toContain(
            'KevinBatdorf\\Fullscreen\\FullscreenServiceProvider'
        );
    });

    it('has correct PSR-4 autoload namespace', function () {
        $composer = json_decode(file_get_contents($this->pluginPath.'/composer.json'), true);

        expect($composer['autoload']['psr-4'])->toHaveKey('KevinBatdorf\\Fullscreen\\');
    });
});

// ---------------------------------------------------------------------------
// PHP Classes
// ---------------------------------------------------------------------------

describe('PHP Classes', function () {
    it('has service provider', function () {
        $file = $this->pluginPath.'/src/FullscreenServiceProvider.php';
        expect(file_exists($file))->toBeTrue();

        $content = file_get_contents($file);
        expect($content)->toContain('namespace KevinBatdorf\\Fullscreen');
        expect($content)->toContain('class FullscreenServiceProvider');
    });

    it('has facade', function () {
        $file = $this->pluginPath.'/src/Facades/Fullscreen.php';
        expect(file_exists($file))->toBeTrue();

        $content = file_get_contents($file);
        expect($content)->toContain('namespace KevinBatdorf\\Fullscreen\\Facades');
        expect($content)->toContain('class Fullscreen extends Facade');
    });

    it('has main implementation class', function () {
        $file = $this->pluginPath.'/src/Fullscreen.php';
        expect(file_exists($file))->toBeTrue();

        $content = file_get_contents($file);
        expect($content)->toContain('namespace KevinBatdorf\\Fullscreen');
        expect($content)->toContain('class Fullscreen');
    });

    it('has CopyAssetsCommand', function () {
        $file = $this->pluginPath.'/src/Commands/CopyAssetsCommand.php';
        expect(file_exists($file))->toBeTrue();

        $content = file_get_contents($file);
        expect($content)->toContain('extends NativePluginHookCommand');
    });
});

// ---------------------------------------------------------------------------
// Native Code
// ---------------------------------------------------------------------------

describe('Native Code', function () {
    it('has Android Kotlin file', function () {
        $kotlinFile = $this->pluginPath.'/resources/android/src/FullscreenFunctions.kt';

        expect(file_exists($kotlinFile))->toBeTrue();

        $content = file_get_contents($kotlinFile);
        expect($content)->toContain('package com.kevinbatdorf.plugins.fullscreen');
        expect($content)->toContain('object FullscreenFunctions');
        expect($content)->toContain('BridgeFunction');
    });

    it('has iOS Swift file', function () {
        $swiftFile = $this->pluginPath.'/resources/ios/Sources/FullscreenFunctions.swift';

        expect(file_exists($swiftFile))->toBeTrue();

        $content = file_get_contents($swiftFile);
        expect($content)->toContain('enum FullscreenFunctions');
        expect($content)->toContain('BridgeFunction');
    });

    it('has JavaScript module', function () {
        $jsFile = $this->pluginPath.'/resources/js/Fullscreen.js';

        expect(file_exists($jsFile))->toBeTrue();

        $content = file_get_contents($jsFile);
        expect($content)->toContain('export const Fullscreen');
        expect($content)->toContain('export default Fullscreen');
    });
});

// ---------------------------------------------------------------------------
// Fullscreen PHP class (non-native environment)
// ---------------------------------------------------------------------------

describe('Fullscreen PHP class (non-native environment)', function () {
    it('returns false for enter when nativephp_call is unavailable', function () {
        $fullscreen = new \KevinBatdorf\Fullscreen\Fullscreen;
        expect($fullscreen->enter())->toBeFalse();
    });

    it('returns false for exit when nativephp_call is unavailable', function () {
        $fullscreen = new \KevinBatdorf\Fullscreen\Fullscreen;
        expect($fullscreen->exit())->toBeFalse();
    });

    it('returns false for isActive when nativephp_call is unavailable', function () {
        $fullscreen = new \KevinBatdorf\Fullscreen\Fullscreen;
        expect($fullscreen->isActive())->toBeFalse();
    });
});

// ---------------------------------------------------------------------------
// Cross-Platform Consistency
// ---------------------------------------------------------------------------

describe('Cross-Platform Consistency', function () {
    it('has matching bridge function classes in Kotlin', function () {
        $manifest = json_decode(file_get_contents($this->manifestPath), true);
        $kotlinFile = $this->pluginPath.'/resources/android/src/FullscreenFunctions.kt';
        $kotlinContent = file_get_contents($kotlinFile);

        foreach ($manifest['bridge_functions'] as $function) {
            $parts = explode('.', $function['android']);
            $className = end($parts);
            expect($kotlinContent)->toContain("class {$className}");
        }
    });

    it('has matching bridge function classes in Swift', function () {
        $manifest = json_decode(file_get_contents($this->manifestPath), true);
        $swiftFile = $this->pluginPath.'/resources/ios/Sources/FullscreenFunctions.swift';
        $swiftContent = file_get_contents($swiftFile);

        foreach ($manifest['bridge_functions'] as $function) {
            $parts = explode('.', $function['ios']);
            $className = end($parts);
            expect($swiftContent)->toContain("class {$className}");
        }
    });

    it('returns consistent status response format across platforms', function () {
        $kotlinFile = $this->pluginPath.'/resources/android/src/FullscreenFunctions.kt';
        $swiftFile = $this->pluginPath.'/resources/ios/Sources/FullscreenFunctions.swift';

        $kotlinContent = file_get_contents($kotlinFile);
        $swiftContent = file_get_contents($swiftFile);

        // Both use "status" to "active" for Enter
        expect($kotlinContent)->toContain('"status" to "active"');
        expect($swiftContent)->toContain('"status": "active"');

        // Both use "status" to "inactive" for Exit
        expect($kotlinContent)->toContain('"status" to "inactive"');
        expect($swiftContent)->toContain('"status": "inactive"');

        // Both use "active" key for IsActive
        expect($kotlinContent)->toContain('"active" to');
        expect($swiftContent)->toContain('"active":');
    });
});

// ---------------------------------------------------------------------------
// Android Kotlin Code
// ---------------------------------------------------------------------------

describe('Android Kotlin Code', function () {
    it('uses the correct package name', function () {
        $content = file_get_contents($this->pluginPath.'/resources/android/src/FullscreenFunctions.kt');

        expect($content)->toContain('package com.kevinbatdorf.plugins.fullscreen');
    });

    it('imports BridgeFunction and BridgeResponse', function () {
        $content = file_get_contents($this->pluginPath.'/resources/android/src/FullscreenFunctions.kt');

        expect($content)->toContain('import com.nativephp.mobile.bridge.BridgeFunction');
        expect($content)->toContain('import com.nativephp.mobile.bridge.BridgeResponse');
    });

    it('implements BridgeFunction interface for all bridge classes', function () {
        $content = file_get_contents($this->pluginPath.'/resources/android/src/FullscreenFunctions.kt');

        expect($content)->toContain('class Enter(private val activity: FragmentActivity) : BridgeFunction');
        expect($content)->toContain('class Exit(private val activity: FragmentActivity) : BridgeFunction');
        expect($content)->toContain('class IsActive(private val activity: FragmentActivity) : BridgeFunction');
    });

    it('uses FragmentActivity constructor parameter', function () {
        $content = file_get_contents($this->pluginPath.'/resources/android/src/FullscreenFunctions.kt');

        expect($content)->toContain('FragmentActivity');
    });

    it('uses WindowInsetsControllerCompat for system bar control', function () {
        $content = file_get_contents($this->pluginPath.'/resources/android/src/FullscreenFunctions.kt');

        expect($content)->toContain('WindowInsetsControllerCompat');
        expect($content)->toContain('WindowInsetsCompat.Type.systemBars()');
    });

    it('uses sticky immersive behavior', function () {
        $content = file_get_contents($this->pluginPath.'/resources/android/src/FullscreenFunctions.kt');

        expect($content)->toContain('BEHAVIOR_SHOW_TRANSIENT_BARS_BY_SWIPE');
    });

    it('uses main thread for window operations', function () {
        $content = file_get_contents($this->pluginPath.'/resources/android/src/FullscreenFunctions.kt');

        expect($content)->toContain('runOnMainThreadBlocking');
        expect($content)->toContain('Handler(Looper.getMainLooper())');
    });

    it('uses BridgeResponse.success for all success responses', function () {
        $content = file_get_contents($this->pluginPath.'/resources/android/src/FullscreenFunctions.kt');

        preg_match_all('/BridgeResponse\.success/', $content, $matches);
        expect(count($matches[0]))->toBeGreaterThanOrEqual(3);
    });

    it('uses BridgeResponse.error for error handling', function () {
        $content = file_get_contents($this->pluginPath.'/resources/android/src/FullscreenFunctions.kt');

        expect($content)->toContain('BridgeResponse.error');
    });
});

// ---------------------------------------------------------------------------
// iOS Swift Code
// ---------------------------------------------------------------------------

describe('iOS Swift Code', function () {
    it('imports required frameworks', function () {
        $content = file_get_contents($this->pluginPath.'/resources/ios/Sources/FullscreenFunctions.swift');

        expect($content)->toContain('import Foundation');
        expect($content)->toContain('import UIKit');
    });

    it('implements BridgeFunction protocol for all bridge classes', function () {
        $content = file_get_contents($this->pluginPath.'/resources/ios/Sources/FullscreenFunctions.swift');

        expect($content)->toContain('class Enter: BridgeFunction');
        expect($content)->toContain('class Exit: BridgeFunction');
        expect($content)->toContain('class IsActive: BridgeFunction');
    });

    it('uses prefersStatusBarHidden for status bar control', function () {
        $content = file_get_contents($this->pluginPath.'/resources/ios/Sources/FullscreenFunctions.swift');

        expect($content)->toContain('prefersStatusBarHidden');
    });

    it('uses prefersHomeIndicatorAutoHidden for home indicator', function () {
        $content = file_get_contents($this->pluginPath.'/resources/ios/Sources/FullscreenFunctions.swift');

        expect($content)->toContain('prefersHomeIndicatorAutoHidden');
    });

    it('uses method swizzling for view controller override', function () {
        $content = file_get_contents($this->pluginPath.'/resources/ios/Sources/FullscreenFunctions.swift');

        expect($content)->toContain('method_setImplementation');
        expect($content)->toContain('swizzleOnce');
    });

    it('guards against double swizzling', function () {
        $content = file_get_contents($this->pluginPath.'/resources/ios/Sources/FullscreenFunctions.swift');

        expect($content)->toContain('guard !swizzled');
    });

    it('calls setNeedsStatusBarAppearanceUpdate after changes', function () {
        $content = file_get_contents($this->pluginPath.'/resources/ios/Sources/FullscreenFunctions.swift');

        expect($content)->toContain('setNeedsStatusBarAppearanceUpdate');
        expect($content)->toContain('setNeedsUpdateOfHomeIndicatorAutoHidden');
    });

    it('uses DispatchQueue.main.sync for UI operations', function () {
        $content = file_get_contents($this->pluginPath.'/resources/ios/Sources/FullscreenFunctions.swift');

        expect($content)->toContain('DispatchQueue.main.sync');
    });

    it('uses BridgeResponse.success for all success responses', function () {
        $content = file_get_contents($this->pluginPath.'/resources/ios/Sources/FullscreenFunctions.swift');

        preg_match_all('/BridgeResponse\.success/', $content, $matches);
        expect(count($matches[0]))->toBeGreaterThanOrEqual(3);
    });
});

// ---------------------------------------------------------------------------
// JavaScript Module
// ---------------------------------------------------------------------------

describe('JavaScript Module', function () {
    it('exports Fullscreen object with all methods', function () {
        $content = file_get_contents($this->pluginPath.'/resources/js/Fullscreen.js');

        expect($content)->toContain('export const Fullscreen');
        expect($content)->toContain('enter');
        expect($content)->toContain('exit');
        expect($content)->toContain('isActive');
    });

    it('uses correct bridge call method names', function () {
        $content = file_get_contents($this->pluginPath.'/resources/js/Fullscreen.js');

        expect($content)->toContain("'Fullscreen.Enter'");
        expect($content)->toContain("'Fullscreen.Exit'");
        expect($content)->toContain("'Fullscreen.IsActive'");
    });

    it('has default export', function () {
        $content = file_get_contents($this->pluginPath.'/resources/js/Fullscreen.js');

        expect($content)->toContain('export default Fullscreen');
    });

    it('handles CSRF token', function () {
        $content = file_get_contents($this->pluginPath.'/resources/js/Fullscreen.js');

        expect($content)->toContain('X-CSRF-TOKEN');
        expect($content)->toContain('meta[name="csrf-token"]');
    });

    it('calls the NativePHP bridge API endpoint', function () {
        $content = file_get_contents($this->pluginPath.'/resources/js/Fullscreen.js');

        expect($content)->toContain('/_native/api/call');
    });

    it('uses POST method for bridge calls', function () {
        $content = file_get_contents($this->pluginPath.'/resources/js/Fullscreen.js');

        expect($content)->toContain("method: 'POST'");
    });

    it('sends Content-Type JSON header', function () {
        $content = file_get_contents($this->pluginPath.'/resources/js/Fullscreen.js');

        expect($content)->toContain("'Content-Type': 'application/json'");
    });
});

// ---------------------------------------------------------------------------
// PHP Method Signatures
// ---------------------------------------------------------------------------

describe('PHP Method Signatures', function () {
    it('enter returns bool', function () {
        $content = file_get_contents($this->pluginPath.'/src/Fullscreen.php');

        expect($content)->toContain('public function enter(): bool');
    });

    it('exit returns bool', function () {
        $content = file_get_contents($this->pluginPath.'/src/Fullscreen.php');

        expect($content)->toContain('public function exit(): bool');
    });

    it('isActive returns bool', function () {
        $content = file_get_contents($this->pluginPath.'/src/Fullscreen.php');

        expect($content)->toContain('public function isActive(): bool');
    });

    it('guards all bridge calls with function_exists check', function () {
        $content = file_get_contents($this->pluginPath.'/src/Fullscreen.php');

        preg_match_all("/function_exists\('nativephp_call'\)/", $content, $matches);
        expect(count($matches[0]))->toBe(3);
    });
});

// ---------------------------------------------------------------------------
// Facade PHPDoc
// ---------------------------------------------------------------------------

describe('Facade PHPDoc', function () {
    it('documents all three methods', function () {
        $content = file_get_contents($this->pluginPath.'/src/Facades/Fullscreen.php');

        expect($content)->toContain('@method static bool enter()');
        expect($content)->toContain('@method static bool exit()');
        expect($content)->toContain('@method static bool isActive()');
    });

    it('references the correct implementation class', function () {
        $content = file_get_contents($this->pluginPath.'/src/Facades/Fullscreen.php');

        expect($content)->toContain('@see \\KevinBatdorf\\Fullscreen\\Fullscreen');
    });
});

// ---------------------------------------------------------------------------
// Service Provider
// ---------------------------------------------------------------------------

describe('Service Provider', function () {
    it('registers Fullscreen as a singleton', function () {
        $content = file_get_contents($this->pluginPath.'/src/FullscreenServiceProvider.php');

        expect($content)->toContain('singleton(Fullscreen::class');
    });

    it('registers CopyAssetsCommand in console context', function () {
        $content = file_get_contents($this->pluginPath.'/src/FullscreenServiceProvider.php');

        expect($content)->toContain('CopyAssetsCommand::class');
        expect($content)->toContain('runningInConsole');
    });

    it('extends ServiceProvider', function () {
        $content = file_get_contents($this->pluginPath.'/src/FullscreenServiceProvider.php');

        expect($content)->toContain('extends ServiceProvider');
    });
});

// ---------------------------------------------------------------------------
// CopyAssets Command
// ---------------------------------------------------------------------------

describe('CopyAssets Command', function () {
    it('has correct artisan signature', function () {
        $content = file_get_contents($this->pluginPath.'/src/Commands/CopyAssetsCommand.php');

        expect($content)->toContain("'nativephp:fullscreen:copy-assets'");
    });

    it('extends NativePluginHookCommand', function () {
        $content = file_get_contents($this->pluginPath.'/src/Commands/CopyAssetsCommand.php');

        expect($content)->toContain('extends NativePluginHookCommand');
    });

    it('checks for both platforms', function () {
        $content = file_get_contents($this->pluginPath.'/src/Commands/CopyAssetsCommand.php');

        expect($content)->toContain('$this->isAndroid()');
        expect($content)->toContain('$this->isIos()');
    });

    it('returns SUCCESS', function () {
        $content = file_get_contents($this->pluginPath.'/src/Commands/CopyAssetsCommand.php');

        expect($content)->toContain('return self::SUCCESS');
    });
});

// ---------------------------------------------------------------------------
// No Special Permissions
// ---------------------------------------------------------------------------

describe('No Special Permissions', function () {
    it('requires no Android permissions', function () {
        $manifest = json_decode(file_get_contents($this->manifestPath), true);

        expect($manifest['android']['permissions'])->toBeEmpty();
    });

    it('requires no iOS Info.plist entries', function () {
        $manifest = json_decode(file_get_contents($this->manifestPath), true);

        expect($manifest['ios']['info_plist'])->toBeEmpty();
    });

    it('has no Android dependencies', function () {
        $manifest = json_decode(file_get_contents($this->manifestPath), true);

        expect($manifest['android']['dependencies']['implementation'])->toBeEmpty();
    });

    it('has no iOS dependencies', function () {
        $manifest = json_decode(file_get_contents($this->manifestPath), true);

        expect($manifest['ios']['dependencies']['swift_packages'])->toBeEmpty();
    });
});

// ---------------------------------------------------------------------------
// Boost Guidelines
// ---------------------------------------------------------------------------

describe('Boost Guidelines', function () {
    it('documents all PHP facade methods', function () {
        $content = file_get_contents($this->pluginPath.'/resources/boost/guidelines/core.blade.php');

        expect($content)->toContain('Fullscreen::enter()');
        expect($content)->toContain('Fullscreen::exit()');
        expect($content)->toContain('Fullscreen::isActive()');
    });

    it('shows JavaScript usage', function () {
        $content = file_get_contents($this->pluginPath.'/resources/boost/guidelines/core.blade.php');

        expect($content)->toContain('Fullscreen.enter()');
        expect($content)->toContain('Fullscreen.exit()');
    });

    it('shows installation instructions', function () {
        $content = file_get_contents($this->pluginPath.'/resources/boost/guidelines/core.blade.php');

        expect($content)->toContain('composer require kevinbatdorf/nativephp-fullscreen');
    });

    it('documents platform behavior', function () {
        $content = file_get_contents($this->pluginPath.'/resources/boost/guidelines/core.blade.php');

        expect($content)->toContain('Android');
        expect($content)->toContain('iOS');
    });
});

// ---------------------------------------------------------------------------
// Thread Safety Patterns
// ---------------------------------------------------------------------------

describe('Thread Safety Patterns', function () {
    it('android runs Enter on main thread', function () {
        $content = file_get_contents($this->pluginPath.'/resources/android/src/FullscreenFunctions.kt');
        $section = ($this->extractClassSection)($content, 'Enter');

        expect($section)->toContain('runOnMainThreadBlocking');
    });

    it('android runs Exit on main thread', function () {
        $content = file_get_contents($this->pluginPath.'/resources/android/src/FullscreenFunctions.kt');
        $section = ($this->extractClassSection)($content, 'Exit');

        expect($section)->toContain('runOnMainThreadBlocking');
    });

    it('android has timeout for main thread blocking', function () {
        $content = file_get_contents($this->pluginPath.'/resources/android/src/FullscreenFunctions.kt');

        expect($content)->toContain('latch.await');
        expect($content)->toContain('TimeUnit.SECONDS');
    });

    it('ios uses sync dispatch for UI operations', function () {
        $content = file_get_contents($this->pluginPath.'/resources/ios/Sources/FullscreenFunctions.swift');
        $enterSection = ($this->extractSwiftClassSection)($content, 'Enter');
        $exitSection = ($this->extractSwiftClassSection)($content, 'Exit');

        expect($enterSection)->toContain('DispatchQueue.main.sync');
        expect($exitSection)->toContain('DispatchQueue.main.sync');
    });
});

// ---------------------------------------------------------------------------
// Error Handling
// ---------------------------------------------------------------------------

describe('Error Handling', function () {
    it('android catches exceptions in Enter', function () {
        $content = file_get_contents($this->pluginPath.'/resources/android/src/FullscreenFunctions.kt');
        $section = ($this->extractClassSection)($content, 'Enter');

        expect($section)->toContain('catch (e: Exception)');
        expect($section)->toContain('BridgeResponse.error');
    });

    it('android catches exceptions in Exit', function () {
        $content = file_get_contents($this->pluginPath.'/resources/android/src/FullscreenFunctions.kt');
        $section = ($this->extractClassSection)($content, 'Exit');

        expect($section)->toContain('catch (e: Exception)');
        expect($section)->toContain('BridgeResponse.error');
    });

    it('android catches exceptions in IsActive', function () {
        $content = file_get_contents($this->pluginPath.'/resources/android/src/FullscreenFunctions.kt');
        $section = ($this->extractClassSection)($content, 'IsActive');

        expect($section)->toContain('catch (e: Exception)');
        expect($section)->toContain('BridgeResponse.error');
    });
});
