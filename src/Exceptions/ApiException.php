<?php
namespace KamranAta\IstanbulFestivali\Exceptions;

/**
 * API Exception
 * 
 * Thrown when API requests fail with HTTP error status codes.
 * Contains the HTTP status code and response body for debugging.
 * 
 * @package KamranAta\IstanbulFestivali\Exceptions
 */
class ApiException extends \RuntimeException
{
    /**
     * Initialize API exception
     * 
     * @param string $message Exception message
     * @param int $code HTTP status code
     * @param \Throwable|null $previous Previous exception
     * @param array|null $responseBody API response body for debugging
     */
    public function __construct(
        string $message, 
        int $code = 0, 
        ?\Throwable $previous = null, 
        public ?array $responseBody = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the HTTP status code
     * 
     * @return int The HTTP status code
     */
    public function getHttpStatusCode(): int
    {
        return $this->getCode();
    }

    /**
     * Get the API response body
     * 
     * @return array|null The response body or null if not available
     */
    public function getResponseBody(): ?array
    {
        return $this->responseBody;
    }

    /**
     * String representation of the exception
     * 
     * @return string
     */
    public function __toString(): string
    {
        $str = parent::__toString();
        if ($this->responseBody) {
            $str .= "\nResponse Body: " . json_encode($this->responseBody, JSON_PRETTY_PRINT);
        }
        return $str;
    }
}
