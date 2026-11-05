<?php

return [
    // Android only. Read at build time, so rebuild after changing it.
    'on_launch' => (bool) env('NATIVEPHP_FULLSCREEN_ON_LAUNCH', false),
];
