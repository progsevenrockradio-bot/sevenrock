<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Gateways\MercadoPago;

use App\Exceptions\Payment\MercadoPago\MercadoPagoPayloadValidationException;
use App\Exceptions\Payment\MercadoPago\MercadoPagoSignatureValidationException;
use App\Exceptions\Payment\MercadoPago\MercadoPagoWebhookConfigException;
use App\Services\Gateways\MercadoPago\MercadoPagoWebhookAuthenticator;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class MercadoPagoWebhookAuthenticatorTest extends TestCase
{
    private const DUMMY_SECRET = 'dummy_secret_key_for_testing_purposes_only';
    private const DUMMY_REQUEST_ID = '00000000-0000-0000-0000-000000000001';

    private function generateValidHeader(string $secret, ?string $dataId, ?string $requestId, string $ts): string
    {
        $parts = [];
        if ($dataId !== null && $dataId !== '') {
            $parts[] = 'id:' . strtolower($dataId);
        }
        if ($requestId !== null && $requestId !== '') {
            $parts[] = 'request-id:' . $requestId;
        }
        $parts[] = 'ts:' . $ts;
        $manifest = implode(';', $parts) . ';';
        $hash = hash_hmac('sha256', $manifest, $secret);

        return "ts={$ts},v1={$hash}";
    }

    private function createAuthenticator(
        ?string $secret = self::DUMMY_SECRET,
        mixed $tolerance = null,
        ?LoggerInterface $logger = null
    ): MercadoPagoWebhookAuthenticator {
        return new MercadoPagoWebhookAuthenticator(
            logger: $logger ?? new NullLogger(),
            secretOverride: $secret,
            toleranceOverride: $tolerance,
            useOverrides: true
        );
    }

    public function test_throws_config_exception_when_secret_is_null_or_empty(): void
    {
        $authenticatorNull = $this->createAuthenticator(secret: null);
        $request = Request::create('/talentos/webhook/mercadopago?data.id=123', 'POST');

        $this->expectException(MercadoPagoWebhookConfigException::class);
        $authenticatorNull->authenticate($request);
    }

    public function test_throws_config_exception_when_secret_is_empty_string(): void
    {
        $authenticatorEmpty = $this->createAuthenticator(secret: '   ');
        $request = Request::create('/talentos/webhook/mercadopago?data.id=123', 'POST');

        $this->expectException(MercadoPagoWebhookConfigException::class);
        $authenticatorEmpty->authenticate($request);
    }

    public function test_throws_config_exception_when_tolerance_is_malformed(): void
    {
        $authenticatorNegative = $this->createAuthenticator(tolerance: -10);
        $request = Request::create('/talentos/webhook/mercadopago?data.id=123', 'POST');
        $this->expectException(MercadoPagoWebhookConfigException::class);
        $authenticatorNegative->authenticate($request);
    }

    public function test_throws_config_exception_when_tolerance_is_invalid_type(): void
    {
        $authenticatorString = $this->createAuthenticator(tolerance: 'invalid_float_3.14');
        $request = Request::create('/talentos/webhook/mercadopago?data.id=123', 'POST');
        $this->expectException(MercadoPagoWebhookConfigException::class);
        $authenticatorString->authenticate($request);
    }

    public function test_throws_payload_exception_when_signed_resource_id_is_missing(): void
    {
        $request = Request::create('/talentos/webhook/mercadopago', 'POST');
        $authenticator = $this->createAuthenticator();

        $this->expectException(MercadoPagoPayloadValidationException::class);
        try {
            $authenticator->authenticate($request);
        } catch (MercadoPagoPayloadValidationException $e) {
            $this->assertSame('MISSING_SIGNED_RESOURCE_ID', $e->getFailureReason());
            throw $e;
        }
    }

    public function test_throws_payload_exception_when_signed_resource_id_is_empty(): void
    {
        $request = Request::create('/talentos/webhook/mercadopago?data.id=', 'POST');
        $authenticator = $this->createAuthenticator();

        $this->expectException(MercadoPagoPayloadValidationException::class);
        try {
            $authenticator->authenticate($request);
        } catch (MercadoPagoPayloadValidationException $e) {
            $this->assertSame('EMPTY_RESOURCE_IDENTIFIER', $e->getFailureReason());
            throw $e;
        }
    }

    public function test_throws_payload_exception_on_duplicate_query_parameter(): void
    {
        // Parameter duplication in query string: data.id=111&data.id=222
        $request = Request::create('/talentos/webhook/mercadopago?data.id=111&data.id=222', 'POST');
        $authenticator = $this->createAuthenticator();

        $this->expectException(MercadoPagoPayloadValidationException::class);
        try {
            $authenticator->authenticate($request);
        } catch (MercadoPagoPayloadValidationException $e) {
            $this->assertSame('DUPLICATE_RESOURCE_IDENTIFIER', $e->getFailureReason());
            throw $e;
        }
    }

    public function test_throws_payload_exception_on_alias_query_parameter(): void
    {
        // Ambiguous aliases in query string: data.id=111&data_id=111
        $request = Request::create('/talentos/webhook/mercadopago?data.id=111&data_id=111', 'POST');
        $authenticator = $this->createAuthenticator();

        $this->expectException(MercadoPagoPayloadValidationException::class);
        try {
            $authenticator->authenticate($request);
        } catch (MercadoPagoPayloadValidationException $e) {
            $this->assertSame('DUPLICATE_RESOURCE_IDENTIFIER', $e->getFailureReason());
            throw $e;
        }
    }

    public function test_throws_payload_exception_on_bracket_pollution(): void
    {
        // Bracketed array pollution: data.id[]=111
        $request = Request::create('/talentos/webhook/mercadopago?data.id%5B%5D=111', 'POST');
        $authenticator = $this->createAuthenticator();

        $this->expectException(MercadoPagoPayloadValidationException::class);
        try {
            $authenticator->authenticate($request);
        } catch (MercadoPagoPayloadValidationException $e) {
            $this->assertSame('POLLUTED_QUERY_PARAMETER', $e->getFailureReason());
            throw $e;
        }
    }

    public function test_throws_signature_exception_when_x_signature_header_is_missing(): void
    {
        $request = Request::create('/talentos/webhook/mercadopago?data.id=123', 'POST');
        $authenticator = $this->createAuthenticator();

        $this->expectException(MercadoPagoSignatureValidationException::class);
        try {
            $authenticator->authenticate($request);
        } catch (MercadoPagoSignatureValidationException $e) {
            $this->assertSame('MissingSignatureHeader', $e->getFailureReason());
            throw $e;
        }
    }

    public function test_throws_signature_exception_when_x_signature_header_is_malformed(): void
    {
        $request = Request::create('/talentos/webhook/mercadopago?data.id=123', 'POST');
        $request->headers->set('x-signature', 'malformed_signature_without_ts_and_v1');
        $authenticator = $this->createAuthenticator();

        $this->expectException(MercadoPagoSignatureValidationException::class);
        try {
            $authenticator->authenticate($request);
        } catch (MercadoPagoSignatureValidationException $e) {
            $this->assertSame('MalformedSignatureHeader', $e->getFailureReason());
            throw $e;
        }
    }

    public function test_throws_signature_exception_when_signature_mismatches(): void
    {
        $ts = (string) (int) (microtime(true) * 1000);
        $request = Request::create('/talentos/webhook/mercadopago?data.id=12345', 'POST');
        $request->headers->set('x-signature', "ts={$ts},v1=invalid_hmac_sha256_hash_here_1234567890abcdef");
        $request->headers->set('x-request-id', self::DUMMY_REQUEST_ID);

        $authenticator = $this->createAuthenticator();

        $this->expectException(MercadoPagoSignatureValidationException::class);
        try {
            $authenticator->authenticate($request);
        } catch (MercadoPagoSignatureValidationException $e) {
            $this->assertSame('SignatureMismatch', $e->getFailureReason());
            $this->assertSame(self::DUMMY_REQUEST_ID, $e->getRequestId());
            throw $e;
        }
    }

    public function test_throws_payload_exception_when_json_body_is_malformed(): void
    {
        $request = Request::create('/talentos/webhook/mercadopago?data.id=123', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], '{bad-json-unclosed:');

        $authenticator = $this->createAuthenticator();

        $this->expectException(MercadoPagoPayloadValidationException::class);
        try {
            $authenticator->authenticate($request);
        } catch (MercadoPagoPayloadValidationException $e) {
            $this->assertSame('INVALID_JSON_BODY', $e->getFailureReason());
            throw $e;
        }
    }

    public function test_throws_payload_exception_when_body_data_is_not_an_object(): void
    {
        $content = json_encode(['data' => 'invalid_string_not_object']);
        $request = Request::create('/talentos/webhook/mercadopago?data.id=123', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $content);

        $authenticator = $this->createAuthenticator();

        $this->expectException(MercadoPagoPayloadValidationException::class);
        try {
            $authenticator->authenticate($request);
        } catch (MercadoPagoPayloadValidationException $e) {
            $this->assertSame('INVALID_DATA_STRUCTURE', $e->getFailureReason());
            throw $e;
        }
    }

    public function test_throws_payload_exception_when_body_data_id_has_incompatible_type(): void
    {
        $content = json_encode(['data' => ['id' => ['array_not_allowed']]]);
        $request = Request::create('/talentos/webhook/mercadopago?data.id=123', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $content);

        $authenticator = $this->createAuthenticator();

        $this->expectException(MercadoPagoPayloadValidationException::class);
        try {
            $authenticator->authenticate($request);
        } catch (MercadoPagoPayloadValidationException $e) {
            $this->assertSame('INVALID_IDENTIFIER_TYPE', $e->getFailureReason());
            throw $e;
        }
    }

    public function test_throws_payload_exception_when_query_and_body_differ_by_case(): void
    {
        // R03: Strict identity equality - case differences MUST be rejected
        $dataIdQuery = 'ABC123XYZ';
        $dataIdBody = 'abc123xyz';
        $content = json_encode(['data' => ['id' => $dataIdBody]]);

        $request = Request::create("/talentos/webhook/mercadopago?data.id={$dataIdQuery}", 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $content);

        $authenticator = $this->createAuthenticator();

        $this->expectException(MercadoPagoPayloadValidationException::class);
        try {
            $authenticator->authenticate($request);
        } catch (MercadoPagoPayloadValidationException $e) {
            $this->assertSame('CONFLICTING_IDENTIFIERS', $e->getFailureReason());
            throw $e;
        }
    }

    public function test_throws_payload_exception_when_body_data_id_has_outer_whitespace(): void
    {
        // F02: Outer whitespace in body identifier must be rejected with WHITESPACE_IN_IDENTIFIER
        $dataIdQuery = '123456';
        $dataIdBody = '123456 ';
        $content = json_encode(['data' => ['id' => $dataIdBody]]);

        $request = Request::create("/talentos/webhook/mercadopago?data.id={$dataIdQuery}", 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $content);

        $authenticator = $this->createAuthenticator();

        $this->expectException(MercadoPagoPayloadValidationException::class);
        try {
            $authenticator->authenticate($request);
        } catch (MercadoPagoPayloadValidationException $e) {
            $this->assertSame('WHITESPACE_IN_IDENTIFIER', $e->getFailureReason());
            throw $e;
        }
    }

    public function test_throws_payload_exception_when_query_and_body_have_different_valid_identifiers(): void
    {
        // F02 / R03: Strict identity equality - both identifiers are valid but differ
        $dataIdQuery = '123456';
        $dataIdBody = '654321';
        $content = json_encode(['data' => ['id' => $dataIdBody]]);

        $request = Request::create("/talentos/webhook/mercadopago?data.id={$dataIdQuery}", 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $content);

        $authenticator = $this->createAuthenticator();

        $this->expectException(MercadoPagoPayloadValidationException::class);
        try {
            $authenticator->authenticate($request);
        } catch (MercadoPagoPayloadValidationException $e) {
            $this->assertSame('CONFLICTING_IDENTIFIERS', $e->getFailureReason());
            throw $e;
        }
    }

    public function test_passes_authentication_and_strictly_returns_query_identifier(): void
    {
        $dataId = 'PAY123456';
        $ts = (string) (int) (microtime(true) * 1000);
        $content = json_encode(['data' => ['id' => $dataId], 'type' => 'payment']);

        $request = Request::create("/talentos/webhook/mercadopago?data.id={$dataId}", 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $content);

        $sig = $this->generateValidHeader(self::DUMMY_SECRET, $dataId, self::DUMMY_REQUEST_ID, $ts);
        $request->headers->set('x-signature', $sig);
        $request->headers->set('x-request-id', self::DUMMY_REQUEST_ID);

        $authenticator = $this->createAuthenticator();
        $verifiedId = $authenticator->authenticate($request);

        $this->assertSame($dataId, $verifiedId);
    }

    public function test_throws_signature_exception_when_timestamp_exceeds_tolerance_if_configured(): void
    {
        $dataId = 'PAY123456';
        // Timestamp from 2 hours ago
        $oldTs = (string) ((int) (microtime(true) * 1000) - (7200 * 1000));

        $request = Request::create("/talentos/webhook/mercadopago?data.id={$dataId}", 'POST');
        $sig = $this->generateValidHeader(self::DUMMY_SECRET, $dataId, self::DUMMY_REQUEST_ID, $oldTs);
        $request->headers->set('x-signature', $sig);
        $request->headers->set('x-request-id', self::DUMMY_REQUEST_ID);

        $authenticator = $this->createAuthenticator(tolerance: 300);

        $this->expectException(MercadoPagoSignatureValidationException::class);
        try {
            $authenticator->authenticate($request);
        } catch (MercadoPagoSignatureValidationException $e) {
            $this->assertSame('TimestampOutOfTolerance', $e->getFailureReason());
            throw $e;
        }
    }

    public function test_throws_payload_exception_when_query_id_has_leading_or_trailing_whitespace(): void
    {
        $requestLeading = Request::create('/talentos/webhook/mercadopago?data.id=%2012345', 'POST');
        $authenticator = $this->createAuthenticator();

        $this->expectException(MercadoPagoPayloadValidationException::class);
        try {
            $authenticator->authenticate($requestLeading);
        } catch (MercadoPagoPayloadValidationException $e) {
            $this->assertSame('WHITESPACE_IN_IDENTIFIER', $e->getFailureReason());
            throw $e;
        }
    }

    public function test_throws_payload_exception_when_query_id_is_only_whitespace(): void
    {
        // F02: An identifier composed solely of whitespace must be rejected with EMPTY_RESOURCE_IDENTIFIER
        $requestSpaces = Request::create('/talentos/webhook/mercadopago?data.id=%20%20%20', 'POST');
        $authenticator = $this->createAuthenticator();

        $this->expectException(MercadoPagoPayloadValidationException::class);
        try {
            $authenticator->authenticate($requestSpaces);
        } catch (MercadoPagoPayloadValidationException $e) {
            $this->assertSame('EMPTY_RESOURCE_IDENTIFIER', $e->getFailureReason());
            throw $e;
        }
    }

    public function test_throws_payload_exception_when_query_id_has_internal_whitespace(): void
    {
        $requestInternal = Request::create('/talentos/webhook/mercadopago?data.id=12%20345', 'POST');
        $authenticator = $this->createAuthenticator();

        $this->expectException(MercadoPagoPayloadValidationException::class);
        try {
            $authenticator->authenticate($requestInternal);
        } catch (MercadoPagoPayloadValidationException $e) {
            $this->assertSame('INVALID_IDENTIFIER_FORMAT', $e->getFailureReason());
            throw $e;
        }
    }

    public function test_throws_payload_exception_when_query_id_contains_control_characters(): void
    {
        $requestControl = Request::create('/talentos/webhook/mercadopago?data.id=123%0045', 'POST');
        $authenticator = $this->createAuthenticator();

        $this->expectException(MercadoPagoPayloadValidationException::class);
        try {
            $authenticator->authenticate($requestControl);
        } catch (MercadoPagoPayloadValidationException $e) {
            $this->assertSame('INVALID_IDENTIFIER_FORMAT', $e->getFailureReason());
            throw $e;
        }
    }

    public function test_throws_payload_exception_when_body_data_id_has_whitespace(): void
    {
        $content = json_encode(['data' => ['id' => ' 12345 ']]);
        $request = Request::create('/talentos/webhook/mercadopago?data.id=12345', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $content);

        $authenticator = $this->createAuthenticator();

        $this->expectException(MercadoPagoPayloadValidationException::class);
        try {
            $authenticator->authenticate($request);
        } catch (MercadoPagoPayloadValidationException $e) {
            $this->assertSame('WHITESPACE_IN_IDENTIFIER', $e->getFailureReason());
            throw $e;
        }
    }

    public function test_throws_payload_exception_when_json_body_is_root_array(): void
    {
        $content = json_encode([['data' => ['id' => '12345']]]);
        $request = Request::create('/talentos/webhook/mercadopago?data.id=12345', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $content);

        $authenticator = $this->createAuthenticator();

        $this->expectException(MercadoPagoPayloadValidationException::class);
        try {
            $authenticator->authenticate($request);
        } catch (MercadoPagoPayloadValidationException $e) {
            $this->assertSame('INVALID_JSON_BODY', $e->getFailureReason());
            throw $e;
        }
    }

    public function test_allows_data_key_inside_nested_metadata_object_without_false_positive(): void
    {
        // F01 Contraexample 1: "data" inside "metadata" belongs to a different object scope
        $dataId = 'PAY123456';
        $ts = (string) (int) (microtime(true) * 1000);
        $rawJson = '{"data":{"id":"' . $dataId . '"},"metadata":{"data":1}}';

        $request = Request::create("/talentos/webhook/mercadopago?data.id={$dataId}", 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $rawJson);

        $sig = $this->generateValidHeader(self::DUMMY_SECRET, $dataId, self::DUMMY_REQUEST_ID, $ts);
        $request->headers->set('x-signature', $sig);
        $request->headers->set('x-request-id', self::DUMMY_REQUEST_ID);

        $authenticator = $this->createAuthenticator();
        $verifiedId = $authenticator->authenticate($request);

        $this->assertSame($dataId, $verifiedId);
    }

    public function test_allows_escaped_quotes_in_key_names_without_false_positive(): void
    {
        // F01 Contraexample 2: Key "x\"data" is distinct from "data"
        $dataId = 'PAY123456';
        $ts = (string) (int) (microtime(true) * 1000);
        $rawJson = '{"data":{"id":"' . $dataId . '"},"x\"data":1}';

        $request = Request::create("/talentos/webhook/mercadopago?data.id={$dataId}", 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $rawJson);

        $sig = $this->generateValidHeader(self::DUMMY_SECRET, $dataId, self::DUMMY_REQUEST_ID, $ts);
        $request->headers->set('x-signature', $sig);
        $request->headers->set('x-request-id', self::DUMMY_REQUEST_ID);

        $authenticator = $this->createAuthenticator();
        $verifiedId = $authenticator->authenticate($request);

        $this->assertSame($dataId, $verifiedId);
    }

    public function test_throws_payload_exception_on_unicode_escaped_duplicate_root_data_key(): void
    {
        // F01 Contraexample 3: "\u0064ata" decodes to "data", which is duplicate at root
        $rawJson = '{"data":{"id":"111"},"\u0064ata":{"id":"222"}}';
        $request = Request::create('/talentos/webhook/mercadopago?data.id=111', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $rawJson);

        $authenticator = $this->createAuthenticator();

        $this->expectException(MercadoPagoPayloadValidationException::class);
        try {
            $authenticator->authenticate($request);
        } catch (MercadoPagoPayloadValidationException $e) {
            $this->assertSame('DUPLICATE_JSON_KEY', $e->getFailureReason());
            throw $e;
        }
    }

    public function test_throws_payload_exception_on_duplicate_data_id_within_data_object(): void
    {
        // F01 Contraexample 4: Duplicate "id" inside "data" object
        $rawJson = '{"data":{"id":"111","id":"222"}}';
        $request = Request::create('/talentos/webhook/mercadopago?data.id=111', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $rawJson);

        $authenticator = $this->createAuthenticator();

        $this->expectException(MercadoPagoPayloadValidationException::class);
        try {
            $authenticator->authenticate($request);
        } catch (MercadoPagoPayloadValidationException $e) {
            $this->assertSame('DUPLICATE_JSON_KEY', $e->getFailureReason());
            throw $e;
        }
    }

    public function test_throws_payload_exception_on_duplicate_root_data_key(): void
    {
        // F01 Contraexample 5: Literal duplicate "data" at root level
        $rawJson = '{"data":{"id":"111"},"data":{"id":"222"}}';
        $request = Request::create('/talentos/webhook/mercadopago?data.id=111', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $rawJson);

        $authenticator = $this->createAuthenticator();

        $this->expectException(MercadoPagoPayloadValidationException::class);
        try {
            $authenticator->authenticate($request);
        } catch (MercadoPagoPayloadValidationException $e) {
            $this->assertSame('DUPLICATE_JSON_KEY', $e->getFailureReason());
            throw $e;
        }
    }

    public function test_throws_payload_exception_on_escaped_backslashes_duplicate_key(): void
    {
        // F01 Contraexample 6: Escaped backslashes in keys must decode and detect duplicate
        $rawJson = '{"key\\\\name":1,"key\\\\name":2,"data":{"id":"111"}}';
        $request = Request::create('/talentos/webhook/mercadopago?data.id=111', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $rawJson);

        $authenticator = $this->createAuthenticator();

        $this->expectException(MercadoPagoPayloadValidationException::class);
        try {
            $authenticator->authenticate($request);
        } catch (MercadoPagoPayloadValidationException $e) {
            $this->assertSame('DUPLICATE_JSON_KEY', $e->getFailureReason());
            throw $e;
        }
    }

    public function test_allows_string_values_containing_braces_and_escapes_without_scanner_confusion(): void
    {
        // F01: String values containing braces, quotes, and colons must not alter structural state
        $dataId = 'PAY123456';
        $ts = (string) (int) (microtime(true) * 1000);
        $rawJson = '{"data":{"id":"' . $dataId . '"},"note":"Escaped \\"quotes\\", {braces}, [brackets], and : colons"}';

        $request = Request::create("/talentos/webhook/mercadopago?data.id={$dataId}", 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $rawJson);

        $sig = $this->generateValidHeader(self::DUMMY_SECRET, $dataId, self::DUMMY_REQUEST_ID, $ts);
        $request->headers->set('x-signature', $sig);
        $request->headers->set('x-request-id', self::DUMMY_REQUEST_ID);

        $authenticator = $this->createAuthenticator();
        $verifiedId = $authenticator->authenticate($request);

        $this->assertSame($dataId, $verifiedId);
    }

    public function test_throws_payload_exception_on_duplicate_key_inside_nested_array_object(): void
    {
        // F01 Contraexample 7a: Duplicate key inside an object nested within a JSON array
        $rawJson = '{"data":{"id":"111"},"items":[{"item_id":"A","item_id":"B"}]}';
        $request = Request::create('/talentos/webhook/mercadopago?data.id=111', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $rawJson);

        $authenticator = $this->createAuthenticator();

        $this->expectException(MercadoPagoPayloadValidationException::class);
        try {
            $authenticator->authenticate($request);
        } catch (MercadoPagoPayloadValidationException $e) {
            $this->assertSame('DUPLICATE_JSON_KEY', $e->getFailureReason());
            throw $e;
        }
    }

    public function test_allows_distinct_objects_with_same_keys_nested_within_array(): void
    {
        // F01 Contraexample 7b: Distinct objects inside an array have separate scopes and may share key names
        $dataId = 'PAY123456';
        $ts = (string) (int) (microtime(true) * 1000);
        $rawJson = '{"data":{"id":"' . $dataId . '"},"items":[{"item_id":"A"},{"item_id":"B"}]}';

        $request = Request::create("/talentos/webhook/mercadopago?data.id={$dataId}", 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $rawJson);

        $sig = $this->generateValidHeader(self::DUMMY_SECRET, $dataId, self::DUMMY_REQUEST_ID, $ts);
        $request->headers->set('x-signature', $sig);
        $request->headers->set('x-request-id', self::DUMMY_REQUEST_ID);

        $authenticator = $this->createAuthenticator();
        $verifiedId = $authenticator->authenticate($request);

        $this->assertSame($dataId, $verifiedId);
    }

    public function test_logs_do_not_contain_payload_nor_secrets_nor_raw_exception_messages(): void
    {
        $loggedContexts = [];
        $mockLogger = $this->createMock(LoggerInterface::class);
        $mockLogger->method('warning')
            ->willReturnCallback(function (string $message, array $context) use (&$loggedContexts): void {
                $loggedContexts[] = $context;
            });

        $sensitivePayload = '{"secret_customer_token":"super_secret_12345","amount":999}';
        $maliciousRequestId = "req-id-with-leak\nSELECT * FROM users WHERE pass='123';";

        $request = Request::create('/talentos/webhook/mercadopago?data.id=TEST1', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $sensitivePayload);

        $request->headers->set('x-signature', 'ts=1000,v1=invalid_hash');
        $request->headers->set('x-request-id', $maliciousRequestId);

        $authenticator = $this->createAuthenticator(logger: $mockLogger);

        try {
            $authenticator->authenticate($request);
        } catch (MercadoPagoSignatureValidationException) {
            // expected
        }

        $this->assertNotEmpty($loggedContexts);
        foreach ($loggedContexts as $context) {
            $json = json_encode($context);
            // Must NOT contain sensitive tokens or secret keys
            $this->assertStringNotContainsString('super_secret_12345', $json);
            $this->assertStringNotContainsString(self::DUMMY_SECRET, $json);
            $this->assertStringNotContainsString('invalid_hash', $json);

            // Sanitizer verification (V04): permitted characters, length <= 64, forbidden characters stripped
            if (isset($context['request_id'])) {
                $this->assertLessThanOrEqual(64, strlen($context['request_id']));
                $this->assertMatchesRegularExpression('/^[a-zA-Z0-9_\-\.]+$/', $context['request_id']);
                // Verify that control and non-whitelisted characters were stripped
                $this->assertStringNotContainsString("\n", $context['request_id']);
                $this->assertStringNotContainsString(' ', $context['request_id']);
                $this->assertStringNotContainsString('*', $context['request_id']);
                $this->assertStringNotContainsString("'", $context['request_id']);
                $this->assertStringNotContainsString(';', $context['request_id']);
                $this->assertStringNotContainsString('=', $context['request_id']);
            }

            $this->assertArrayHasKey('event', $context);
            $this->assertArrayHasKey('failure_reason', $context);
        }
    }

    public function test_accepts_payload_of_exactly_max_allowed_size_65536_bytes(): void
    {
        // L01 Boundary Test 1: Body of exactly 65536 bytes (MAX_JSON_PAYLOAD_BYTES)
        $dataId = 'PAY123456';
        $ts = (string) (int) (microtime(true) * 1000);

        $prefix = '{"data":{"id":"' . $dataId . '"},"padding":"';
        $suffix = '"}';
        $paddingLength = 65536 - strlen($prefix) - strlen($suffix);
        $rawJson = $prefix . str_repeat('a', $paddingLength) . $suffix;

        $this->assertSame(65536, strlen($rawJson));

        $request = Request::create("/talentos/webhook/mercadopago?data.id={$dataId}", 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $rawJson);

        $sig = $this->generateValidHeader(self::DUMMY_SECRET, $dataId, self::DUMMY_REQUEST_ID, $ts);
        $request->headers->set('x-signature', $sig);
        $request->headers->set('x-request-id', self::DUMMY_REQUEST_ID);

        $authenticator = $this->createAuthenticator();
        $verifiedId = $authenticator->authenticate($request);

        $this->assertSame($dataId, $verifiedId);
    }

    public function test_rejects_payload_exceeding_max_by_one_byte_65537_bytes(): void
    {
        // L01 Boundary Test 2: Body of exactly 65537 bytes (MAX_JSON_PAYLOAD_BYTES + 1)
        $dataId = 'PAY123456';
        $prefix = '{"data":{"id":"' . $dataId . '"},"padding":"';
        $suffix = '"}';
        $paddingLength = 65537 - strlen($prefix) - strlen($suffix);
        $rawJson = $prefix . str_repeat('a', $paddingLength) . $suffix;

        $this->assertSame(65537, strlen($rawJson));

        $request = Request::create("/talentos/webhook/mercadopago?data.id={$dataId}", 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $rawJson);

        $authenticator = $this->createAuthenticator();

        $this->expectException(MercadoPagoPayloadValidationException::class);
        try {
            $authenticator->authenticate($request);
        } catch (MercadoPagoPayloadValidationException $e) {
            $this->assertSame('PAYLOAD_TOO_LARGE', $e->getFailureReason());
            throw $e;
        }
    }

    public function test_rejects_excessively_large_valid_json_payload(): void
    {
        // L01 Boundary Test 3: Excessively large valid JSON payload (100,000 bytes)
        $dataId = 'PAY123456';
        $targetBytes = 100000;
        $prefix = '{"data":{"id":"' . $dataId . '"},"large_content":"';
        $suffix = '"}';
        $paddingLength = $targetBytes - strlen($prefix) - strlen($suffix);
        $rawJson = $prefix . str_repeat('z', $paddingLength) . $suffix;

        $this->assertSame($targetBytes, strlen($rawJson));

        $request = Request::create("/talentos/webhook/mercadopago?data.id={$dataId}", 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $rawJson);

        $authenticator = $this->createAuthenticator();

        $this->expectException(MercadoPagoPayloadValidationException::class);
        try {
            $authenticator->authenticate($request);
        } catch (MercadoPagoPayloadValidationException $e) {
            $this->assertSame('PAYLOAD_TOO_LARGE', $e->getFailureReason());
            throw $e;
        }
    }

    public function test_rejects_excessively_large_invalid_json_payload_as_payload_too_large(): void
    {
        // L01 Boundary Test 4: Excessively large invalid JSON payload (100,000 bytes)
        $dataId = 'PAY123456';
        $rawMalformedContent = '{"malformed_syntax_unclosed": ' . str_repeat('x', 100000);

        $this->assertGreaterThan(65536, strlen($rawMalformedContent));

        $request = Request::create("/talentos/webhook/mercadopago?data.id={$dataId}", 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $rawMalformedContent);

        $authenticator = $this->createAuthenticator();

        $this->expectException(MercadoPagoPayloadValidationException::class);
        try {
            $authenticator->authenticate($request);
        } catch (MercadoPagoPayloadValidationException $e) {
            $this->assertSame('PAYLOAD_TOO_LARGE', $e->getFailureReason());
            throw $e;
        }
    }

    public function test_large_body_is_rejected_as_payload_too_large_before_json_decode(): void
    {
        // L01 Boundary Test 5: Confirmation that large body is rejected before json_decode().
        // If json_decode() ran first on invalid syntax, it would throw INVALID_JSON_BODY.
        // Because the size check runs before json_decode(), it throws PAYLOAD_TOO_LARGE.
        $dataId = 'PAY123456';
        $syntaxErrorJsonLargerThanLimit = '{invalid_json_syntax:' . str_repeat('?', 70000);

        $this->assertGreaterThan(65536, strlen($syntaxErrorJsonLargerThanLimit));

        $request = Request::create("/talentos/webhook/mercadopago?data.id={$dataId}", 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $syntaxErrorJsonLargerThanLimit);

        $authenticator = $this->createAuthenticator();

        $this->expectException(MercadoPagoPayloadValidationException::class);
        try {
            $authenticator->authenticate($request);
        } catch (MercadoPagoPayloadValidationException $e) {
            $this->assertSame('PAYLOAD_TOO_LARGE', $e->getFailureReason());
            $this->assertNotSame('INVALID_JSON_BODY', $e->getFailureReason());
            throw $e;
        }
    }
}
