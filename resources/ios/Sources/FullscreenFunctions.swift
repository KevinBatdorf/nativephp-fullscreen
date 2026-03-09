import Foundation
import UIKit

/// Bridge functions for the NativePHP Fullscreen plugin.
///
/// Provides fullscreen mode by hiding and showing the iOS status bar.
/// Uses the `prefersStatusBarHidden` override on the root view controller
/// and `prefersHomeIndicatorAutoHidden` to minimize the home indicator.
enum FullscreenFunctions {

    /// Tracks whether fullscreen mode is active.
    private static var isFullscreen = false

    /// Swizzle flag to prevent double-swizzling.
    private static var swizzled = false

    /// Ensures the root view controller's `prefersStatusBarHidden` and
    /// `prefersHomeIndicatorAutoHidden` are overridden via method swizzling.
    ///
    /// iOS does not allow hiding the status bar globally after iOS 13 —
    /// it must be done per-view-controller via `prefersStatusBarHidden`.
    /// We swizzle the root VC's methods once, then toggle via our static flag.
    private static func ensureSwizzled() {
        guard !swizzled else { return }
        swizzled = true

        guard let rootVC = UIApplication.shared.connectedScenes
            .compactMap({ $0 as? UIWindowScene })
            .first?
            .windows
            .first?
            .rootViewController else {
            NSLog("[Fullscreen] No root view controller found for swizzling")
            return
        }

        let vcClass: AnyClass = type(of: rootVC)

        // Swizzle prefersStatusBarHidden
        if let original = class_getInstanceMethod(vcClass, #selector(getter: UIViewController.prefersStatusBarHidden)),
           let replacement = class_getInstanceMethod(FullscreenFunctions.self, #selector(FullscreenFunctions.swizzled_prefersStatusBarHidden)) {
            method_exchangeImplementations(original, replacement)
        }

        // Swizzle prefersHomeIndicatorAutoHidden
        if let original = class_getInstanceMethod(vcClass, #selector(getter: UIViewController.prefersHomeIndicatorAutoHidden)),
           let replacement = class_getInstanceMethod(FullscreenFunctions.self, #selector(FullscreenFunctions.swizzled_prefersHomeIndicatorAutoHidden)) {
            method_exchangeImplementations(original, replacement)
        }
    }

    @objc private dynamic func swizzled_prefersStatusBarHidden() -> Bool {
        return FullscreenFunctions.isFullscreen
    }

    @objc private dynamic func swizzled_prefersHomeIndicatorAutoHidden() -> Bool {
        return FullscreenFunctions.isFullscreen
    }

    /// Notifies the root view controller to re-query its status bar preferences.
    private static func setNeedsStatusBarUpdate() {
        guard let windowScene = UIApplication.shared.connectedScenes
            .compactMap({ $0 as? UIWindowScene })
            .first,
              let rootVC = windowScene.windows.first?.rootViewController else {
            return
        }

        rootVC.setNeedsStatusBarAppearanceUpdate()
        rootVC.setNeedsUpdateOfHomeIndicatorAutoHidden()
    }

    // MARK: - Bridge Functions

    /// Enter fullscreen mode (hide status bar and home indicator).
    ///
    /// - Returns: Dictionary containing:
    ///   - status: "active" on success
    class Enter: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            DispatchQueue.main.sync {
                FullscreenFunctions.ensureSwizzled()
                FullscreenFunctions.isFullscreen = true
                FullscreenFunctions.setNeedsStatusBarUpdate()
            }

            NSLog("[Fullscreen] Fullscreen mode entered")

            return BridgeResponse.success(data: [
                "status": "active"
            ])
        }
    }

    /// Exit fullscreen mode (show status bar and home indicator).
    ///
    /// - Returns: Dictionary containing:
    ///   - status: "inactive" on success
    class Exit: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            DispatchQueue.main.sync {
                FullscreenFunctions.ensureSwizzled()
                FullscreenFunctions.isFullscreen = false
                FullscreenFunctions.setNeedsStatusBarUpdate()
            }

            NSLog("[Fullscreen] Fullscreen mode exited")

            return BridgeResponse.success(data: [
                "status": "inactive"
            ])
        }
    }

    /// Check whether fullscreen mode is currently active.
    ///
    /// - Returns: Dictionary containing:
    ///   - active: Bool - true if fullscreen mode is active
    class IsActive: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            let active = FullscreenFunctions.isFullscreen

            return BridgeResponse.success(data: [
                "active": active
            ])
        }
    }
}
