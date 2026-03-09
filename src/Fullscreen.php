<?php

namespace KevinBatdorf\Fullscreen;

class Fullscreen
{
    /**
     * Enter fullscreen (immersive) mode.
     *
     * Hides both the status bar and navigation bar using
     * sticky immersive mode. System bars reappear temporarily
     * on swipe, then auto-hide again.
     */
    public function enter(): bool
    {
        if (function_exists('nativephp_call')) {
            $result = nativephp_call('Fullscreen.Enter', '{}');

            if ($result) {
                $decoded = json_decode($result);

                return ($decoded->status ?? '') === 'active';
            }
        }

        return false;
    }

    /**
     * Exit fullscreen (immersive) mode.
     */
    public function exit(): bool
    {
        if (function_exists('nativephp_call')) {
            $result = nativephp_call('Fullscreen.Exit', '{}');

            if ($result) {
                $decoded = json_decode($result);

                return ($decoded->status ?? '') === 'inactive';
            }
        }

        return false;
    }

    /**
     * Check whether fullscreen mode is currently active.
     */
    public function isActive(): bool
    {
        if (function_exists('nativephp_call')) {
            $result = nativephp_call('Fullscreen.IsActive', '{}');

            if ($result) {
                $decoded = json_decode($result);

                return $decoded->active ?? false;
            }
        }

        return false;
    }
}
