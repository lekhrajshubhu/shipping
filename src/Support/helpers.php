<?php

if (! function_exists('shipping_config')) {
    function shipping_config(?string $key = null, mixed $default = null): mixed
    {
        return $key === null ? config('shipping') : config('shipping.'.$key, $default);
    }
}
