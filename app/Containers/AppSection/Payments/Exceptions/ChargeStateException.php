<?php

namespace App\Containers\AppSection\Payments\Exceptions;

use App\Ship\Parents\Exceptions\Exception as ParentException;

final class ChargeStateException extends ParentException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $statusCode = 409,
    ) {
        parent::__construct($message);
    }
}
