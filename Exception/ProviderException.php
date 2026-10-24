<?php

declare(strict_types=1);

namespace Orangecat\MagoAiImage\Exception;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;

/**
 * Base class of provider failures. Messages are safe to show to an administrator and never contain credentials.
 *
 * @api
 */
class ProviderException extends LocalizedException
{
    /**
     * Takes any throwable as cause: HTTP client and Symfony AI exceptions are typed by interface.
     *
     * @param Phrase $phrase
     * @param \Throwable|null $cause Kept when it is an \Exception, as LocalizedException requires
     * @param int $code
     */
    public function __construct(Phrase $phrase, ?\Throwable $cause = null, $code = 0)
    {
        parent::__construct($phrase, $cause instanceof \Exception ? $cause : null, $code);
    }
}
