<?php

declare(strict_types=1);

namespace App\Core;

/** Raised when request validation fails. */
final class ValidationException extends \RuntimeException
{
    public function __construct(private readonly Validator $validator)
    {
        parent::__construct($validator->firstError() ?? 'The submitted data is invalid.', 422);
    }

    /** @return array<string,array<int,string>> */
    public function errors(): array
    {
        return $this->validator->errors();
    }

    public function validator(): Validator
    {
        return $this->validator;
    }
}
