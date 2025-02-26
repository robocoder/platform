<?php

namespace Oro\Bundle\FeatureToggleBundle\Twig;

use Oro\Bundle\FeatureToggleBundle\Checker\FeatureChecker;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Provides Twig functions to check feature status:
 *   - feature_enabled
 *   - feature_resource_enabled
 */
class FeatureExtension extends AbstractExtension
{
    /** @var ContainerInterface */
    protected $container;

    /**
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @return FeatureChecker
     */
    protected function getFeatureChecker()
    {
        return $this->container->get('oro_featuretoggle.checker.feature_checker');
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions()
    {
        return [
            new TwigFunction('feature_enabled', [$this, 'isFeatureEnabled']),
            new TwigFunction('feature_resource_enabled', [$this, 'isResourceEnabled']),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'oro_featuretoggle_extension';
    }

    /**
     * @param string $feature
     * @param int|object|null $scopeIdentifier
     * @return bool
     */
    public function isFeatureEnabled($feature, $scopeIdentifier = null)
    {
        return $this->getFeatureChecker()->isFeatureEnabled($feature, $scopeIdentifier);
    }

    /**
     * @param string $resource
     * @param string $resourceType
     * @param int|object|null $scopeIdentifier
     * @return bool
     */
    public function isResourceEnabled($resource, $resourceType, $scopeIdentifier = null)
    {
        return $this->getFeatureChecker()->isResourceEnabled($resource, $resourceType, $scopeIdentifier);
    }
}
