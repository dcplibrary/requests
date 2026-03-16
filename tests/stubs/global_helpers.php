<?php

/**
 * Global helpers for tests that run controller code without full Laravel.
 * No namespace so these define \abort, \route, \redirect.
 */

use Symfony\Component\HttpKernel\Exception\HttpException;

if (! function_exists('abort')) {
    function abort(int $code, string $message = '', array $headers = [])
    {
        throw new HttpException($code, $message, null, $headers);
    }
}

if (! function_exists('route')) {
    function route($name, $parameters = [], $absolute = true)
    {
        if ($parameters instanceof \Illuminate\Database\Eloquent\Model) {
            $parameters = ['patronRequest' => $parameters];
        }
        $id = is_array($parameters) && isset($parameters['patronRequest'])
            ? $parameters['patronRequest']->getKey()
            : 0;
        return 'http://example.com/request/' . $id;
    }
}

if (! function_exists('redirect')) {
    function redirect($to = null)
    {
        return new class ($to) {
            public $targetUrl;

            public function __construct($to = null)
            {
                $this->targetUrl = $to ?? 'http://example.com';
            }

            public function with($key, $value)
            {
                return $this;
            }
        };
    }
}
