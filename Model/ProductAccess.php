<?php

declare(strict_types=1);

namespace Orangecat\MagoAiImage\Model;

use Magento\Framework\AuthorizationInterface;

/**
 * Product permission the Mago tools need besides their own ACL resource.
 *
 * A tool declares a single ACL resource, and a role can hold this module's resources without
 * Magento_Catalog::products (e.g. one created through the API). Without this check such a role
 * could change the gallery and image roles of products it cannot edit.
 */
class ProductAccess
{
    public const ACL_PRODUCTS = 'Magento_Catalog::products';

    /**
     * @param AuthorizationInterface $authorization
     */
    public function __construct(
        private readonly AuthorizationInterface $authorization
    ) {
    }

    /**
     * Why the current admin may not use the product images, or null when allowed.
     *
     * @return string|null
     */
    public function getDeniedReason(): ?string
    {
        return $this->authorization->isAllowed(self::ACL_PRODUCTS)
            ? null
            : (string) __('You are not allowed to manage products.');
    }
}
