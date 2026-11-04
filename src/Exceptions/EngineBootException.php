<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — Engine Boot Exception
 *
 * خطای مربوط به راه‌اندازی موتورها: تکراری بودن شناسه، چرخهٔ وابستگی،
 * شکست در boot.
 */

namespace Tavaan\EngineCore\Exceptions;

use Tavaan\Core\Exceptions\TavaanException;

/**
 * @api 1.0
 */
final class EngineBootException extends TavaanException
{
    /** @param array<string, mixed> $context */
    public function __construct(
        string $messageId = 'engine.boot_failed',
        array $context = [],
        ?string $hint = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($messageId, $context, $hint, null, 0, $previous);
    }

    /** خطای تکراری بودن شناسهٔ موتور هنگام ثبت در رجیستری */
    public static function forDuplicate(string $engineId): self
    {
        return new self(
            'engine.duplicate_id',
            ['engine' => $engineId],
            "موتور '{$engineId}' قبلاً در رجیستری ثبت شده است."
        );
    }

    /** خطای چرخهٔ وابستگی بین موتورها */
    public static function forCircularDependency(string $engineList): self
    {
        return new self(
            'engine.circular_dependency',
            ['engines' => $engineList],
            'چرخهٔ وابستگی بین موتورها شناسایی شد. وابستگی‌های متقابل را بررسی کنید: ' . $engineList
        );
    }

    /** خطای عدم رفع وابستگی — موتور مورد‌نیاز موجود نیست */
    public static function forMissingDependency(string $engineId, string $dependencyId): self
    {
        return new self(
            'engine.missing_dependency',
            ['engine' => $engineId, 'dependency' => $dependencyId],
            "موتور '{$engineId}' به موتور '{$dependencyId}' وابسته است اما后者 در رجیستری موجود نیست."
        );
    }
}
