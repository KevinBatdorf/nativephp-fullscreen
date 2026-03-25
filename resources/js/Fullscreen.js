/**
 * Fullscreen Plugin for NativePHP Mobile
 *
 * Enter and exit fullscreen (immersive) mode.
 *
 * @example
 * import { Fullscreen } from '@kevinbatdorf/nativephp-fullscreen';
 *
 * // Enter fullscreen
 * await Fullscreen.enter();
 *
 * // Exit fullscreen
 * await Fullscreen.exit();
 *
 * // Check if fullscreen is active
 * const active = await Fullscreen.isActive();
 */

const baseUrl = '/_native/api/call';

async function bridgeCall(method, params = {}) {
    const response = await fetch(baseUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
        },
        body: JSON.stringify({ method, params })
    });

    if (!response.ok) {
        throw new Error(`Native call failed with status ${response.status}`);
    }

    const result = await response.json();

    if (result.status === 'error') {
        throw new Error(result.message || 'Native call failed');
    }

    return result.data;
}

async function enter() {
    const result = await bridgeCall('Fullscreen.Enter');
    return result?.status === 'active';
}

async function exit() {
    const result = await bridgeCall('Fullscreen.Exit');
    return result?.status === 'inactive';
}

async function isActive() {
    const result = await bridgeCall('Fullscreen.IsActive');
    return result?.active ?? false;
}

export const Fullscreen = {
    enter,
    exit,
    isActive
};

export default Fullscreen;
