<?php

namespace App\Services\IndicatorSources;

use RuntimeException;

/** An external source could not deliver usable data; the message is shown to the coordinator. */
class IndicatorSourceException extends RuntimeException {}
