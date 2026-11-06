package com.kevinbatdorf.plugins.fullscreen

import android.app.Activity
import android.app.Application
import android.content.ContentProvider
import android.content.ContentValues
import android.database.Cursor
import android.net.Uri
import android.os.Bundle
import android.util.Log
import androidx.core.view.WindowInsetsCompat
import androidx.core.view.WindowInsetsControllerCompat

object FullscreenOnLaunch {

    // Exit() clears this so resuming after it leaves the bars shown.
    @Volatile
    var wanted = false

    fun hideSystemBars(activity: Activity) {
        val controller = WindowInsetsControllerCompat(activity.window, activity.window.decorView)
        controller.hide(WindowInsetsCompat.Type.systemBars())
        controller.systemBarsBehavior =
            WindowInsetsControllerCompat.BEHAVIOR_SHOW_TRANSIENT_BARS_BY_SWIPE
    }
}

// A provider, not init_function: NativePHP runs init functions after the first frame.
class FullscreenLaunchProvider : ContentProvider() {

    override fun onCreate(): Boolean {
        val app = context?.applicationContext as? Application ?: return true

        // Missing when the app was built without the copy-assets hook.
        val id = app.resources.getIdentifier("nativephp_fullscreen_on_launch", "bool", app.packageName)
        if (id == 0 || !app.resources.getBoolean(id)) return true

        FullscreenOnLaunch.wanted = true

        app.registerActivityLifecycleCallbacks(object : Application.ActivityLifecycleCallbacks {
            // Not onActivityCreated: its decor access breaks a later requestWindowFeature().
            override fun onActivityResumed(activity: Activity) {
                if (!FullscreenOnLaunch.wanted) return
                try {
                    FullscreenOnLaunch.hideSystemBars(activity)
                } catch (e: Exception) {
                    Log.e("FullscreenOnLaunch", "Failed to hide system bars: ${e.message}", e)
                }
            }

            override fun onActivityCreated(activity: Activity, savedInstanceState: Bundle?) {}
            override fun onActivityStarted(activity: Activity) {}
            override fun onActivityPaused(activity: Activity) {}
            override fun onActivityStopped(activity: Activity) {}
            override fun onActivitySaveInstanceState(activity: Activity, outState: Bundle) {}
            override fun onActivityDestroyed(activity: Activity) {}
        })

        return true
    }

    override fun query(
        uri: Uri,
        projection: Array<out String>?,
        selection: String?,
        selectionArgs: Array<out String>?,
        sortOrder: String?
    ): Cursor? = null

    override fun getType(uri: Uri): String? = null

    override fun insert(uri: Uri, values: ContentValues?): Uri? = null

    override fun delete(uri: Uri, selection: String?, selectionArgs: Array<out String>?): Int = 0

    override fun update(
        uri: Uri,
        values: ContentValues?,
        selection: String?,
        selectionArgs: Array<out String>?
    ): Int = 0
}
