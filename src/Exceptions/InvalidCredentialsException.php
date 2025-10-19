<?php
namespace KamranAta\IstanbulFestivali\Exceptions;

/**
 * Invalid Credentials Exception
 * 
 * Thrown when provided credentials are invalid or malformed.
 * 
 * @package KamranAta\IstanbulFestivali\Exceptions
 */
class InvalidCredentialsException extends \RuntimeException
{
    /**
     * Initialize invalid credentials exception
     * 
     * @param string $message Exception message
     * @param int $code Exception code
     * @param \Throwable|null $previous Previous exception
     */
    public function __construct(
        string $message = 'Invalid credentials provided', 
        int $code = 0, 
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * String representation of the exception
     * 
     * @return string
     */
    public function __toString(): string
    {
        return 'InvalidCredentialsException: ' . $this->getMessage() . ' in ' . $this->getFile() . ':' . $this->getLine();
    }
}
