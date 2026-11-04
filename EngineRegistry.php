<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — Engine Registry
 *
 * پیاده‌سازی رجیستری مرکزی موتورها. این کلاس:
 *   - موتورها را ثبت می‌کند
 *   - dependency graph را می‌سازد
 *   - به‌ترتیب topological موتورها را register و boot می‌کند
 *   - در shutdown، موتورها را به‌ترتیب معکوس خاموش می‌کند
 *
 * همچنین درگاه‌های Container، Events و EngineBus را به هر موتور تزریق می‌کند.
 */

namespace Tavaan\EngineCore;

use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Events\DispatcherInterface;
use Tavaan\EngineCore\Contracts\EngineBusInterface;
use Tavaan\EngineCore\Contracts\EngineInterface;
use Tavaan\EngineCore\Contracts\EngineRegistryInterface;
use Tavaan\EngineCore\Exceptions\EngineBootException;

final class EngineRegistry implements EngineRegistryInterface
{
    /** @var array<string, EngineInterface> */
    private array $engines = [];

    /** @var list<string> ترتیب boot (برای shutdown معکوس) */
    private array $bootOrder = [];

    public function __construct(
        private readonly ?EngineBusInterface $bus = null,
        private readonly ?DispatcherInterface $events = null,
        private readonly ?EngineContext $context = null,
    ) {
    }

    public function register(EngineInterface $engine): void
    {
        $id = $engine->id();
        if (isset($this->engines[$id])) {
            throw EngineBootException::forDuplicate($id);
        }
        $this->engines[$id] = $engine;
    }

    public function get(string $id): ?EngineInterface
    {
        return $this->engines[$id] ?? null;
    }

    public function has(string $id): bool
    {
        return isset($this->engines[$id]);
    }

    public function all(): array
    {
        return \array_values($this->engines);
    }

    public function ids(): array
    {
        return \array_keys($this->engines);
    }

    public function bootAll(ContainerInterface $container): int
    {
        // ۱) بررسی وابستگی‌های ضروری
        $this->validateDependencies();

        // ۲) محاسبهٔ ترتیب topological
        $this->bootOrder = $this->topologicalSort();

        // ۳) تزریق درگاه‌ها به هر موتور
        foreach ($this->bootOrder as $id) {
            $engine = $this->engines[$id];
            $engine->setContainer($container);
            if ($this->events !== null) {
                $engine->setEvents($this->events);
            }
            if ($this->bus !== null) {
                $engine->setBus($this->bus);
            }
            if ($this->context !== null) {
                $engine->setContext($this->context);
            }
        }

        // ۴) stage register — همهٔ موتورها ابتدا bindingهایشان را ثبت می‌کنند
        foreach ($this->bootOrder as $id) {
            $this->engines[$id]->register($container);
        }

        // ۵) stage boot — پس از ثبت همه، هر موتور راه‌اندازی نهایی می‌شود
        $count = 0;
        foreach ($this->bootOrder as $id) {
            if (!$this->engines[$id]->isBooted()) {
                $this->engines[$id]->boot();
                $count++;
            }
        }

        return $count;
    }

    public function shutdownAll(): void
    {
        // معکوس ترتیب boot
        foreach (\array_reverse($this->bootOrder) as $id) {
            if (isset($this->engines[$id])) {
                $this->engines[$id]->shutdown();
            }
        }
    }

    /**
     * بررسی اینکه تمام وابستگی‌های ضروری موتورها موجود هستند.
     *
     * @throws EngineBootException  اگر وابستگی موجود نباشد
     */
    private function validateDependencies(): void
    {
        foreach ($this->engines as $id => $engine) {
            foreach ($engine->dependsOn() as $dep) {
                if (!isset($this->engines[$dep])) {
                    throw EngineBootException::forMissingDependency($id, $dep);
                }
            }
        }
    }

    /**
     * مرتب‌سازی topological بر اساس dependsOn() هر موتور.
     *
     * الگوریتم: تکرار انتخاب گره‌های بدون وابستگیِ باقی‌مانده.
     *
     * @return list<string>  شناسهٔ موتورها به‌ترتیب boot
     * @throws EngineBootException  اگر چرخهٔ وابستگی وجود داشته باشد
     */
    private function topologicalSort(): array
    {
        $sorted = [];
        $remaining = [];

        foreach ($this->engines as $id => $engine) {
            // فقط وابستگی‌های داخلی (موجود در رجیستری) را لحاظ می‌کنیم
            $remaining[$id] = \array_filter(
                $engine->dependsOn(),
                fn ($dep) => isset($this->engines[$dep])
            );
        }

        while (!empty($remaining)) {
            $ready = [];
            foreach ($remaining as $id => $deps) {
                if (empty($deps)) {
                    $ready[] = $id;
                }
            }

            if (empty($ready)) {
                // چرخه یا وابستگی حل‌نشدنی
                $cycle = \implode(', ', \array_keys($remaining));
                throw EngineBootException::forCircularDependency($cycle);
            }

            // ترتیب alphabetically برای determinism
            \sort($ready);

            foreach ($ready as $id) {
                $sorted[] = $id;
                unset($remaining[$id]);
                // حذف این موتور از وابستگی‌های باقی‌مانده
                foreach ($remaining as &$deps) {
                    $deps = \array_filter($deps, fn ($d) => $d !== $id);
                }
                unset($deps);
            }
        }

        return $sorted;
    }
}
