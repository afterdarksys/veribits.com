<?php
declare(strict_types=1);

namespace VeriBits\Exceptions;

class QuotaExceededException extends \Exception {
    public function __construct(string $message = 'Quota exceeded') {
        parent::__construct($message);
    }
}
