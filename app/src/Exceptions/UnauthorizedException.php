<?php
declare(strict_types=1);

namespace VeriBits\Exceptions;

class UnauthorizedException extends \Exception {
    public function __construct(string $message = 'Unauthorized') {
        parent::__construct($message);
    }
}
