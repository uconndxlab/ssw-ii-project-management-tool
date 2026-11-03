<?php

namespace App\Exceptions;

use RuntimeException;

/** A lifecycle action no longer matches the enrollment's current state; nothing was saved. */
class CertificateLifecycleConflict extends RuntimeException {}
