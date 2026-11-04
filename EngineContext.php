<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — Engine Context (Context اشتراکی بین موتورها)
 *
 * این کلاس یک فضای ذخیره‌سازی متمرکز برای دادهٔ اشتراکی بین موتورها فراهم می‌کند.
 *
 * کاربردها:
 *   - tenant_id: موتور Tenancy تنظیم می‌کند، موتورهای دیگر می‌خوانند
 *   - user_id: موتور Auth تنظیم می‌کند، موتورهای Audit/Log می‌خوانند
 *   - request_id: موتور Http تنظیم می‌کند، موتورهای Tracing می‌خوانند
 *   - locale: موتور I18n تنظیم می‌کند، موتورهای View/Log می‌خوانند
 *
 * الگو: Shared Context / Ambient Context
 *
 * مثال:
 *   $context->set('tenant_id', 42);
 *   $tenantId = $context->get('tenant_id', 0);
 */

namespace Tavaan\EngineCore;

final class EngineContext
{
    /** @var array<string, mixed> */
    private array $data = [];

    /**
     * ذخیره یک مقدار در context.
     *
     * @param string $key   کلید
     * @param mixed  $value مقدار
     */
    public function set(string $key, mixed $value): static
    {
        $this->data[$key] = $value;
        return $this;
    }

    /**
     * بازیابی یک مقدار از context.
     *
     * @param  string $key     کلید
     * @param  mixed  $default مقدار پیش‌فرض در صورت نبود
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * بررسی وجود یک کلید.
     */
    public function has(string $key): bool
    {
        return \array_key_exists($key, $this->data);
    }

    /**
     * حذف یک کلید.
     */
    public function forget(string $key): static
    {
        unset($this->data[$key]);
        return $this;
    }

    /**
     * همهٔ دادهٔ context را خالی می‌کند.
     */
    public function flush(): static
    {
        $this->data = [];
        return $this;
    }

    /**
     * بازگرداندن همهٔ دادهٔ context به‌صورت آرایه.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->data;
    }

    /**
     * ادغام دادهٔ جدید با دادهٔ موجود.
     *
     * @param array<string, mixed> $data
     */
    public function merge(array $data): static
    {
        $this->data = \array_merge($this->data, $data);
        return $this;
    }
}
