<?php

declare(strict_types=1);

namespace Tests\Http;

use Oscryn\Exceptions\HttpException;
use Oscryn\Exceptions\ValidationException;
use Oscryn\Http\FormRequest;
use Oscryn\Routing\Route;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\Requests\StoreTodoRequest;

final class FormRequestTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $_GET = [];
        $_POST = [];
        $_SERVER = [];
    }

    public function test_valid_request_resolves_and_returns_validated_data(): void
    {
        $_POST['title'] = 'Buy milk';

        $request = new StoreTodoRequest();
        $request->validateResolved();

        $this->assertSame(['title' => 'Buy milk'], $request->validated());
    }

    public function test_invalid_request_throws_validation_exception(): void
    {
        $_POST['title'] = 'ab';

        $this->expectException(ValidationException::class);

        (new StoreTodoRequest())->validateResolved();
    }

    public function test_errors_are_available_without_throwing(): void
    {
        $_POST['title'] = 'ab';

        $errors = (new StoreTodoRequest())->errors();

        $this->assertArrayHasKey('title', $errors);
    }

    public function test_unauthorized_request_throws_403(): void
    {
        $request = new class extends FormRequest {
            public function authorize(): bool
            {
                return false;
            }

            public function rules(): array
            {
                return [];
            }
        };

        try {
            $request->validateResolved();
            $this->fail('Expected an HttpException');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->status());
        }
    }

    public function test_route_injects_and_validates_form_request(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/todos';
        $_POST['title'] = 'Buy milk';

        $route = new Route(['POST'], '/todos', fn (StoreTodoRequest $request) => $request->validated()['title']);

        $this->assertTrue($route->matches('POST', '/todos'));
        $this->assertSame('Buy milk', $route->run()->content());
    }

    public function test_route_injects_plain_request_still_works(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/ping';

        $route = new Route(['GET'], '/ping', fn (\Oscryn\Http\Request $request) => get_class($request));

        $this->assertTrue($route->matches('GET', '/ping'));
        $this->assertSame(\Oscryn\Http\Request::class, $route->run()->content());
    }
}
