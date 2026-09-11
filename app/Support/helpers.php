<?php

use Illuminate\Support\Str;
use Illuminate\Support\Stringable;

if (! function_exists('str')) {
    /**
     * Get a new stringable object from the given string.
     *
     * @param  string|null  $string
     * @return \Illuminate\Support\Stringable
     */
    function str($string = null)
    {
        if (is_null($string)) {
            return new class
            {
                public function __call($method, $parameters)
                {
                    return Str::$method(...$parameters);
                }
            };
        }

        return new Stringable($string);
    }
}
