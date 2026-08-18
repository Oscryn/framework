<?php

namespace Oscryn\Auth;

use Oscryn\Extensions\Model;
use Oscryn\Http\Session;

class Auth
{
    protected static ?self $instance = null;

    protected string $model = 'App\Models\User';

    protected ?Model $user = null;

    protected bool $resolved = false;

    public static function instance(): static
    {
        return static::$instance ??= new static();
    }

    public static function useModel(string $class): void
    {
        static::instance()->model = $class;
        static::instance()->forgetUser();
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function guest(): bool
    {
        return !$this->check();
    }

    public function id(): mixed
    {
        return Session::get('auth_id');
    }

    public function user(): ?Model
    {
        if ($this->resolved) {
            return $this->user;
        }

        $this->resolved = true;

        $id = $this->id();

        if ($id === null) {
            return $this->user = null;
        }

        $class = $this->model;

        return $this->user = $class::find($id);
    }

    public function login(Model $user): void
    {
        Session::put('auth_id', $user->getKey());
        Session::regenerate();

        $this->user = $user;
        $this->resolved = true;
    }

    public function loginUsingId(mixed $id): ?Model
    {
        $class = $this->model;
        $user = $class::find($id);

        if ($user !== null) {
            $this->login($user);
        }

        return $user;
    }

    public function logout(): void
    {
        Session::forget('auth_id');
        Session::regenerate();

        $this->forgetUser();
    }

    public function attempt(array $credentials): bool
    {
        $class = $this->model;

        $password = $credentials['password'] ?? null;
        unset($credentials['password']);

        $query = $class::query();

        foreach ($credentials as $key => $value) {
            $query->where($key, $value);
        }

        $user = $query->first();

        if ($user === null || !is_string($password) || !password_verify($password, $user->getAttribute('password'))) {
            return false;
        }

        $this->login($user);

        return true;
    }

    public function forgetUser(): void
    {
        $this->user = null;
        $this->resolved = false;
    }
}
