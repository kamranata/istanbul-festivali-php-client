<?php
namespace KamranAta\IstanbulFestivali\Exceptions;

/**
 * Authentication Exception
 * 
 * Thrown when authentication fails or tokens are invalid.
 * 
 * @package KamranAta\IstanbulFestivali\Exceptions
 */
class AuthException extends \RuntimeException
{
    /**
     * Initialize authentication exception
     * 
     * @param string $message Exception message
     * @param int $code Exception code
     * @param \Throwable|null $previous Previous exception
     */
    public function __construct(
        string $message = 'Authentication failed', 
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
        return 'AuthException: ' . $this->getMessage() . ' in ' . $this->getFile() . ':' . $this->getLine();
    }
}
