<?php

declare(strict_types=1);

namespace Orangecat\MagoAiImage\Exception;

/**
 * The provider refused the prompt or the reference image (safety policy).
 *
 * @api
 */
class ContentRejectedException extends ProviderException
{
}
