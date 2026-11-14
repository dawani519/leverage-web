<?php

namespace App\Exceptions;

use RuntimeException;

/** A permanent payment problem (unknown reference, mismatch, not configured). Retrying will not help. */
class PaymentException extends RuntimeException {}
