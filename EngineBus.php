<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — Engine Bus (درگاه ارتباط point-to-point RPC)
 *
 * پیاده‌سازی باس ارتباطی بین موتورها. موتورها capabilityهای
 * خود را در این باس ثبت می‌کنند و سایر موتورها می‌توانند آن‌ها را فراخوانی کنند.
 *
 * این سومین درگاه ارتباط بین موتورهاست (در کنار Container و Events که در Tavaan-Core هستند).
 *
 * الگو: RPC (Remote Procedure Call) با registry متمرکز.
 *
 * مثال:
 *   موتور Auth ثبت می‌کند:
 *     $bus->offer('auth', 'issue-token', fn(array $input) => $token);
 *
 *   موتور Notification فراخوانی می‌کند:
 *     $token = $bus->call('auth', 'issue-token', ['user_id' => 42]);
 */

namespace Tavaan\EngineCore;

use Tavaan\EngineCore\Contracts\EngineBusInterface;
use Tavaan\EngineCore\Contracts\EngineRegistryInterface;
use Tavaan\EngineCore\Exceptions\EngineCapabilityException;
use Tavaan\EngineCore\Exceptions\EngineNotInstalledException;

final class EngineBus implements EngineBusInterface
{
    /**
     * نگاشت: "<engineId>\x00<capability>" => callable
     *
     * از کاراکتر NUL (\x00) به‌عنوان جداکننده استفاده می‌شود تا امکان overlap
     * بین engineId و capability وجود نداشته باشد.
     *
     * @var array<string, callable>
     */
    private array $handlers = [];

    public function __construct(
        private readonly ?EngineRegistryInterface $registry = null
    ) {
    }

    public function offer(string $engineId, string $capability, callable $handler): void
    {
        $key = $this->key($engineId, $capability);
        if (isset($this->handlers[$key])) {
            throw EngineCapabilityException::forDuplicateOffer($engineId, $capability);
        }
        $this->handlers[$key] = $handler;
    }

    public function call(string $engineId, string $capability, array $input = []): mixed
    {
        // اگر رجیستری موجود است، بررسی کنیم که موتور فعلاً نصب شده
        if ($this->registry !== null && !$this->registry->has($engineId)) {
            throw EngineNotInstalledException::forEngine($engineId);
        }

        $key = $this->key($engineId, $capability);
        if (!isset($this->handlers[$key])) {
            throw EngineCapabilityException::forMissing($engineId, $capability);
        }

        return ($this->handlers[$key])($input);
    }

    public function has(string $engineId, string $capability): bool
    {
        return isset($this->handlers[$this->key($engineId, $capability)]);
    }

    public function capabilities(): array
    {
        $result = [];
        foreach (\array_keys($this->handlers) as $key) {
            [$engineId, $capability] = \explode("\x00", $key, 2);
            $result[$engineId][] = $capability;
        }
        return $result;
    }

    public function capabilitiesOf(string $engineId): array
    {
        $result = [];
        foreach (\array_keys($this->handlers) as $key) {
            [$eid, $capability] = \explode("\x00", $key, 2);
            if ($eid === $engineId) {
                $result[] = $capability;
            }
        }
        return $result;
    }

    /**
     * ساخت کلید یکتای داخلی برای نگاشت handler.
     */
    private function key(string $engineId, string $capability): string
    {
        return $engineId . "\x00" . $capability;
    }
}
