<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — Engine Service Provider (کلاس پایه برای Providerهای موتورها)
 *
 * این کلاس پایهٔ همهٔ Service Providerهای موتورهاست. هر موتور می‌تواند
 * Providerهای خود را بسازد که از این کلاس ارث می‌برند.
 *
 * یک EngineServiceProvider مسئول:
 *   - ثبت bindingها در Container (در stage register)
 *   - راه‌اندازی سرویس‌ها (در stage boot)
 *   - بارگذاری فایل‌های پیکربندی موتور
 *   - ثبت routeها، viewها، langها (در صورت وجود)
 *
 * الگو: Service Provider (from Tavaan-Core) + Engine-specific enhancements
 */

namespace Tavaan\EngineCore;

use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Provider\ServiceProvider;

abstract class EngineServiceProvider extends ServiceProvider
{
    /**
     * شناسهٔ موتور که این Provider به آن تعلق دارد.
     *
     * مثال: 'auth', 'cache', 'http'
     */
    protected string $engineId = '';

    /**
     * مسیر ریشهٔ موتور (engines/<id>).
     */
    protected string $enginePath = '';

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
    }

    /**
     * تنظیم شناسهٔ موتور و مسیر.
     */
    public function forEngine(string $engineId, string $enginePath): static
    {
        $this->engineId = $engineId;
        $this->enginePath = $enginePath;
        return $this;
    }

    /**
     * مسیر به یک فایل داخل موتور.
     *
     * @param  string $relative مسیر نسبی (مثلاً 'config/auth.php')
     * @return string مسیر مطلق
     */
    protected function path(string $relative = ''): string
    {
        return $this->enginePath . ($relative !== '' ? '/' . \ltrim($relative, '/') : '');
    }

    /**
     * بارگذاری فایل پیکربندی موتور و merge با کانتینر.
     *
     * @param string $configFile فایل پیکربندی (نسبی به ریشهٔ موتور)
     * @param string $configKey  کلید پیکربندی در کانتینر
     */
    protected function loadConfig(string $configFile, string $configKey): void
    {
        $fullPath = $this->path($configFile);
        if (!\is_file($fullPath)) {
            return;
        }

        /** @var array<string, mixed>|null $config */
        $config = require $fullPath;
        if (!\is_array($config)) {
            return;
        }

        // merge با config موجود اگر قبلاً ثبت شده
        $existing = $this->container->has('config.' . $configKey)
            ? $this->container->make('config.' . $configKey)
            : [];

        if (\is_array($existing)) {
            $config = \array_merge($existing, $config);
        }

        $this->container->bind('config.' . $configKey, fn () => $config);
    }

    /**
     * بارگذاری فایل helper موتور (در صورت وجود).
     *
     * @param string $helperFile فایل helper (نسبی به ریشهٔ موتور)
     */
    protected function loadHelpers(string $helperFile = 'src/helpers.php'): void
    {
        $fullPath = $this->path($helperFile);
        if (\is_file($fullPath)) {
            require_once $fullPath;
        }
    }

    /**
     * شناسهٔ موتور.
     */
    public function engineId(): string
    {
        return $this->engineId;
    }
}
