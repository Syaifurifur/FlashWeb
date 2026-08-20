<?php

namespace Tests\Feature;

use App\Support\ApiErrorResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Tests\TestCase;

class ApiErrorResponseTest extends TestCase
{
    public function test_technical_error_location_is_only_exposed_in_debug_mode(): void
    {
        $request = Request::create('/api/manage/accounts', 'POST');
        $exception = new RuntimeException('Kesalahan teknis untuk pengujian.');
        $previousDebug = config('app.debug');

        config(['app.debug'=>true]);
        $debugPayload = ApiErrorResponse::payload(
            $request,
            'Data akun tidak dapat diproses.',
            400,
            'ACCOUNT_TEST_ERROR',
            ['email'=>['Email tidak dapat digunakan.']],
            $exception,
        );

        $this->assertSame('Kelola akun dan akses', $debugPayload['error']['location']['module']);
        $this->assertSame('POST /api/manage/accounts', $debugPayload['error']['location']['endpoint']);
        $this->assertSame('Email', $debugPayload['error']['fields'][0]['label']);
        $this->assertSame('RuntimeException', $debugPayload['error']['technical']['exception']);
        $this->assertSame('tests/Feature/ApiErrorResponseTest.php', $debugPayload['error']['technical']['file']);
        $this->assertIsInt($debugPayload['error']['technical']['line']);

        config(['app.debug'=>false]);
        $productionPayload = ApiErrorResponse::payload(
            $request,
            'Data akun tidak dapat diproses.',
            400,
            'ACCOUNT_TEST_ERROR',
            exception: $exception,
        );
        $this->assertArrayNotHasKey('technical', $productionPayload['error']);

        config(['app.debug'=>$previousDebug]);
    }
}
