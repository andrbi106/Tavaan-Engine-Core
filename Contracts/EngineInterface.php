<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — Engine Interface
 *
 * تعریف قراردادیِ یک «موتور» در فریمورک تَوان. هر موتور یک واحد خودمختار است که:
 *  - از کلاس پایهٔ Engine (در این مخزن) ارث می‌برد
 *  - یک namespace `Tavaan\Engine\<name>\*` دارد
 *  - در مسیر `engines/<name>/src/` قرار دارد
 *  - یک `engine.json` (مانیفست) برای معرفی خود دارد
 *  - می‌تواند Service Provider، Facade، Contract و Exception مخصوص خود را ثبت کند
 *  - از طریق سه درگاه Container، Events و EngineBus با سایر موتورها ارتباط برقرار می‌کند
 */

namespace Tavaan\EngineCore\Contracts;

use Tavaan\Core\Contracts\Container\ContainerInterface;

/**
 * یک موتور تَوان.
 *
 * چرخهٔ عمر:
 *   register()  →  boot()  →  (runtime)  →  shutdown()
 *
 * در stage «register»، موتور فقط bindingها را در Container ثبت می‌کند (سنگین‌ترین
 * کارها را به boot() موکول می‌کند تا lazy-loading ممکن بماند).
 */
interface EngineInterface
{
    /**
     * شناسهٔ یکتای موتور (مطابق نام پوشه در engines/<id>/).
     *
     * مثال: «auth»، «cache»، «http»، «database».
     */
    public function id(): string;

    /**
     * نسخهٔ SemVer موتور (از engine.json).
     */
    public function version(): string;

    /**
     * Stage ۱ — ثبتbindingها در Container.
     *
     * در این stage نباید کار سنگین انجام شود (مثل اتصال به DB یا راه‌اندازی
     * socket) زیرا ممکن است موتور در نهایت در درخواست فعلی استفاده نشود.
     *
     * @param ContainerInterface $container کانتینر مرکزی (درگاه ۱: pull-based DI)
     */
    public function register(ContainerInterface $container): void;

    /**
     * Stage ۲ — راه‌اندازی کامل موتور.
     *
     * پس از ثبت همهٔ موتورها، این متد فراخوانی می‌شود تا هر موتور بتواند
     * وابستگی‌هایش به موتورهای دیگر را resolve کند و راه‌اندازی نهایی انجام دهد.
     */
    public function boot(): void;

    /**
     * آیا این موتور قبلاً boot شده؟
     */
    public function isBooted(): bool;

    /**
     * لیست Service Providerهای این موتور.
     *
     * @return list<class-string>  کلاس‌های ServiceProvider که باید register شوند
     */
    public function providers(): array;

    /**
     * لیست وابستگی‌های این موتور به موتورهای دیگر.
     *
     * EngineRegistry از این لیست برای تعیین ترتیب boot (topological sort) استفاده می‌کند.
     *
     * @return list<string>  شناسهٔ موتورهای مورد‌نیاز
     */
    public function dependsOn(): array;

    /**
     * خاموش‌سازی موتور (cleanup منابع).
     *
     * فراخوانی در shutdown اپلیکیشن.
     */
    public function shutdown(): void;
}
