<?php

namespace App\Exceptions;

use DomainException;

/**
 * A business rule said no (e.g. "this freelancer is already on another shift at that time").
 * The message is already translated and safe to show to the user.
 */
final class DomainRuleViolation extends DomainException {}
