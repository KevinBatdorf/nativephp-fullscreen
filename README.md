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

## JavaScript

```js
import { Fullscreen } from '@kevinbatdorf/nativephp-fullscreen';

await Fullscreen.enter();
await Fullscreen.exit();
const active = await Fullscreen.isActive();
```

## Platform Behavior

- **Android**: Uses `WindowInsetsControllerCompat` with `BEHAVIOR_SHOW_TRANSIENT_BARS_BY_SWIPE`. System bars reappear briefly on edge swipe, then auto-hide.
- **iOS**: Uses a shared `FullscreenState` ObservableObject to drive `.statusBarHidden()` in SwiftUI.

## License

MIT
