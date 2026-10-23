<?php

// @formatter:off
// phpcs:ignoreFile

/**
 * Foresight Radar IDE Helper for Intelephense / Static Analyzers.
 * Provides explicit signatures for Laravel Request, FormRequest, and Auth macros.
 */

namespace Illuminate\Http {
    /**
     * @method \App\Models\User|null user($guard = null)
     * @method bool boolean($key = null, $default = false)
     */
    class Request {}
}

namespace Illuminate\Foundation\Http {
    /**
     * @method \App\Models\User|null user($guard = null)
     * @method bool boolean($key = null, $default = false)
     * @method mixed validated($key = null, $default = null)
     */
    class FormRequest {}
}

namespace Illuminate\Support\Facades {
    /**
     * @method static \App\Models\User|null user()
     * @method static int|string|null id()
     * @method static bool check()
     * @method static bool guest()
     */
    class Auth {}
}

