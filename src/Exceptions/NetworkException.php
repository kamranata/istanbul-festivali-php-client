<?php
namespace KamranAta\IstanbulFestivali\Exceptions;

/**
 * Network Exception
 * 
 * Thrown when network-related errors occur during API requests.
 * 
 * @package KamranAta\IstanbulFestivali\Exceptions
 */
class NetworkException extends \RuntimeException
{
    /**
     * Initialize network exception
     * 
     * @param string $message Exception message
     * @param int $code Exception code
     * @param \Throwable|null $previous Previous exception
     */
    public function __construct(
        string $message = 'Network error occurred', 
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
        return 'NetworkException: ' . $this->getMessage() . ' in ' . $this->getFile() . ':' . $this->getLine();
    }
}
