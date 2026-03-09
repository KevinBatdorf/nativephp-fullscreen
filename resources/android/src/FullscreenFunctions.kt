package com.kevinbatdorf.plugins.fullscreen

import android.os.Handler
import android.os.Looper
import android.util.Log
import androidx.core.view.WindowCompat
import androidx.core.view.WindowInsetsCompat
import androidx.core.view.WindowInsetsControllerCompat
import androidx.fragment.app.FragmentActivity
import com.nativephp.mobile.bridge.BridgeFunction
import com.nativephp.mobile.bridge.BridgeResponse
import java.util.concurrent.CountDownLatch
import java.util.concurrent.TimeUnit

/**
 * Bridge functions for the NativePHP Fullscreen plugin.
 *
 * Provides immersive/fullscreen mode by hiding and showing
 * the Android status bar and navigation bar.
 */
object FullscreenFunctions {

    private const val TAG = "FullscreenFunctions"

    /**
     * Run a block on the main thread and block until it completes.
     */
    private fun <T> runOnMainThreadBlocking(block: () -> T): T {
        if (Looper.myLooper() == Looper.getMainLooper()) {
            return block()
        }

        val latch = CountDownLatch(1)
        var result: T? = null
        var error: Exception? = null

        Handler(Looper.getMainLooper()).post {
            try {
                result = block()
            } catch (e: Exception) {
                error = e
            } finally {
                latch.countDown()
            }
        }

        latch.await(5, TimeUnit.SECONDS)
        error?.let { throw it }

        @Suppress("UNCHECKED_CAST")
        return result as T
    }

    /**
     * Enter fullscreen (immersive) mode.
     *
     * Hides both the status bar and navigation bar using sticky immersive mode.
     * System bars will reappear temporarily on swipe from edge,
     * then auto-hide again after a short delay.
     *
     * Parameters: none
     *
     * Returns:
     * - status: String - "active" on success
     */
    class Enter(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            return try {
                runOnMainThreadBlocking {
                    val controller = WindowInsetsControllerCompat(
                        activity.window, activity.window.decorView
                    )
                    controller.hide(WindowInsetsCompat.Type.systemBars())
                    controller.systemBarsBehavior =
                        WindowInsetsControllerCompat.BEHAVIOR_SHOW_TRANSIENT_BARS_BY_SWIPE
                }

                Log.d(TAG, "Fullscreen mode entered")

                BridgeResponse.success(mapOf("status" to "active"))
            } catch (e: Exception) {
                Log.e(TAG, "Failed to enter fullscreen: ${e.message}", e)
                BridgeResponse.error("ENTER_FAILED", e.message ?: "Failed to enter fullscreen")
            }
        }
    }

    /**
     * Exit fullscreen (immersive) mode.
     *
     * Parameters: none
     *
     * Returns:
     * - status: String - "inactive" on success
     */
    class Exit(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            return try {
                runOnMainThreadBlocking {
                    val controller = WindowInsetsControllerCompat(
                        activity.window, activity.window.decorView
                    )
                    controller.show(WindowInsetsCompat.Type.systemBars())
                }

                Log.d(TAG, "Fullscreen mode exited")

                BridgeResponse.success(mapOf("status" to "inactive"))
            } catch (e: Exception) {
                Log.e(TAG, "Failed to exit fullscreen: ${e.message}", e)
                BridgeResponse.error("EXIT_FAILED", e.message ?: "Failed to exit fullscreen")
            }
        }
    }

    /**
     * Check whether fullscreen mode is currently active.
     *
     * Parameters: none
     *
     * Returns:
     * - active: Boolean - true if fullscreen mode is active (system bars hidden)
     */
    class IsActive(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            return try {
                val active = runOnMainThreadBlocking {
                    val insets = androidx.core.view.ViewCompat.getRootWindowInsets(
                        activity.window.decorView
                    )
                    !(insets?.isVisible(WindowInsetsCompat.Type.statusBars()) ?: true)
                }

                BridgeResponse.success(mapOf("active" to active))
            } catch (e: Exception) {
                Log.e(TAG, "Failed to check fullscreen state: ${e.message}", e)
                BridgeResponse.error(
                    "CHECK_FAILED",
                    e.message ?: "Failed to check fullscreen state"
                )
            }
        }
    }
}
