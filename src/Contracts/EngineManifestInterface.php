<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — Engine Manifest Interface
 *
 * مانیفست موتور: توصیف declarative یک موتور پیش از instantiation.
 *
 * از EngineInterface جدا است زیرا EngineRegistry ابتدا manifests را بارگذاری
 * می‌کند (بدون instantiate)، dependency graph را می‌سازد، و سپس به‌ترتیب نمونه‌ها
 * را می‌سازد. این جداسازی lazy loading را ممکن می‌کند.
 */

namespace Tavaan\EngineCore\Contracts;

interface EngineManifestInterface
{
    /** شناسهٔ یکتای موتور. */
    public function id(): string;

    /** نام نمایشی موتور. */
    public function name(): string;

    /** نسخهٔ SemVer. */
    public function version(): string;

    /** مسیر مطلق به ریشهٔ موتور (engines/<id>). */
    public function path(): string;

    /** namespace اصلی موتور (مثلاً «Tavaan\Engine\Auth»). */
    public function namespace(): string;

    /** نوع: «engine»، «module»، «kit» یا «plugin». */
    public function type(): string;

    /** اولویت بارگذاری: «P0» (بالا‌ترین)، «P1»، «P2». */
    public function priority(): string;

    /**
     * لیست وابستگی‌ها به موتورهای دیگر.
     *
     * @return list<string>
     */
    public function dependsOn(): array;

    /**
     * لیست Service Providerهای موتور.
     *
     * @return list<class-string>
     */
    public function providers(): array;

    /** آیا این موتور required است؟ (در صورت نبود، اپلیکیشن boot نمی‌شود) */
    public function isRequired(): bool;

    /** فایل مانیفست اصلی (engine.json). */
    public function manifestFile(): string;
}
