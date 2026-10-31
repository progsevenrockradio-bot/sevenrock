<?php

declare(strict_types=1);

namespace App\Services\Gateways\MercadoPago;

use App\Exceptions\Payment\MercadoPago\MercadoPagoPayloadValidationException;
use App\Exceptions\Payment\MercadoPago\MercadoPagoSignatureValidationException;
use App\Exceptions\Payment\MercadoPago\MercadoPagoWebhookConfigException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use MercadoPago\Exceptions\InvalidWebhookSignatureException;
use MercadoPago\Webhook\WebhookSignatureValidator;
use Psr\Log\LoggerInterface;

class MercadoPagoWebhookAuthenticator implements MercadoPagoWebhookAuthenticatorInterface
{
    private const MAX_JSON_PAYLOAD_BYTES = 65536; // 64 KB
    private const MAX_JSON_DEPTH = 32;
    private const MAX_SCANNER_STEPS = 100000;

    public function __construct(
        private readonly ?LoggerInterface $logger = null,
        private readonly ?string $secretOverride = null,
        private readonly mixed $toleranceOverride = null,
        private readonly bool $useOverrides = false
    ) {
    }

    /**
     * Authenticates an incoming MercadoPago webhook request using HMAC-SHA256 signature verification.
     *
     * @param Request $request
     * @return string The validated, non-empty, authoritative signed resource identifier (data.id).
     *
     * @throws MercadoPagoWebhookConfigException
     * @throws MercadoPagoSignatureValidationException
     * @throws MercadoPagoPayloadValidationException
     */
    public function authenticate(Request $request): string
    {
        $secret = $this->resolveSecret($request);
        $toleranceSeconds = $this->resolveTolerance($request);

        $xSignature = $request->header('x-signature');
        $xRequestId = $request->header('x-request-id');

        // R02/V02: Deterministic raw query extraction with duplicate, pollution, whitespace, and control char guards
        $queryDataId = $this->extractQueryDataId($request, $xRequestId);

        // R01/V02: Signed resource identifier is mandatory for subscription-modifying events
        if ($queryDataId === null || $queryDataId === '') {
            $this->logWarning('mercadopago.webhook.rejected_missing_id', 'MISSING_SIGNED_RESOURCE_ID', $xRequestId, null, $request->ip());

            throw new MercadoPagoPayloadValidationException(
                'MISSING_SIGNED_RESOURCE_ID',
                $xRequestId,
                'Webhook request lacks required signed resource identifier in query string'
            );
        }

        // R05/V06: Explicit validation of JSON body, object structure, duplicate keys, and data types
        $bodyDataId = $this->extractBodyDataId($request, $xRequestId);

        // R03/V02: Strict identity equality between signed query and body resource identifier (no trimming, no strcasecmp)
        if ($bodyDataId !== null && $queryDataId !== $bodyDataId) {
            $this->logWarning('mercadopago.webhook.rejected_conflict', 'CONFLICTING_IDENTIFIERS', $xRequestId, $queryDataId, $request->ip());

            throw new MercadoPagoPayloadValidationException(
                'CONFLICTING_IDENTIFIERS',
                $xRequestId,
                'Strict identity mismatch between signed query identifier and body resource identifier'
            );
        }

        try {
            WebhookSignatureValidator::validate(
                $xSignature,
                $xRequestId,
                $queryDataId,
                $secret,
                $toleranceSeconds
            );
        } catch (InvalidWebhookSignatureException $exception) {
            $this->logWarning(
                'mercadopago.webhook.auth_failed',
                $exception->getReason(),
                $xRequestId,
                $queryDataId,
                $request->ip()
            );

            throw new MercadoPagoSignatureValidationException(
                $exception->getReason(),
                $xRequestId,
                $exception->getTimestamp(),
                'MercadoPago webhook signature verification failed: ' . $exception->getReason()
            );
        } catch (\InvalidArgumentException $exception) {
            $this->logCritical('mercadopago.webhook.config_invalid', 'INVALID_ARGUMENT', $xRequestId, $request->ip());

            throw new MercadoPagoWebhookConfigException('MercadoPago signature validator argument rejected');
        }

        // R01: Strictly return the HMAC-verified signed identifier; no body fallback
        return $queryDataId;
    }

    private function extractQueryDataId(Request $request, ?string $xRequestId): ?string
    {
        // R02: Extract query string directly to avoid parser ambiguity or loss of duplicates
        $queryString = (string) $request->server('QUERY_STRING', '');
        if ($queryString === '') {
            $queryString = (string) $request->getQueryString();
        }

        if ($queryString === '') {
            return null;
        }

        $pairs = explode('&', $queryString);
        $foundCount = 0;
        $extractedValue = null;

        foreach ($pairs as $pair) {
            if ($pair === '') {
                continue;
            }

            $parts = explode('=', $pair, 2);
            $rawKey = $parts[0];
            $rawValue = $parts[1] ?? '';

            $decodedKey = urldecode($rawKey);

            // Reject bracket-based parameter pollution
            if (str_contains($decodedKey, '[') || str_contains($decodedKey, ']')) {
                if (str_starts_with($decodedKey, 'data.id') || str_starts_with($decodedKey, 'data_id') || str_starts_with($decodedKey, 'data[')) {
                    $this->logWarning('mercadopago.webhook.rejected_polluted', 'POLLUTED_QUERY_PARAMETER', $xRequestId, null, $request->ip());

                    throw new MercadoPagoPayloadValidationException(
                        'POLLUTED_QUERY_PARAMETER',
                        $xRequestId,
                        'Bracketed query parameter pollution detected in resource identifier'
                    );
                }
            }

            if ($decodedKey === 'data.id' || $decodedKey === 'data_id') {
                $foundCount++;
                if ($foundCount > 1) {
                    $this->logWarning('mercadopago.webhook.rejected_duplicate', 'DUPLICATE_RESOURCE_IDENTIFIER', $xRequestId, null, $request->ip());

                    throw new MercadoPagoPayloadValidationException(
                        'DUPLICATE_RESOURCE_IDENTIFIER',
                        $xRequestId,
                        'Multiple or duplicate resource identifier parameters detected in query string'
                    );
                }

                if ($rawValue === '') {
                    $this->logWarning('mercadopago.webhook.rejected_empty_id', 'EMPTY_RESOURCE_IDENTIFIER', $xRequestId, null, $request->ip());

                    throw new MercadoPagoPayloadValidationException(
                        'EMPTY_RESOURCE_IDENTIFIER',
                        $xRequestId,
                        'Resource identifier parameter in query string cannot be empty'
                    );
                }

                $extractedValue = urldecode($rawValue);
            }
        }

        // Cross-check with PHP query array to detect array injection
        $parsedQuery = $request->query();
        if (isset($parsedQuery['data.id']) && is_array($parsedQuery['data.id'])) {
            $this->logWarning('mercadopago.webhook.rejected_polluted', 'POLLUTED_QUERY_PARAMETER', $xRequestId, null, $request->ip());
            throw new MercadoPagoPayloadValidationException('POLLUTED_QUERY_PARAMETER', $xRequestId, 'Array query parameter rejected');
        }
        if (isset($parsedQuery['data_id']) && is_array($parsedQuery['data_id'])) {
            $this->logWarning('mercadopago.webhook.rejected_polluted', 'POLLUTED_QUERY_PARAMETER', $xRequestId, null, $request->ip());
            throw new MercadoPagoPayloadValidationException('POLLUTED_QUERY_PARAMETER', $xRequestId, 'Array query parameter rejected');
        }

        if ($extractedValue !== null) {
            // V02: Strict format validation: reject leading/trailing/internal whitespace and control chars
            $this->assertValidResourceIdentifierFormat($extractedValue, 'query string', $xRequestId, $request->ip());
        }

        return $extractedValue;
    }

    private function extractBodyDataId(Request $request, ?string $xRequestId): ?string
    {
        $rawContent = $request->getContent();
        if ($rawContent === '' || $rawContent === false) {
            return null;
        }

        // L01: Check raw body byte length before invoking json_decode() to prevent memory/CPU exhaustion
        if (strlen($rawContent) > self::MAX_JSON_PAYLOAD_BYTES) {
            $this->logWarning('mercadopago.webhook.rejected_payload_too_large', 'PAYLOAD_TOO_LARGE', $xRequestId, null, $request->ip());

            throw new MercadoPagoPayloadValidationException(
                'PAYLOAD_TOO_LARGE',
                $xRequestId,
                'JSON payload exceeds maximum allowed size'
            );
        }

        // V06: Validate that raw body is a valid JSON object ({...}), not a JSON array ([...]) or scalar
        $decodedObject = json_decode($rawContent, false);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_object($decodedObject)) {
            $this->logWarning('mercadopago.webhook.rejected_invalid_json', 'INVALID_JSON_BODY', $xRequestId, null, $request->ip());

            throw new MercadoPagoPayloadValidationException(
                'INVALID_JSON_BODY',
                $xRequestId,
                'Request body must be a valid JSON object'
            );
        }

        // F01: Structural duplicate key inspection across scopes without regex
        $this->assertNoDuplicateJsonKeys($rawContent, $xRequestId, $request->ip());

        // V06: Explicit type validation on data object and data.id
        if (property_exists($decodedObject, 'data')) {
            $data = $decodedObject->data;
            if (! is_object($data)) {
                $this->logWarning('mercadopago.webhook.rejected_invalid_structure', 'INVALID_DATA_STRUCTURE', $xRequestId, null, $request->ip());

                throw new MercadoPagoPayloadValidationException(
                    'INVALID_DATA_STRUCTURE',
                    $xRequestId,
                    'Field "data" in request body must be a JSON object'
                );
            }

            if (property_exists($data, 'id')) {
                $idValue = $data->id;
                if (! is_string($idValue) && ! is_int($idValue)) {
                    $this->logWarning('mercadopago.webhook.rejected_invalid_type', 'INVALID_IDENTIFIER_TYPE', $xRequestId, null, $request->ip());

                    throw new MercadoPagoPayloadValidationException(
                        'INVALID_IDENTIFIER_TYPE',
                        $xRequestId,
                        'Field "data.id" must be a scalar string or integer'
                    );
                }

                $bodyId = (string) $idValue;

                // V02: Strict format validation on body identifier
                $this->assertValidResourceIdentifierFormat($bodyId, 'body', $xRequestId, $request->ip());

                return $bodyId;
            }
        }

        // Top-level 'id' is a notification event ID, NOT a payment resource ID (R05/V06)
        return null;
    }

    /**
     * F01: Lightweight structural JSON scanner for duplicate keys across scopes.
     *
     * Validates that no JSON object (at root or nested, including within arrays) contains
     * duplicate keys. Distinguishes strings, escapes, objects, arrays, and scopes without regex.
     *
     * @param string $rawJson
     * @param string|null $xRequestId
     * @param string|null $ip
     *
     * @throws MercadoPagoPayloadValidationException
     */
    private function assertNoDuplicateJsonKeys(string $rawJson, ?string $xRequestId, ?string $ip): void
    {
        // Strip UTF-8 BOM if present
        if (str_starts_with($rawJson, "\xEF\xBB\xBF")) {
            $rawJson = substr($rawJson, 3);
        }

        $len = strlen($rawJson);
        if ($len > self::MAX_JSON_PAYLOAD_BYTES) {
            $this->logWarning('mercadopago.webhook.rejected_payload_too_large', 'PAYLOAD_TOO_LARGE', $xRequestId, null, $ip);

            throw new MercadoPagoPayloadValidationException(
                'PAYLOAD_TOO_LARGE',
                $xRequestId,
                'JSON payload exceeds maximum allowed size'
            );
        }

        $i = 0;
        $steps = 0;
        $started = false;
        $stack = []; // elements: ['type' => 'object'|'array', 'keys' => array<string, bool>, 'state' => string]

        while ($i < $len) {
            if (++$steps > self::MAX_SCANNER_STEPS) {
                $this->logWarning('mercadopago.webhook.rejected_scanner_work_limit', 'INVALID_JSON_BODY', $xRequestId, null, $ip);

                throw new MercadoPagoPayloadValidationException(
                    'INVALID_JSON_BODY',
                    $xRequestId,
                    'JSON scanner work limit exceeded'
                );
            }

            $char = $rawJson[$i];

            // Skip standard JSON whitespace
            if ($char === ' ' || $char === "\t" || $char === "\n" || $char === "\r") {
                $i++;
                continue;
            }

            if (empty($stack)) {
                if ($started) {
                    // Non-whitespace character after root JSON closed
                    $this->logWarning('mercadopago.webhook.rejected_invalid_json', 'INVALID_JSON_BODY', $xRequestId, null, $ip);

                    throw new MercadoPagoPayloadValidationException(
                        'INVALID_JSON_BODY',
                        $xRequestId,
                        'Trailing non-whitespace characters after root JSON object'
                    );
                }

                if ($char !== '{') {
                    $this->logWarning('mercadopago.webhook.rejected_invalid_json', 'INVALID_JSON_BODY', $xRequestId, null, $ip);

                    throw new MercadoPagoPayloadValidationException(
                        'INVALID_JSON_BODY',
                        $xRequestId,
                        'Root JSON element must be an object'
                    );
                }

                $started = true;
                $stack[] = [
                    'type'  => 'object',
                    'keys'  => [],
                    'state' => 'START_OR_KEY',
                ];
                $i++;
                continue;
            }

            $depth = count($stack);
            if ($depth > self::MAX_JSON_DEPTH) {
                $this->logWarning('mercadopago.webhook.rejected_depth_limit', 'INVALID_JSON_BODY', $xRequestId, null, $ip);

                throw new MercadoPagoPayloadValidationException(
                    'INVALID_JSON_BODY',
                    $xRequestId,
                    'JSON nesting depth limit exceeded'
                );
            }

            $topIdx = $depth - 1;

            if ($stack[$topIdx]['type'] === 'object') {
                $state = $stack[$topIdx]['state'];

                if ($state === 'START_OR_KEY') {
                    if ($char === '}') {
                        array_pop($stack);
                        if (! empty($stack)) {
                            $stack[count($stack) - 1]['state'] = 'COMMA_OR_END';
                        }
                        $i++;
                        continue;
                    }

                    if ($char === '"') {
                        $this->handleObjectKey($rawJson, $i, $len, $stack[$topIdx], $xRequestId, $ip);
                        continue;
                    }

                    $this->logWarning('mercadopago.webhook.rejected_invalid_json', 'INVALID_JSON_BODY', $xRequestId, null, $ip);
                    throw new MercadoPagoPayloadValidationException('INVALID_JSON_BODY', $xRequestId, 'Expected string key or object close');
                }

                if ($state === 'KEY') {
                    if ($char === '"') {
                        $this->handleObjectKey($rawJson, $i, $len, $stack[$topIdx], $xRequestId, $ip);
                        continue;
                    }

                    $this->logWarning('mercadopago.webhook.rejected_invalid_json', 'INVALID_JSON_BODY', $xRequestId, null, $ip);
                    throw new MercadoPagoPayloadValidationException('INVALID_JSON_BODY', $xRequestId, 'Expected string key');
                }

                if ($state === 'COLON') {
                    if ($char === ':') {
                        $stack[$topIdx]['state'] = 'VALUE';
                        $i++;
                        continue;
                    }

                    $this->logWarning('mercadopago.webhook.rejected_invalid_json', 'INVALID_JSON_BODY', $xRequestId, null, $ip);
                    throw new MercadoPagoPayloadValidationException('INVALID_JSON_BODY', $xRequestId, 'Expected colon after object key');
                }

                if ($state === 'VALUE') {
                    if ($char === '{') {
                        $stack[] = [
                            'type'  => 'object',
                            'keys'  => [],
                            'state' => 'START_OR_KEY',
                        ];
                        $i++;
                        continue;
                    }

                    if ($char === '[') {
                        $stack[] = [
                            'type'  => 'array',
                            'state' => 'START_OR_VALUE',
                        ];
                        $i++;
                        continue;
                    }

                    if ($char === '"') {
                        $this->scanJsonString($rawJson, $i, $len, $xRequestId, $ip);
                        $stack[$topIdx]['state'] = 'COMMA_OR_END';
                        continue;
                    }

                    $this->scanJsonPrimitive($rawJson, $i, $len, $xRequestId, $ip);
                    $stack[$topIdx]['state'] = 'COMMA_OR_END';
                    continue;
                }

                if ($state === 'COMMA_OR_END') {
                    if ($char === ',') {
                        $stack[$topIdx]['state'] = 'KEY';
                        $i++;
                        continue;
                    }

                    if ($char === '}') {
                        array_pop($stack);
                        if (! empty($stack)) {
                            $stack[count($stack) - 1]['state'] = 'COMMA_OR_END';
                        }
                        $i++;
                        continue;
                    }

                    $this->logWarning('mercadopago.webhook.rejected_invalid_json', 'INVALID_JSON_BODY', $xRequestId, null, $ip);
                    throw new MercadoPagoPayloadValidationException('INVALID_JSON_BODY', $xRequestId, 'Expected comma or closing brace');
                }
            } else {
                // Array scope
                $state = $stack[$topIdx]['state'];

                if ($state === 'START_OR_VALUE') {
                    if ($char === ']') {
                        array_pop($stack);
                        if (! empty($stack)) {
                            $stack[count($stack) - 1]['state'] = 'COMMA_OR_END';
                        }
                        $i++;
                        continue;
                    }

                    if ($char === '{') {
                        $stack[] = [
                            'type'  => 'object',
                            'keys'  => [],
                            'state' => 'START_OR_KEY',
                        ];
                        $i++;
                        continue;
                    }

                    if ($char === '[') {
                        $stack[] = [
                            'type'  => 'array',
                            'state' => 'START_OR_VALUE',
                        ];
                        $i++;
                        continue;
                    }

                    if ($char === '"') {
                        $this->scanJsonString($rawJson, $i, $len, $xRequestId, $ip);
                        $stack[$topIdx]['state'] = 'COMMA_OR_END';
                        continue;
                    }

                    $this->scanJsonPrimitive($rawJson, $i, $len, $xRequestId, $ip);
                    $stack[$topIdx]['state'] = 'COMMA_OR_END';
                    continue;
                }

                if ($state === 'VALUE') {
                    if ($char === '{') {
                        $stack[] = [
                            'type'  => 'object',
                            'keys'  => [],
                            'state' => 'START_OR_KEY',
                        ];
                        $i++;
                        continue;
                    }

                    if ($char === '[') {
                        $stack[] = [
                            'type'  => 'array',
                            'state' => 'START_OR_VALUE',
                        ];
                        $i++;
                        continue;
                    }

                    if ($char === '"') {
                        $this->scanJsonString($rawJson, $i, $len, $xRequestId, $ip);
                        $stack[$topIdx]['state'] = 'COMMA_OR_END';
                        continue;
                    }

                    $this->scanJsonPrimitive($rawJson, $i, $len, $xRequestId, $ip);
                    $stack[$topIdx]['state'] = 'COMMA_OR_END';
                    continue;
                }

                if ($state === 'COMMA_OR_END') {
                    if ($char === ',') {
                        $stack[$topIdx]['state'] = 'VALUE';
                        $i++;
                        continue;
                    }

                    if ($char === ']') {
                        array_pop($stack);
                        if (! empty($stack)) {
                            $stack[count($stack) - 1]['state'] = 'COMMA_OR_END';
                        }
                        $i++;
                        continue;
                    }

                    $this->logWarning('mercadopago.webhook.rejected_invalid_json', 'INVALID_JSON_BODY', $xRequestId, null, $ip);
                    throw new MercadoPagoPayloadValidationException('INVALID_JSON_BODY', $xRequestId, 'Expected comma or closing bracket');
                }
            }
        }

        if (! empty($stack) || ! $started) {
            $this->logWarning('mercadopago.webhook.rejected_invalid_json', 'INVALID_JSON_BODY', $xRequestId, null, $ip);

            throw new MercadoPagoPayloadValidationException(
                'INVALID_JSON_BODY',
                $xRequestId,
                'Unclosed or incomplete JSON structure'
            );
        }
    }

    /**
     * Handles string key parsing, decoding, and duplicate checking within an object scope.
     *
     * @param string $rawJson
     * @param int $i
     * @param int $len
     * @param array<string, mixed> $scope
     * @param string|null $xRequestId
     * @param string|null $ip
     *
     * @throws MercadoPagoPayloadValidationException
     */
    private function handleObjectKey(string $rawJson, int &$i, int $len, array &$scope, ?string $xRequestId, ?string $ip): void
    {
        $rawKeyLiteral = $this->scanJsonString($rawJson, $i, $len, $xRequestId, $ip);
        $decodedKey = json_decode($rawKeyLiteral);

        if (! is_string($decodedKey)) {
            $this->logWarning('mercadopago.webhook.rejected_invalid_json', 'INVALID_JSON_BODY', $xRequestId, null, $ip);

            throw new MercadoPagoPayloadValidationException(
                'INVALID_JSON_BODY',
                $xRequestId,
                'Failed to decode JSON object key'
            );
        }

        if (isset($scope['keys'][$decodedKey])) {
            $this->logWarning('mercadopago.webhook.rejected_duplicate_key', 'DUPLICATE_JSON_KEY', $xRequestId, null, $ip);

            throw new MercadoPagoPayloadValidationException(
                'DUPLICATE_JSON_KEY',
                $xRequestId,
                'Duplicate key "' . $decodedKey . '" detected in JSON object'
            );
        }

        $scope['keys'][$decodedKey] = true;
        $scope['state'] = 'COLON';
    }

    /**
     * Scans a JSON string literal starting at quote index $i up to closing unescaped quote.
     *
     * @param string $json
     * @param int $i
     * @param int $len
     * @param string|null $xRequestId
     * @param string|null $ip
     * @return string Raw JSON string token with surrounding quotes
     *
     * @throws MercadoPagoPayloadValidationException
     */
    private function scanJsonString(string $json, int &$i, int $len, ?string $xRequestId, ?string $ip): string
    {
        $start = $i;
        $i++; // Skip opening quote

        while ($i < $len) {
            $c = $json[$i];

            if ($c === '\\') {
                $i += 2;
                if ($i > $len) {
                    $this->logWarning('mercadopago.webhook.rejected_invalid_json', 'INVALID_JSON_BODY', $xRequestId, null, $ip);
                    throw new MercadoPagoPayloadValidationException('INVALID_JSON_BODY', $xRequestId, 'Unterminated escape in JSON string');
                }
                continue;
            }

            if ($c === '"') {
                $i++; // Skip closing quote
                return substr($json, $start, $i - $start);
            }

            $i++;
        }

        $this->logWarning('mercadopago.webhook.rejected_invalid_json', 'INVALID_JSON_BODY', $xRequestId, null, $ip);
        throw new MercadoPagoPayloadValidationException('INVALID_JSON_BODY', $xRequestId, 'Unterminated JSON string');
    }

    /**
     * Scans a JSON primitive (number, boolean, null) up to the next delimiter or whitespace.
     *
     * @param string $json
     * @param int $i
     * @param int $len
     * @param string|null $xRequestId
     * @param string|null $ip
     *
     * @throws MercadoPagoPayloadValidationException
     */
    private function scanJsonPrimitive(string $json, int &$i, int $len, ?string $xRequestId, ?string $ip): void
    {
        $start = $i;
        while ($i < $len) {
            $c = $json[$i];
            if ($c === ',' || $c === '}' || $c === ']' || $c === ' ' || $c === "\t" || $c === "\n" || $c === "\r") {
                break;
            }
            $i++;
        }

        if ($i === $start) {
            $this->logWarning('mercadopago.webhook.rejected_invalid_json', 'INVALID_JSON_BODY', $xRequestId, null, $ip);
            throw new MercadoPagoPayloadValidationException('INVALID_JSON_BODY', $xRequestId, 'Expected primitive JSON value');
        }
    }

    private function assertValidResourceIdentifierFormat(string $id, string $source, ?string $xRequestId, ?string $ip): void
    {
        // V02: Reject empty string or only whitespace
        if (trim($id) === '') {
            $this->logWarning('mercadopago.webhook.rejected_empty_id', 'EMPTY_RESOURCE_IDENTIFIER', $xRequestId, null, $ip);

            throw new MercadoPagoPayloadValidationException(
                'EMPTY_RESOURCE_IDENTIFIER',
                $xRequestId,
                "Resource identifier in {$source} cannot be empty or contain only whitespace"
            );
        }

        // V02: Reject any identifier that changes when trimmed (leading or trailing whitespace)
        if (trim($id) !== $id) {
            $this->logWarning('mercadopago.webhook.rejected_whitespace', 'WHITESPACE_IN_IDENTIFIER', $xRequestId, null, $ip);

            throw new MercadoPagoPayloadValidationException(
                'WHITESPACE_IN_IDENTIFIER',
                $xRequestId,
                "Resource identifier in {$source} contains forbidden leading or trailing whitespace"
            );
        }

        // V02: Reject internal whitespace or control characters
        if (preg_match('/[\s\x00-\x1F\x7F]/', $id)) {
            $this->logWarning('mercadopago.webhook.rejected_control_chars', 'INVALID_IDENTIFIER_FORMAT', $xRequestId, null, $ip);

            throw new MercadoPagoPayloadValidationException(
                'INVALID_IDENTIFIER_FORMAT',
                $xRequestId,
                "Resource identifier in {$source} contains whitespace or control characters"
            );
        }
    }

    private function resolveSecret(Request $request): string
    {
        $secret = $this->useOverrides ? $this->secretOverride : (string) config('mercadopago.webhook_secret');
        if ($secret === null || trim($secret) === '') {
            $this->logCritical('mercadopago.webhook.config_missing', 'CONFIG_MISSING', $request->header('x-request-id'), $request->ip());

            throw new MercadoPagoWebhookConfigException('MercadoPago webhook secret is not configured');
        }

        return $secret;
    }

    private function resolveTolerance(Request $request): ?int
    {
        $tolerance = $this->useOverrides ? $this->toleranceOverride : config('mercadopago.webhook_tolerance');

        if ($tolerance === null || $tolerance === '') {
            return null;
        }

        if (is_int($tolerance)) {
            if ($tolerance < 0) {
                $this->logCritical('mercadopago.webhook.config_invalid', 'MALFORMED_TOLERANCE', $request->header('x-request-id'), $request->ip());
                throw new MercadoPagoWebhookConfigException('MercadoPago webhook tolerance must be a non-negative integer or null');
            }

            return $tolerance;
        }

        if (is_string($tolerance) && ctype_digit(trim($tolerance))) {
            return (int) trim($tolerance);
        }

        // R09: Reject malformed tolerance values (floats, negative strings, booleans, arrays)
        $this->logCritical('mercadopago.webhook.config_invalid', 'MALFORMED_TOLERANCE', $request->header('x-request-id'), $request->ip());
        throw new MercadoPagoWebhookConfigException('MercadoPago webhook tolerance configuration is malformed');
    }

    private function sanitizeLogIdentifier(?string $id, int $maxLength = 64): ?string
    {
        if ($id === null) {
            return null;
        }

        $clean = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $id);
        if ($clean === '' || $clean === null) {
            return '[INVALID_CHARS]';
        }

        return substr($clean, 0, $maxLength);
    }

    private function logWarning(string $event, string $failureReason, ?string $requestId, ?string $resourceId, ?string $ip): void
    {
        $context = [
            'event'          => $event,
            'gateway'        => 'mercadopago',
            'request_id'     => $this->sanitizeLogIdentifier($requestId),
            'failure_reason' => $this->sanitizeLogIdentifier($failureReason, 32),
            'resource_id'    => $this->sanitizeLogIdentifier($resourceId),
            'ip'             => $this->sanitizeLogIdentifier($ip, 45),
        ];

        if ($this->logger !== null) {
            $this->logger->warning($event, $context);
        } else {
            Log::warning($event, $context);
        }
    }

    private function logCritical(string $event, string $failureReason, ?string $requestId, ?string $ip): void
    {
        $context = [
            'event'          => $event,
            'gateway'        => 'mercadopago',
            'request_id'     => $this->sanitizeLogIdentifier($requestId),
            'failure_reason' => $this->sanitizeLogIdentifier($failureReason, 32),
            'ip'             => $this->sanitizeLogIdentifier($ip, 45),
        ];

        if ($this->logger !== null) {
            $this->logger->critical($event, $context);
        } else {
            Log::critical($event, $context);
        }
    }
}
