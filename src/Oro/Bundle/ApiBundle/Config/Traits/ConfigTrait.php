<?php

namespace Oro\Bundle\ApiBundle\Config\Traits;

/**
 * @property array $items
 */
trait ConfigTrait
{
    /**
     * Checks whether the configuration attribute exists.
     *
     * @param string $key
     *
     * @return bool
     */
    public function has($key)
    {
        return array_key_exists($key, $this->items);
    }

    /**
     * Gets the configuration value.
     *
     * @param string $key
     *
     * @return mixed
     */
    public function get($key)
    {
        return array_key_exists($key, $this->items)
            ? $this->items[$key]
            : null;
    }

    /**
     * Sets the configuration value.
     *
     * @param string $key
     * @param mixed  $value
     */
    public function set($key, $value)
    {
        if (null !== $value) {
            $this->items[$key] = $value;
        } else {
            unset($this->items[$key]);
        }
    }

    /**
     * Removes the configuration value.
     *
     * @param string $key
     */
    public function remove($key)
    {
        unset($this->items[$key]);
    }

    /**
     * @param array  $config
     * @param string $key
     * @param mixed  $defaultValue
     */
    protected function removeItemWithDefaultValue(array &$config, $key, $defaultValue = false)
    {
        if (array_key_exists($key, $config) && $defaultValue === $config[$key]) {
            unset($config[$key]);
        }
    }
}
