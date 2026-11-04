<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — Engine Capability Exception
 *
 * خطای مربوط به درگاه EngineBus: عدم وجود capability، تکراری بودن ثبت.
 */

namespace Tavaan\EngineCore\Exceptions;

use Tavaan\Core\Exceptions\TavaanException;

/**
 * @api 1.0
 */
final class EngineCapabilityException extends TavaanException
{
    /** @param array<string, mixed> $context */
    public function __construct(
        string $messageId = 'engine.capability_missing',
        array $context = [],
        ?string $hint = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($messageId, $context, $hint, null, 0, $previous);
    }

    /** خطای عدم وجود یک capability در یک موتور */
    public static function forMissing(string $engineId, string $capability): self
    {
        return new self(
            'engine.capability_missing',
            ['engine' => $engineId, 'capability' => $capability],
            "موتور '{$engineId}' قابلیت '{$capability}' را ارائه نمی‌دهد. " .
            'برای ثبت، از EngineBus::offer() استفاده کنید.'
        );
    }

    /** خطای تکراری بودن ثبت یک capability */
    public static function forDuplicateOffer(string $engineId, string $capability): self
    {
        return new self(
            'engine.capability_duplicate',
            ['engine' => $engineId, 'capability' => $capability],
            "قابلیت '{$capability}' از موتور '{$engineId}' قبلاً در EngineBus ثبت شده است."
        );
    }
}
