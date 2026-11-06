import Foundation

func registerFullscreenOnLaunch() {
    // Written by the copy-assets hook; missing when the app was built without it.
    guard Bundle.main.object(forInfoDictionaryKey: "NativePHPFullscreenOnLaunch") as? Bool == true else {
        return
    }

    FullscreenState.startOnLaunch()
}
