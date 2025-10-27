<?php
declare(strict_types=1);

namespace VeriBits\Exceptions;

class ForbiddenException extends \Exception {
    public function __construct(string $message = 'Forbidden') {
        parent::__construct($message);
    }
}
