<?php
declare(strict_types=1);

namespace VeriBits\Exceptions;

class RateLimitException extends \Exception {
    public function __construct(string $message = 'Rate limit exceeded') {
        parent::__construct($message);
    }
}
