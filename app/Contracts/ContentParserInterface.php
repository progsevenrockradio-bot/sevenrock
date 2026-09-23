<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Interface ContentParserInterface
 *
 * @property ?string $lastError
 */
interface ContentParserInterface
{
    /**
     * Process email content using an AI model to clean, translate, and structure it.
     * En caso de error, debe devolver null y establecer \$this->lastError, sin lanzar excepciones hacia fuera.
     *
     * @param string $subject
     * @param string $body
     * @param string $apiKey
     * @return array|null
     */
    public function parse(string $subject, string $body, string $apiKey): ?array;

    /**
     * Analiza el asunto y el cuerpo del correo para extraer información del remitente.
     *
     * @param string $subject
     * @param string $body
     * @param string $apiKey
     * @return array|null
     */
    public function parseContactInfo(string $subject, string $body, string $apiKey): ?array;

    /**
     * Analiza el asunto y el cuerpo del correo para extraer una lista de efemérides.
     *
     * @param string $subject
     * @param string $body
     * @param string $apiKey
     * @return array|null
     */
    public function parseEfemeridesBatch(string $subject, string $body, string $apiKey): ?array;
}
