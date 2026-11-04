<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — Engine Registry Interface
 *
 * رجیستری مرکزی موتورهای بارگذاری‌شده.
 *
 * رجیستری یکی از اجزای مدیریت موتورهاست: موتورها را ثبت، کشف و boot می‌کند.
 * ترتیب boot بر اساس وابستگی‌ها (topological sort) تعیین می‌شود.
 */

namespace Tavaan\EngineCore\Contracts;

use Tavaan\Core\Contracts\Container\ContainerInterface;

interface EngineRegistryInterface
{
    /**
     * ثبت یک موتور در رجیستری.
     *
     * @param EngineInterface $engine  نمونهٔ موتور
     * @throws \Tavaan\EngineCore\Exceptions\EngineBootException  اگر موتور تکراری باشد
     */
    public function register(EngineInterface $engine): void;

    /**
     * دریافت یک موتور با شناسه.
     *
     * @param  string $id  شناسهٔ موتور
     * @return EngineInterface|null  نمونهٔ موتور یا null اگر بارگذاری نشده
     */
    public function get(string $id): ?EngineInterface;

    /**
     * آیا موتور با شناسهٔ مشخص در رجیستری موجود است؟
     */
    public function has(string $id): bool;

    /**
     * لیست همهٔ موتورهای ثبت‌شده.
     *
     * @return list<EngineInterface>
     */
    public function all(): array;

    /**
     * لیست شناسهٔ همهٔ موتورهای ثبت‌شده.
     *
     * @return list<string>
     */
    public function ids(): array;

    /**
     * boot کردن همهٔ موتورهای ثبت‌شده با رعایت ترتیب وابستگیها (topological).
     *
     * مراحل:
     *   ۱) محاسبهٔ ترتیب topological بر اساس dependsOn()
     *   ۲) stage register — همهٔ موتورها bindingهایشان را ثبت می‌کنند
     *   ۳) stage boot — هر موتور راه‌اندازی نهایی می‌شود
     *
     * @param  ContainerInterface $container  کانتینر برای پاس دادن به register()
     * @return int  تعداد موتورهای boot‌شده
     */
    public function bootAll(ContainerInterface $container): int;

    /**
     * shutdown همهٔ موتورها (به‌ترتیب معکوس boot).
     */
    public function shutdownAll(): void;
}
