# Tavaan-Engine-Core

> **موتور والد همهٔ موتورهای تَوان** — کلاس پایهٔ Engine، درگاه ارتباط EngineBus، و مدیریت موتورها.

## نقش

این مخزن **والد همهٔ موتورها** است. هر موتور تَوان (Auth، Cache، Http، …) باید از کلاس `Engine` این مخزن ارث ببرد.

این مخزن **هستهٔ فریمورک نیست** — هستهٔ فریمورک (Application، Bootstrap، Autoloading، Container، Events، Contracts عمومی) در مخزن [Tavaan-Core](https://github.com/andrbi106/Tavaan-Core) قرار دارد.

این مخزن فقط شامل:
- کلاس پایهٔ `Engine` (که همهٔ موتورها ارث می‌برند)
- درگاه ارتباط `EngineBus` (point-to-point RPC)
- مدیریت موتورها (Registry، Loader، Manifest، Context، ServiceProvider)
- قراردادها و خطاهای مخصوص موتورها

## وابستگی

```
Tavaan-Core (هسته فریمورک)
    ↑
    | Engine-Core از Container, Events, ServiceProvider, TavaanException استفاده می‌کند
    |
Tavaan-Engine-Core (این مخزن — والد موتورها)
    ↑
    | هر موتور از Engine base class ارث می‌برد
    |
52 موتور فرزند (Auth, Cache, Http, Database, ...)
```

## سه درگاه ارتباط بین موتورها

| درگاه | پیاده‌سازی | الگو | کاربرد |
|---|---|---|---|
| **Container** | Tavaan-Core | pull-based DI | موتور A سرویس موتور B را resolve می‌کند: `$this->make(CacheInterface::class)` |
| **Events** | Tavaan-Core | pub/sub | موتور A رویداد انتشار می‌دهد: `$this->dispatch('user.registered', $data)` |
| **EngineBus** | **این مخزن** | point-to-point RPC | موتور A یک capability از موتور B فراخوانی می‌کند: `$this->call('auth', 'issue-token', $input)` |

## ساختار src/

```
src/
├── Contracts/                         (قراردادهای مخصوص موتور)
│   ├── EngineInterface.php            تعریف یک موتور (چرخهٔ عمر: register→boot→shutdown)
│   ├── EngineRegistryInterface.php    رجیستری و کشف موتورها
│   ├── EngineManifestInterface.php    مانیفست موتور (توصیف declarative)
│   └── EngineBusInterface.php         باس point-to-point RPC
│
├── Exceptions/                        (خطاهای مخصوص موتور)
│   ├── EngineBootException.php        خطای boot: duplicate, circular dependency, missing dependency
│   ├── EngineCapabilityException.php  خطای EngineBus: missing/duplicate capability
│   └── EngineNotInstalledException.php  خطای عدم وجود موتور
│
├── Engine.php                         کلاس پایه — همهٔ موتورها ارث می‌برند
├── EngineBus.php                      پیاده‌سازی درگاه ۳ (RPC)
├── EngineContext.php                  data اشتراکی بین موتورها
├── EngineLoader.php                   کشف موتورها از filesystem
├── EngineManifest.php                 value object مانیفست
├── EngineRegistry.php                 رجیستری + topological boot + تزریق درگاه‌ها
└── EngineServiceProvider.php          کلاس پایه برای Providerهای موتورها
```

## کلاس Engine — convenience methods

کلاس `Engine` سه درگاه ارتباط را با متدهای کوتاه در اختیار موتورهای فرزند قرار می‌دهد:

### درگاه ۱: Container (pull-based DI)

```php
// داخل یک موتور فرزند:
$cache = $this->make(CacheInterface::class);    // resolve سرویس
$exists = $this->bound(CacheInterface::class);   // بررسی binding
```

### درگاه ۲: Events (pub/sub)

```php
$this->dispatch('user.registered', ['user_id' => 42]);  // انتشار
$this->listen('user.registered', function($payload) {    // شنونده
    // ...
});
```

### درگاه ۳: EngineBus (point-to-point RPC)

```php
// ثبت یک capability برای ارائه به سایر موتورها:
$this->offer('issue-token', function(array $input) {
    return $this->generateToken($input['user_id']);
});

// فراخوانی capability از موتور دیگر:
$token = $this->call('auth', 'issue-token', ['user_id' => 42]);
```

## چرخهٔ عمر موتور

```
register()  →  boot()  →  (runtime)  →  shutdown()
   │              │                          │
   │              │                          └─ cleanup منابع
   │              └─ راه‌اندازی سنگین (پس از ثبت همهٔ موتورها)
   └─ ثبت bindingهای سبک در Container (lazy)
```

`EngineRegistry::bootAll()` این مراحل را مدیریت می‌کند:
۱. بررسی وابستگی‌ها
۲. مرتب‌سازی topological (بر اساس `dependsOn()`)
۳. تزریق درگاه‌ها (Container, Events, EngineBus, Context) به هر موتور
۴. stage register — همه bindingها را ثبت می‌کنند
۵. stage boot — همه راه‌اندازی نهایی می‌کنند

## نمونه موتور فرزند

```php
namespace Tavaan\Engine\Auth;

use Tavaan\EngineCore\Engine;
use Tavaan\EngineCore\Contracts\EngineManifestInterface;

class AuthEngine extends Engine
{
    public function register(ContainerInterface $container): void
    {
        parent::register($container);

        // ثبت bindingهای اختصاصی
        $container->singleton(GuardInterface::class, fn() => new SessionGuard($this->config()));

        // ارائه capability به سایر موتورها
        $this->offer('check', fn(array $input) => $this->checkCredentials($input));
        $this->offer('issue-token', fn(array $input) => $this->generateToken($input['user_id']));
    }

    public function boot(): void
    {
        // راه‌اندازی نهایی
        $this->listen('user.registered', function($payload) {
            $this->dispatch('auth.user_registered', $payload);
        });

        parent::boot();
    }
}
```

## فایل‌های این مخزن

این مخزن شامل **۱۴ فایل PHP + ۲ فایل پیکربندی** است:

| فایل | namespace | نقش |
|---|---|---|
| `Contracts/EngineInterface.php` | `Tavaan\EngineCore\Contracts` | تعریف یک موتور |
| `Contracts/EngineRegistryInterface.php` | `Tavaan\EngineCore\Contracts` | رجیستری موتورها |
| `Contracts/EngineManifestInterface.php` | `Tavaan\EngineCore\Contracts` | مانیفست موتور |
| `Contracts/EngineBusInterface.php` | `Tavaan\EngineCore\Contracts` | باس RPC |
| `Exceptions/EngineBootException.php` | `Tavaan\EngineCore\Exceptions` | خطای boot |
| `Exceptions/EngineCapabilityException.php` | `Tavaan\EngineCore\Exceptions` | خطای capability |
| `Exceptions/EngineNotInstalledException.php` | `Tavaan\EngineCore\Exceptions` | خطای عدم نصب |
| `Engine.php` | `Tavaan\EngineCore` | کلاس پایه |
| `EngineBus.php` | `Tavaan\EngineCore` | درگاه ۳ (RPC) |
| `EngineContext.php` | `Tavaan\EngineCore` | data اشتراکی |
| `EngineLoader.php` | `Tavaan\EngineCore` | کشف filesystem |
| `EngineManifest.php` | `Tavaan\EngineCore` | value object |
| `EngineRegistry.php` | `Tavaan\EngineCore` | topological boot |
| `EngineServiceProvider.php` | `Tavaan\EngineCore` | base provider |

## این مخزن چیست و چیست نیست

| ✅ هست | ❌ نیست |
|---|---|
| کلاس پایهٔ Engine | Application / Bootstrap / Autoloading |
| EngineBus (RPC) | Container.php (در Tavaan-Core) |
| EngineRegistry / Loader / Manifest | EventDispatcher.php (در Tavaan-Core) |
| قراردادهای مخصوص موتور | Contracts عمومی (Container, Events, Support) |
| خطاهای مخصوص موتور | Support / Facade / Context / Error |
| EngineContext | engine.json (فقط موتورهای فرزند) |
| EngineServiceProvider | bootstrap.php |

## روابط با سایر مخازن

| مخزن | نقش |
|---|---|
| **`andrbi106/Tavaan-Engine-Core`** (این مخزن) | والد موتورها — کلاس پایه + درگاه RPC + مدیریت |
| `andrbi106/Tavaan-Core` | هسته فریمورک (Application, Container, Events, Contracts, …) |
| `andrbi106/Tavaan-Engine-*` (۵۲ مخزن) | موتورهای فرزند — هرکدام از Engine ارث می‌برند |
| `Derogaramin/Tavaan-Framework` | فریمورک مادر (monorepo) |

## مجوز

MIT — هم‌راستا با فریمورک مادر تَوان.
