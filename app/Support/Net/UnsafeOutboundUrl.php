<?php

namespace App\Support\Net;

use RuntimeException;

/** An outbound URL that the server must not call (SSRF guard). The message is safe to show to the user. */
class UnsafeOutboundUrl extends RuntimeException {}
