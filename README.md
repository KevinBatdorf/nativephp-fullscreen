# Fullscreen Plugin for NativePHP Mobile

Enter and exit fullscreen (immersive) mode.

## Installation

```bash
composer require kevinbatdorf/nativephp-fullscreen
```

## Usage

```php
use KevinBatdorf\Fullscreen\Facades\Fullscreen;

// Enter fullscreen (hide status bar + navigation bar)
Fullscreen::enter();

// Exit fullscreen (show system bars)
Fullscreen::exit();

// Check if fullscreen mode is active
$active = Fullscreen::isActive();
```

## Fullscreen on launch

`Fullscreen::enter()` can only run once PHP has booted, which on a cold start can take several seconds. To hide the system bars from the app's first frame instead, set this in `.env`:

```env
NATIVEPHP_FULLSCREEN_ON_LAUNCH=true
```

It's read when the app is built, so rebuild after changing it. Off by default. `Fullscreen::exit()` still shows the bars, and they stay shown until `Fullscreen::enter()` is called again.

- **Android**: the bars are hidden before the first frame and again each time the app comes back to the foreground. Android 12 and later still show the status bar on the system splash screen, before the app's first frame; nothing in the app can hide it there.
- **iOS**: the status bar is hidden from the launch screen on (`UIStatusBarHidden` in Info.plist), and the status bar and home indicator stay hidden once the app's window appears. Web pages get the same safe-area CSS as `Fullscreen::enter()` once NativePHP's WebView loads.

## JavaScript

```js
import { Fullscreen } from '@kevinbatdorf/nativephp-fullscreen';

await Fullscreen.enter();
await Fullscreen.exit();
const active = await Fullscreen.isActive();
```

## Platform Behavior

- **Android**: Uses `WindowInsetsControllerCompat` with `BEHAVIOR_SHOW_TRANSIENT_BARS_BY_SWIPE`. System bars reappear briefly on edge swipe, then auto-hide. Fullscreen persists across page navigations natively.
- **iOS**: Swizzles the root UIViewController to hide the status bar and home indicator. Negates safe area insets so content extends into the notch/dynamic island. Injects CSS to zero out NativePHP's safe area variables (`--inset-*`, `--sat/sar/sab/sal`) and removes `body.nativephp-safe-area` padding. A `WKUserScript` at document start reads a `sessionStorage` flag to apply fullscreen CSS before the page renders, preventing flash-of-insets on navigation. A KVO observer on `WKWebView.isLoading` re-injects CSS after each page load. Orientation changes are handled automatically.

## License

MIT
