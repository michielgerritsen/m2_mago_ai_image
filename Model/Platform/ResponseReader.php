<?php

declare(strict_types=1);

namespace Orangecat\MagoAiImage\Model\Platform;

use Orangecat\MagoAiImage\Exception\ProviderException;
use Orangecat\MagoAiImage\Exception\ProviderTimeoutException;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Status and decoded body of a Symfony AI result, read before the bridge converts it.
 *
 * The bridges map only some HTTP errors, and drop the provider's error code (OpenAI
 * `moderation_blocked`, Gemini `promptFeedback`), so the providers inspect the response first and
 * leave the bridge only the successful ones.
 */
class ResponseReader
{
    /**
     * Status and body of the response behind the result.
     *
     * @param DeferredResult $result
     * @param string $providerLabel Provider name for messages
     * @param int $timeout Seconds, for messages
     * @return array{status: int, body: mixed[]}
     * @throws ProviderException
     */
    public function read(DeferredResult $result, string $providerLabel, int $timeout): array
    {
        $response = $result->getRawResult()->getObject();
        if (!$response instanceof ResponseInterface) {
            throw new ProviderException(__('%1 returned an unexpected result.', $providerLabel));
        }

        try {
            $status = $response->getStatusCode();
            try {
                $body = $response->toArray(false);
            } catch (DecodingExceptionInterface) {
                $body = [];
            }
        } catch (TransportExceptionInterface $e) {
            $message = $e->getMessage();
            if (stripos($message, 'timeout') !== false || stripos($message, 'max duration') !== false) {
                throw new ProviderTimeoutException(
                    __('%1 did not answer within %2 seconds.', $providerLabel, $timeout),
                    $e
                );
            }
            throw new ProviderException(__('Could not reach %1: %2', $providerLabel, $message), $e);
        } catch (HttpExceptionInterface $e) {
            throw new ProviderException(__('Could not reach %1: %2', $providerLabel, $e->getMessage()), $e);
        }

        return ['status' => $status, 'body' => $body];
    }
}
