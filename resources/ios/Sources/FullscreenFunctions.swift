import Foundation
import UIKit
import Combine

/// Observable state for fullscreen mode.
/// NativePHPApp and ContentView observe this to apply .statusBarHidden() modifier.
public class FullscreenState: ObservableObject {
    public static let shared = FullscreenState()

    @Published public var isFullscreen = false

    private init() {}
}

enum FullscreenFunctions {

    /// Enter fullscreen mode (hide status bar and home indicator).
    class Enter: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            if Thread.isMainThread {
                FullscreenState.shared.isFullscreen = true
            } else {
                DispatchQueue.main.sync {
                    FullscreenState.shared.isFullscreen = true
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
                FullscreenState.shared.isFullscreen = false
            } else {
                DispatchQueue.main.sync {
                    FullscreenState.shared.isFullscreen = false
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
                "active": FullscreenState.shared.isFullscreen
            ])
        }
    }
}
