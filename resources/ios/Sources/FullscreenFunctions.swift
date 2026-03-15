import Foundation
import UIKit
import ObjectiveC

/// Tracks fullscreen state and applies it to the root UIViewController.
///
/// On iOS, status bar and home indicator visibility are controlled by the
/// root UIViewController's `prefersStatusBarHidden` and
/// `prefersHomeIndicatorAutoHidden` properties. Since NativePHP uses a
/// SwiftUI UIHostingController we can't subclass it, so we swizzle those
/// methods once and toggle behavior via a static flag.
class FullscreenState: NSObject {
    static var isFullscreen = false

    /// Apply the current state to the root view controller.
    static func apply() {
        guard let windowScene = UIApplication.shared.connectedScenes
            .compactMap({ $0 as? UIWindowScene }).first,
            let window = windowScene.windows.first(where: { $0.isKeyWindow }),
            let rootVC = window.rootViewController
        else { return }

        swizzleOnce()
        rootVC.setNeedsStatusBarAppearanceUpdate()
        rootVC.setNeedsUpdateOfHomeIndicatorAutoHidden()

        // Negate safe area insets so content extends into the notch/dynamic island
        if isFullscreen {
            let insets = window.safeAreaInsets
            rootVC.additionalSafeAreaInsets = UIEdgeInsets(
                top: -insets.top,
                left: -insets.left,
                bottom: -insets.bottom,
                right: -insets.right
            )
        } else {
            rootVC.additionalSafeAreaInsets = .zero
        }
    }

    // MARK: - Method swizzling

    private static var swizzled = false

    private static func swizzleOnce() {
        guard !swizzled else { return }
        swizzled = true

        guard let windowScene = UIApplication.shared.connectedScenes
            .compactMap({ $0 as? UIWindowScene }).first,
            let rootVC = windowScene.windows.first(where: { $0.isKeyWindow })?.rootViewController
        else { return }

        let vcClass: AnyClass = type(of: rootVC)

        // Swizzle prefersStatusBarHidden
        if let original = class_getInstanceMethod(vcClass, #selector(getter: UIViewController.prefersStatusBarHidden)),
           let replacement = class_getInstanceMethod(FullscreenState.self, #selector(FullscreenState._prefersStatusBarHidden)) {
            method_setImplementation(original, method_getImplementation(replacement))
        }

        // Swizzle prefersHomeIndicatorAutoHidden
        if let original = class_getInstanceMethod(vcClass, #selector(getter: UIViewController.prefersHomeIndicatorAutoHidden)),
           let replacement = class_getInstanceMethod(FullscreenState.self, #selector(FullscreenState._prefersHomeIndicatorAutoHidden)) {
            method_setImplementation(original, method_getImplementation(replacement))
        }
    }

    @objc private func _prefersStatusBarHidden() -> Bool {
        return FullscreenState.isFullscreen
    }

    @objc private func _prefersHomeIndicatorAutoHidden() -> Bool {
        return FullscreenState.isFullscreen
    }
}

enum FullscreenFunctions {

    /// Enter fullscreen mode (hide status bar and home indicator).
    class Enter: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            if Thread.isMainThread {
                FullscreenState.isFullscreen = true
                FullscreenState.apply()
            } else {
                DispatchQueue.main.sync {
                    FullscreenState.isFullscreen = true
                    FullscreenState.apply()
                }
            }

            return BridgeResponse.success(data: [
                "status": "active"
            ])
        }
    }

    /// Exit fullscreen mode (show status bar and home indicator).
    class Exit: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            if Thread.isMainThread {
                FullscreenState.isFullscreen = false
                FullscreenState.apply()
            } else {
                DispatchQueue.main.sync {
                    FullscreenState.isFullscreen = false
                    FullscreenState.apply()
                }
            }

            return BridgeResponse.success(data: [
                "status": "inactive"
            ])
        }
    }

    /// Check whether fullscreen mode is currently active.
    class IsActive: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            return BridgeResponse.success(data: [
                "active": FullscreenState.isFullscreen
            ])
        }
    }
}
