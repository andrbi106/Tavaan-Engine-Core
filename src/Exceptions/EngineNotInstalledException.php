<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — Engine Not Installed Exception
 *
 * خطای مربوط به عدم وجود یک موتور در رجیستری یا filesystem.
 */

namespace Tavaan\EngineCore\Exceptions;

use Tavaan\Core\Exceptions\TavaanException;

/**
 * @api 1.0
 */
final class EngineNotInstalledException extends TavaanException
{
    /** @param array<string, mixed> $context */
    public function __construct(
        string $messageId = 'engine.not_installed',
        array $context = [],
        ?string $hint = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($messageId, $context, $hint, null, 0, $previous);
    }

    /** خطای عدم وجود موتور با شناسهٔ مشخص در رجیستری */
    public static function forEngine(string $engineId): self
    {
        return new self(
            'engine.not_installed',
            ['engine' => $engineId],
            "موتور '{$engineId}' در رجیستری موجود نیست. آیا در engines/{$engineId}/engine.json ثبت شده؟"
        );
    }

    /** خطای عدم وجود پوشهٔ موتور در filesystem */
    public static function forMissingDirectory(string $engineId, string $expectedPath): self
    {
        return new self(
            'engine.directory_missing',
            ['engine' => $engineId, 'path' => $expectedPath],
            "پوشهٔ موتور '{$engineId}' در مسیر '{$expectedPath}' یافت نشد."
        );
    }

    /** خطای عدم وجود فایل engine.json */
    public static function forMissingManifest(string $engineId, string $expectedFile): self
    {
        return new self(
            'engine.manifest_missing',
            ['engine' => $engineId, 'file' => $expectedFile],
            "فایل مانیفست '{$expectedFile}' برای موتور '{$engineId}' یافت نشد."
        );
    }
}
