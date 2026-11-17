<?php

namespace Tests\Feature;

use App\Http\Middleware\IdempotentRequest;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class IdempotentRequestTest extends TestCase
{
    private int $calls = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Route::post('/_idem-test', function () {
            $this->calls++;

            return response()->json(['success' => true, 'data' => ['id' => $this->calls]], 201);
        })->middleware(IdempotentRequest::class);

        Route::post('/_idem-fail', function () {
            $this->calls++;

            return response()->json(['success' => false], 500);
        })->middleware(IdempotentRequest::class);
    }

    public function test_repeat_with_same_key_returns_first_response(): void
    {
        $first = $this->postJson('/_idem-test', [], ['Idempotency-Key' => 'k1']);
        $again = $this->postJson('/_idem-test', [], ['Idempotency-Key' => 'k1']);

        $first->assertStatus(201)->assertJsonPath('data.id', 1);
        $again->assertStatus(201)->assertJsonPath('data.id', 1)->assertHeader('Idempotent-Replayed', 'true');
        $this->assertSame(1, $this->calls);
    }

    public function test_different_keys_and_no_key_run_each_time(): void
    {
        $this->postJson('/_idem-test', [], ['Idempotency-Key' => 'a'])->assertJsonPath('data.id', 1);
        $this->postJson('/_idem-test', [], ['Idempotency-Key' => 'b'])->assertJsonPath('data.id', 2);
        $this->postJson('/_idem-test')->assertJsonPath('data.id', 3);
        $this->postJson('/_idem-test')->assertJsonPath('data.id', 4);
    }

    public function test_same_key_from_another_token_is_not_shared(): void
    {
        $this->postJson('/_idem-test', [], ['Idempotency-Key' => 'k', 'Authorization' => 'Bearer one'])->assertJsonPath('data.id', 1);
        $this->postJson('/_idem-test', [], ['Idempotency-Key' => 'k', 'Authorization' => 'Bearer two'])->assertJsonPath('data.id', 2);
    }

    public function test_server_errors_are_not_stored(): void
    {
        $this->postJson('/_idem-fail', [], ['Idempotency-Key' => 'f'])->assertStatus(500);
        $this->postJson('/_idem-fail', [], ['Idempotency-Key' => 'f'])->assertStatus(500);
        $this->assertSame(2, $this->calls);
    }
}
