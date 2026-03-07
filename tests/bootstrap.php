<?php

/**
 * PHPUnit bootstrap for the dcplibrary/sfp package.
 *
 * This package uses individual illuminate/* components rather than the full
 * laravel/framework bundle. However, src/Models/User.php extends
 * Illuminate\Foundation\Auth\User, which lives in illuminate/foundation —
 * a package that cannot be installed standalone (it's bundled in laravel/framework).
 *
 * To allow unit tests to run without the full framework, we define a minimal
 * stub for Illuminate\Foundation\Auth\User that satisfies the inheritance chain.
 * This is only registered in the test environment and only if the class hasn't
 * already been loaded (e.g. in an integration environment with laravel/framework).
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Minimal facade boot for tests that reference Setting::get() (Cache facade).
require_once __DIR__ . '/bootstrap_integration_settings.php';

if (! function_exists('app')) {
    /**
     * Minimal test stub for Laravel's app() helper.
     *
     * Used by SfpRequest::scopeVisibleTo() to allow dev-only behavior checks like
     * app()->environment('local') even when laravel/framework isn't installed.
     */
    function app()
    {
        return new class {
            public function environment(...$environments)
            {
                $current = getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? 'testing');

                if (count($environments) === 0) {
                    return $current;
                }

                foreach ($environments as $env) {
                    if ($env === $current) {
                        return true;
                    }
                }

                return false;
            }
        };
    }
}

if (! class_exists(\Illuminate\Foundation\Auth\User::class)) {
    // phpcs:disable
    /**
     * Minimal test stub for Illuminate\Foundation\Auth\User.
     *
     * The real class (in laravel/framework) extends Eloquent Model and
     * implements Authenticatable + other contracts. This stub does the same
     * with only what's needed to make SfpUser instantiable in unit tests.
     */
    class Illuminate_Foundation_Auth_User_Stub
        extends \Illuminate\Database\Eloquent\Model
        implements \Illuminate\Contracts\Auth\Authenticatable
    {
        protected $table = 'sfp_users';

        public function getAuthIdentifierName()
        {
            return $this->getKeyName();
        }

        public function getAuthIdentifier()
        {
            return $this->getKey();
        }

        public function getAuthPassword()
        {
            return (string) ($this->getAttribute('password') ?? '');
        }

        public function getAuthPasswordName()
        {
            return 'password';
        }

        public function getRememberToken()
        {
            return $this->getAttribute($this->getRememberTokenName());
        }

        public function setRememberToken($value)
        {
            $this->setAttribute($this->getRememberTokenName(), $value);
        }

        public function getRememberTokenName()
        {
            return 'remember_token';
        }
    }

    // Register the stub under the real FQCN so `class_exists` resolves it.
    class_alias(
        Illuminate_Foundation_Auth_User_Stub::class,
        \Illuminate\Foundation\Auth\User::class
    );
    // phpcs:enable
}
