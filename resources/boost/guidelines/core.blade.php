## kevinbatdorf/nativephp-fullscreen

Enter and exit fullscreen (immersive) mode. Works on both Android (WindowInsetsController) and iOS (prefersStatusBarHidden).

### Installation

```bash
composer require kevinbatdorf/nativephp-fullscreen
```

### PHP Usage (Livewire/Blade)

Use the `Fullscreen` facade:

@verbatim
<code-snippet name="Basic Fullscreen Usage" lang="php">
use KevinBatdorf\Fullscreen\Facades\Fullscreen;

// Enter fullscreen (hide status bar + navigation bar)
Fullscreen::enter();

// Exit fullscreen (show system bars)
Fullscreen::exit();

// Check if fullscreen mode is active
$active = Fullscreen::isActive();
</code-snippet>
@endverbatim

### Available Methods

- `Fullscreen::enter()`: Enter fullscreen (immersive) mode
- `Fullscreen::exit()`: Exit fullscreen mode
- `Fullscreen::isActive()`: Check whether fullscreen mode is currently active

### JavaScript Usage (Vue/React/Inertia)

@verbatim
<code-snippet name="Using Fullscreen in JavaScript" lang="javascript">
import { Fullscreen } from '@kevinbatdorf/nativephp-fullscreen';

// Enter fullscreen
await Fullscreen.enter();

// Exit fullscreen
await Fullscreen.exit();

// Check if active
const active = await Fullscreen.isActive();
</code-snippet>
@endverbatim

### Platform Behavior

- **Android**: Uses `WindowInsetsControllerCompat` with `BEHAVIOR_SHOW_TRANSIENT_BARS_BY_SWIPE`. System bars reappear briefly on edge swipe, then auto-hide.
- **iOS**: Swizzles the root UIViewController to hide the status bar and home indicator. Negates safe area insets so content extends into the notch/dynamic island. Injects CSS to zero out NativePHP's safe area variables and removes `body.nativephp-safe-area` padding. Fullscreen persists across page navigations via `sessionStorage` and a `WKUserScript` at document start.
