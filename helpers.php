<?php

if (!function_exists('settings')) {

    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \JsonException
     */
    function settings(?string $key = null, $default = null): mixed
    {
        $manager = app(\Settings\Manager::class);
        if (null === $key) {
            return $manager;
        }

        return $manager->get($key, $default);
    }

}
