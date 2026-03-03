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
