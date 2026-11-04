<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — Engine Bus Interface (درگاه ارتباط point-to-point)
 *
 * باس ارتباطی بین موتورها — مکانیزمی صریح برای فراخوانی متقابل.
 *
 * سه مکانیزم ارتباطی بین موتورها در تَوان:
 *   ۱. Container (در Tavaan-Core): pull-based DI — موتور A سرویس موتور B را resolve می‌کند
 *   ۲. Event Dispatcher (در Tavaan-Core): pub/sub async — یک موتور رویداد انتشار می‌دهد
 *   ۳. EngineBus (در این مخزن): درخواست مستقیم sync — point-to-point RPC
 *
 * EngineBus زمانی استفاده می‌شود که یک موتور می‌خواهد یک عملیات مشخص را از
 * موتور دیگری درخواست کند و نتیجهٔ آن را هم‌زمان دریافت کند.
 */

namespace Tavaan\EngineCore\Contracts;

interface EngineBusInterface
{
    /**
     * ثبت یک handler برای یک capability مشخص در یک موتور.
     *
     * موتور «auth» ممکن است capability «issue-token» را ثبت کند:
     *   $bus->offer('auth', 'issue-token', fn(array $input) => $token);
     *
     * @param  string   $engineId    شناسهٔ موتور ارائه‌دهنده
     * @param  string   $capability  نام قابلیت (مثلاً «issue-token»)
     * @param  callable $handler     تابع پردازشگر: (array $input) => mixed
     * @throws \Tavaan\EngineCore\Exceptions\EngineCapabilityException  اگر capability تکراری باشد
     */
    public function offer(string $engineId, string $capability, callable $handler): void;

    /**
     * درخواست اجرای یک capability از یک موتور مشخص.
     *
     * @param  string $engineId    شناسهٔ موتور هدف
     * @param  string $capability  نام قابلیت
     * @param  array  $input       ورودی برای handler
     * @return mixed              خروجی handler
     *
     * @throws \Tavaan\EngineCore\Exceptions\EngineCapabilityException  اگر capability موجود نباشد
     * @throws \Tavaan\EngineCore\Exceptions\EngineNotInstalledException  اگر موتور موجود نباشد
     */
    public function call(string $engineId, string $capability, array $input = []): mixed;

    /** بررسی وجود یک capability در یک موتور. */
    public function has(string $engineId, string $capability): bool;

    /**
     * لیست همهٔ capabilityهای ثبت‌شده.
     *
     * @return array<string, list<string>>  نگاشت engineId → فهرست capabilityها
     */
    public function capabilities(): array;

    /**
     * لیست capabilityهای یک موتور مشخص.
     *
     * @return list<string>
     */
    public function capabilitiesOf(string $engineId): array;
}
