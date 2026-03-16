import Foundation
import UIKit
import ObjectiveC
import WebKit

/// Tracks fullscreen state and applies it to the root UIViewController.
///
/// On iOS, status bar and home indicator visibility are controlled by the
/// root UIViewController's `prefersStatusBarHidden` and
/// `prefersHomeIndicatorAutoHidden` properties. Since NativePHP uses a
/// SwiftUI UIHostingController we can't subclass it, so we swizzle those
/// methods once and toggle behavior via a static flag.
///
/// Safe area insets are negated so content extends into the notch/dynamic island.
/// An orientation observer re-applies insets on rotation since the notch moves
/// to a different edge in landscape.
///
/// A WKUserScript at document start reads a sessionStorage flag to apply
/// fullscreen CSS before the page renders, preventing flash-of-insets on
/// navigation while fullscreen is active.
class FullscreenState: NSObject {
    static var isFullscreen = false

    /// Whether we've registered for orientation change notifications.
    private static var observingOrientation = false

    /// KVO observation for re-injecting CSS after page navigation.
    private static var loadingObservation: NSKeyValueObservation?

    /// Whether we've installed the early-injection user script.
    private static var userScriptInstalled = false

    /// Apply the current state to the root view controller and WebView.
    static func apply() {
        guard let windowScene = UIApplication.shared.connectedScenes
            .compactMap({ $0 as? UIWindowScene }).first,
            let window = windowScene.windows.first(where: { $0.isKeyWindow }),
            let rootVC = window.rootViewController
        else { return }

        swizzleOnce()
        startObservingOrientation()
        installUserScriptOnce()

        if isFullscreen {
            startObservingWebView()
        } else {
            stopObservingWebView()
        }

        rootVC.setNeedsStatusBarAppearanceUpdate()
        rootVC.setNeedsUpdateOfHomeIndicatorAutoHidden()

        // Negate safe area insets so content extends into the notch/dynamic island.
        // Must re-apply on every orientation change because insets shift edges.
        if isFullscreen {
            // Reset first so UIKit recalculates the real insets
            rootVC.additionalSafeAreaInsets = .zero
            rootVC.view.layoutIfNeeded()

            let insets = window.safeAreaInsets
            rootVC.additionalSafeAreaInsets = UIEdgeInsets(
                top: -insets.top,
                left: -insets.left,
                bottom: -insets.bottom,
                right: -insets.right
            )
        } else {
            rootVC.additionalSafeAreaInsets = .zero
            // Force layout so env(safe-area-inset-*) returns correct values
            // immediately on the next page navigation.
            rootVC.view.layoutIfNeeded()
        }

        // Also override safe area CSS in the WKWebView. UIKit's additionalSafeAreaInsets
        // doesn't affect WKWebView's env(safe-area-inset-*) or NativePHP's --inset-* vars.
        injectFullscreenCSS()
    }

    /// Injects CSS into the main WKWebView to zero out safe area insets when fullscreen,
    /// or restores real values when exiting fullscreen.
    /// Also sets a sessionStorage flag so the early-injection user script can apply
    /// the correct state on future page navigations before any rendering occurs.
    private static func injectFullscreenCSS() {
        guard let windowScene = UIApplication.shared.connectedScenes
            .compactMap({ $0 as? UIWindowScene }).first,
            let window = windowScene.windows.first(where: { $0.isKeyWindow }),
            let webView = findWebView(in: window)
        else { return }

        if isFullscreen {
            let js = """
            (function() {
                try { sessionStorage.setItem('__nativephp_fullscreen', '1'); } catch(e) {}
                document.documentElement.style.setProperty('--inset-top', '0px');
                document.documentElement.style.setProperty('--inset-right', '0px');
                document.documentElement.style.setProperty('--inset-bottom', '0px');
                document.documentElement.style.setProperty('--inset-left', '0px');
                var id = 'nativephp-fullscreen-style';
                if (!document.getElementById(id)) {
                    var s = document.createElement('style');
                    s.id = id;
                    s.textContent = ':root { --sat: 0px !important; --sar: 0px !important; --sab: 0px !important; --sal: 0px !important; } body.nativephp-safe-area { padding-top: 0 !important; }';
                    (document.head || document.documentElement).appendChild(s);
                }
            })();
            """
            webView.evaluateJavaScript(js, completionHandler: nil)
        } else {
            // Restore real safe area inset values — NativePHP's injectSafeAreaInsets
            // only runs on page navigation, so we must restore them manually on exit.
            let insets = window.safeAreaInsets
            let js = """
            (function() {
                try { sessionStorage.removeItem('__nativephp_fullscreen'); } catch(e) {}
                document.documentElement.style.setProperty('--inset-top', '\(insets.top)px');
                document.documentElement.style.setProperty('--inset-right', '\(insets.right)px');
                document.documentElement.style.setProperty('--inset-bottom', '\(insets.bottom)px');
                document.documentElement.style.setProperty('--inset-left', '\(insets.left)px');
                var el = document.getElementById('nativephp-fullscreen-style');
                if (el) el.remove();
            })();
            """
            webView.evaluateJavaScript(js, completionHandler: nil)
        }
    }

    /// Recursively finds the first WKWebView in a view hierarchy.
    private static func findWebView(in view: UIView) -> WKWebView? {
        if let webView = view as? WKWebView {
            return webView
        }
        for subview in view.subviews {
            if let found = findWebView(in: subview) {
                return found
            }
        }
        return nil
    }

    // MARK: - Early CSS Injection via WKUserScript

    /// Installs a permanent WKUserScript at document start that reads a
    /// sessionStorage flag and applies fullscreen CSS before the page renders.
    /// This prevents the flash-of-insets when navigating while in fullscreen.
    private static func installUserScriptOnce() {
        guard !userScriptInstalled else { return }

        guard let windowScene = UIApplication.shared.connectedScenes
            .compactMap({ $0 as? UIWindowScene }).first,
            let window = windowScene.windows.first(where: { $0.isKeyWindow }),
            let webView = findWebView(in: window)
        else { return }

        userScriptInstalled = true

        let source = """
        (function() {
            try {
                if (sessionStorage.getItem('__nativephp_fullscreen') === '1') {
                    var s = document.createElement('style');
                    s.id = 'nativephp-fullscreen-style';
                    s.textContent = ':root { --sat: 0px !important; --sar: 0px !important; --sab: 0px !important; --sal: 0px !important; --inset-top: 0px !important; --inset-right: 0px !important; --inset-bottom: 0px !important; --inset-left: 0px !important; } body.nativephp-safe-area { padding-top: 0 !important; }';
                    (document.head || document.documentElement).appendChild(s);
                }
            } catch(e) {}
        })();
        """

        let script = WKUserScript(
            source: source,
            injectionTime: .atDocumentStart,
            forMainFrameOnly: true
        )

        webView.configuration.userContentController.addUserScript(script)
    }

    // MARK: - Orientation Observer

    private static func startObservingOrientation() {
        guard !observingOrientation else { return }
        observingOrientation = true

        NotificationCenter.default.addObserver(
            self,
            selector: #selector(orientationDidChange),
            name: UIDevice.orientationDidChangeNotification,
            object: nil
        )
    }

    @objc private static func orientationDidChange() {
        guard isFullscreen else { return }
        // Delay slightly so UIKit has updated the safe area insets for the new orientation
        DispatchQueue.main.asyncAfter(deadline: .now() + 0.1) {
            guard isFullscreen else { return }
            apply()
        }
    }

    // MARK: - WebView Navigation Observer

    /// Observes the WKWebView's `isLoading` property so that fullscreen CSS
    /// is re-injected after every page navigation while fullscreen is active.
    /// This handles NativePHP's `injectSafeAreaInsets()` overwriting our values
    /// on `didCommit`/`didFinish`.
    private static func startObservingWebView() {
        guard loadingObservation == nil else { return }

        guard let windowScene = UIApplication.shared.connectedScenes
            .compactMap({ $0 as? UIWindowScene }).first,
            let window = windowScene.windows.first(where: { $0.isKeyWindow }),
            let webView = findWebView(in: window)
        else { return }

        loadingObservation = webView.observe(\.isLoading, options: [.new]) { _, change in
            guard let isLoading = change.newValue, !isLoading, isFullscreen else { return }
            DispatchQueue.main.async {
                injectFullscreenCSS()
            }
        }
    }

    /// Stops observing WebView loading when fullscreen is no longer active.
    private static func stopObservingWebView() {
        loadingObservation?.invalidate()
        loadingObservation = nil
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
