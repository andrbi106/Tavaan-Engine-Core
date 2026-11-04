<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — Engine Base Class (والد همهٔ موتورها)
 *
 * این کلاس پایهٔ همهٔ موتورهای تَوان است. هر موتور فرزند باید از این کلاس ارث ببرد
 * و متدهای register() و boot() را (در صورت نیاز) override کند.
 *
 * کلاس Engine سه درگاه ارتباطی را در اختیار موتورهای فرزند قرار می‌دهد:
 *
 *   ۱. Container (درگاه ۱ — pull-based DI):
 *      - متد make(): resolve سرویس از کانتینر
 *      - متد container(): دسترسی مستقیم به ContainerInterface
 *
 *   ۲. Events (درگاه ۲ — pub/sub):
 *      - متد dispatch(): انتشار رویداد
 *      - متد listen(): ثبت شنوندهٔ رویداد
 *      - متد events(): دسترسی مستقیم به DispatcherInterface
 *
 *   ۳. EngineBus (درگاه ۳ — point-to-point RPC):
 *      - متد call(): فراخوانی capability از موتور دیگر
 *      - متد offer(): ثبت capability برای ارائه به سایر موتورها
 *      - متد bus(): دسترسی مستقیم به EngineBusInterface
 *
 * چرخهٔ عمر:
 *   register() → boot() → (runtime) → shutdown()
 *
 * در register() فقط bindingهای سبک ثبت می‌شوند.
 * در boot() راه‌اندازی سنگین انجام می‌شود (پس از ثبت همهٔ موتورها).
 */

namespace Tavaan\EngineCore;

use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Events\DispatcherInterface;
use Tavaan\EngineCore\Contracts\EngineBusInterface;
use Tavaan\EngineCore\Contracts\EngineInterface;
use Tavaan\EngineCore\Contracts\EngineManifestInterface;

abstract class Engine implements EngineInterface
{
    /** آیا boot شده؟ */
    protected bool $booted = false;

    /** کانتینر DI (درگاه ۱) — از Tavaan-Core تأمین می‌شود */
    protected ?ContainerInterface $container = null;

    /** Event Dispatcher (درگاه ۲) — از Tavaan-Core تأمین می‌شود */
    protected ?DispatcherInterface $events = null;

    /** EngineBus (درگاه ۳) — در این مخزن پیاده‌سازی شده */
    protected ?EngineBusInterface $bus = null;

    /** Context اشتراکی بین موتورها */
    protected ?EngineContext $context = null;

    /** پیکربندی موتور (از engine.json یا config merge) */
    protected array $config = [];

    public function __construct(
        protected readonly EngineManifestInterface $manifest
    ) {
    }

    // ─── Identity ────────────────────────────────────────────────────────────

    public function id(): string
    {
        return $this->manifest->id();
    }

    public function version(): string
    {
        return $this->manifest->version();
    }

    public function providers(): array
    {
        return $this->manifest->providers();
    }

    public function dependsOn(): array
    {
        return $this->manifest->dependsOn();
    }

    // ─── Gateway Injection ───────────────────────────────────────────────────
    // این متدها توسط EngineRegistry هنگام bootAll فراخوانی می‌شوند.

    public function setContainer(ContainerInterface $container): static
    {
        $this->container = $container;
        return $this;
    }

    public function setEvents(DispatcherInterface $events): static
    {
        $this->events = $events;
        return $this;
    }

    public function setBus(EngineBusInterface $bus): static
    {
        $this->bus = $bus;
        return $this;
    }

    public function setContext(EngineContext $context): static
    {
        $this->context = $context;
        return $this;
    }

    // ─── Gateway Access ──────────────────────────────────────────────────────

    /** دسترسی به کانتینر (درگاه ۱) */
    public function container(): ?ContainerInterface
    {
        return $this->container;
    }

    /** دسترسی به Event Dispatcher (درگاه ۲) */
    public function events(): ?DispatcherInterface
    {
        return $this->events;
    }

    /** دسترسی به EngineBus (درگاه ۳) */
    public function bus(): ?EngineBusInterface
    {
        return $this->bus;
    }

    /** دسترسی به Context اشتراکی */
    public function context(): ?EngineContext
    {
        return $this->context;
    }

    // ─── Lifecycle ───────────────────────────────────────────────────────────

    /**
     * Stage ۱ — ثبت bindingها در Container.
     *
     * پیاده‌سازی پیش‌فرض: Service Providerهای مانیفست را ثبت می‌کند.
     * زیرکلاس‌ها می‌توانند برای ثبت binding اضافی override کنند.
     */
    public function register(ContainerInterface $container): void
    {
        $this->container = $container;

        // ثبت Service Providerهای تعریف‌شده در manifest
        // از reflection برای سازگاری با constructorهای مختلف استفاده می‌شود
        foreach ($this->manifest->providers() as $providerClass) {
            if (!class_exists($providerClass)) {
                continue;
            }

            $provider = $this->instantiateProvider($providerClass, $container);

            if (method_exists($provider, 'register')) {
                $provider->register();
            }

            // ثبت provider در container برای boot بعدی
            $container->instance($providerClass, $provider);
        }
    }

    /**
     * Stage ۲ — راه‌اندازی کامل موتور.
     *
     * پیاده‌سازی پیش‌فرض: Service Providerهای ثبت‌شده را boot می‌کند.
     * زیرکلاس‌ها باید parent::boot() را در انتها فراخوانی کنند.
     */
    public function boot(): void
    {
        if ($this->container !== null) {
            foreach ($this->manifest->providers() as $providerClass) {
                if ($this->container->has($providerClass)) {
                    $provider = $this->container->make($providerClass);
                    if (method_exists($provider, 'boot')) {
                        $provider->boot();
                    }
                }
            }
        }

        $this->booted = true;
    }

    /**
     * Instantiate یک ServiceProvider با constructor signature دلخواه.
     *
     * از reflection برای تشخیص پارامترهای constructor استفاده می‌کند:
     * - اگر پارامتر ContainerInterface باشد → container پاس داده می‌شود
     * - اگر پارامتر کلاس قابل resolve باشد → از container resolve می‌شود
     * - در غیر این صورت → default value یا null
     *
     * @param string $providerClass
     * @param ContainerInterface $container
     * @return object
     */
    private function instantiateProvider(string $providerClass, ContainerInterface $container): object
    {
        $reflection = new \ReflectionClass($providerClass);
        $constructor = $reflection->getConstructor();

        // اگر constructor ندارد — بدون آرگومان
        if ($constructor === null) {
            return new $providerClass();
        }

        $args = [];
        foreach ($constructor->getParameters() as $param) {
            $type = $param->getType();

            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $typeName = $type->getName();

                // اگر پارامتر ContainerInterface است → container پاس بده
                if ($typeName === ContainerInterface::class
                    || $typeName === 'Tavaan\\Core\\Contracts\\Container\\ContainerInterface'
                    || is_subclass_of($typeName, ContainerInterface::class)) {
                    $args[] = $container;
                    continue;
                }

                // اگر کلاس در container موجود است → resolve کن
                if (class_exists($typeName) && $container->has($typeName)) {
                    try {
                        $args[] = $container->make($typeName);
                        continue;
                    } catch (\Throwable) {
                        // resolve ناموفق — به default برو
                    }
                }
            }

            // default value یا null
            $args[] = $param->isDefaultValueAvailable()
                ? $param->getDefaultValue()
                : null;
        }

        return $reflection->newInstanceArgs($args);
    }

    public function isBooted(): bool
    {
        return $this->booted;
    }

    /**
     * خاموش‌سازی موتور.
     *
     * پیاده‌سازی پیش‌فرض: no-op. زیرکلاس‌ها برای cleanup منابع override می‌کنند.
     */
    public function shutdown(): void
    {
        // no-op default
    }

    // ─── Convenience: درگاه ۱ (Container — pull-based DI) ─────────────────────

    /**
     * Resolve یک سرویس از Container.
     *
     * @param  string $abstract   شناسهٔ سرویس (interface یا class name)
     * @param  array  $parameters پارامترهای اضافی
     * @return mixed
     */
    protected function make(string $abstract, array $parameters = []): mixed
    {
        if ($this->container === null) {
            throw new \RuntimeException(
                "Container not set for engine '{$this->id()}'. Call register() first."
            );
        }
        return $this->container->make($abstract, $parameters);
    }

    /**
     * بررسی وجود یک binding در Container.
     */
    protected function bound(string $abstract): bool
    {
        return $this->container !== null && $this->container->has($abstract);
    }

    // ─── Convenience: درگاه ۲ (Events — pub/sub) ─────────────────────────────

    /**
     * انتشار یک رویداد (publish).
     *
     * @param  string|object $event   نام رویداد یا شیء رویداد
     * @param  array         $payload دادهٔ همراه رویداد
     * @return mixed                  خروجی dispatcher
     */
    protected function dispatch(string|object $event, array $payload = []): mixed
    {
        if ($this->events === null) {
            return null;
        }
        return $this->events->dispatch($event, $payload);
    }

    /**
     * ثبت یک شنوندهٔ رویداد (subscribe).
     *
     * @param  string                $event    نام رویداد
     * @param  callable|string|array $listener شنونده
     */
    protected function listen(string $event, callable|string|array $listener): void
    {
        if ($this->events !== null) {
            $this->events->listen($event, $listener);
        }
    }

    // ─── Convenience: درگاه ۳ (EngineBus — point-to-point RPC) ───────────────

    /**
     * فراخوانی یک capability از موتور دیگر (RPC).
     *
     * @param  string $engineId    شناسهٔ موتور هدف
     * @param  string $capability  نام قابلیت
     * @param  array  $input       ورودی برای handler
     * @return mixed
     */
    protected function call(string $engineId, string $capability, array $input = []): mixed
    {
        if ($this->bus === null) {
            throw new \RuntimeException(
                "EngineBus not set for engine '{$this->id()}'. Call setBus() first."
            );
        }
        return $this->bus->call($engineId, $capability, $input);
    }

    /**
     * ثبت یک capability برای ارائه به سایر موتورها.
     *
     * @param  string   $capability نام قابلیت
     * @param  callable $handler    تابع پردازشگر
     */
    protected function offer(string $capability, callable $handler): void
    {
        if ($this->bus !== null) {
            $this->bus->offer($this->id(), $capability, $handler);
        }
    }

    /**
     * بررسی وجود یک capability در یک موتور.
     */
    protected function hasCapability(string $engineId, string $capability): bool
    {
        return $this->bus !== null && $this->bus->has($engineId, $capability);
    }

    // ─── Configuration ───────────────────────────────────────────────────────

    /**
     * دسترسی به پیکربندی موتور (با dot notation).
     *
     * @param  string|null $key     کلید (مثلاً «auth.guards.session»)
     * @param  mixed       $default مقدار پیش‌فرض
     * @return mixed
     */
    public function config(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->config;
        }

        $keys = \explode('.', $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (!\is_array($value) || !\array_key_exists($k, $value)) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * تنظیم پیکربندی موتور.
     */
    public function setConfig(array $config): static
    {
        $this->config = $config;
        return $this;
    }

    // ─── Context ─────────────────────────────────────────────────────────────

    /**
     * دسترسی به یک مقدار در Context اشتراکی.
     */
    protected function contextGet(string $key, mixed $default = null): mixed
    {
        return $this->context?->get($key, $default) ?? $default;
    }

    /**
     * تنظیم یک مقدار در Context اشتراکی.
     */
    protected function contextSet(string $key, mixed $value): static
    {
        $this->context?->set($key, $value);
        return $this;
    }
}
