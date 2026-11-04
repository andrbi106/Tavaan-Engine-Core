<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — Engine Manifest (Value Object)
 *
 * ساختار داده‌ای تغییرناپذیر برای نگه‌داری manifest یک موتور.
 * از engine.json هر موتور ساخته می‌شود.
 */

namespace Tavaan\EngineCore;

use Tavaan\EngineCore\Contracts\EngineManifestInterface;

final class EngineManifest implements EngineManifestInterface
{
    /**
     * @param string         $id           شناسهٔ موتور
     * @param string         $name         نام نمایشی
     * @param string         $version      نسخهٔ SemVer
     * @param string         $path         مسیر مطلق ریشهٔ موتور
     * @param string         $namespace    namespace اصلی
     * @param string         $type         نوع: engine/module/kit/plugin
     * @param string         $priority     اولویت: P0/P1/P2
     * @param list<string>   $dependsOn    وابستگی‌ها به موتورهای دیگر
     * @param list<class-string> $providers Service Providerها
     * @param bool           $required     آیا required است؟
     * @param string         $manifestFile مسیر فایل engine.json
     */
    public function __construct(
        private readonly string $id,
        private readonly string $name,
        private readonly string $version,
        private readonly string $path,
        private readonly string $namespace,
        private readonly string $type,
        private readonly string $priority,
        private readonly array $dependsOn,
        private readonly array $providers,
        private readonly bool $required,
        private readonly string $manifestFile,
    ) {
    }

    /**
     * ساخت manifest از روی آرایهٔ پارس‌شدهٔ engine.json.
     *
     * @param  string $basePath     مسیر مطلق ریشهٔ موتور (engines/<id>)
     * @param  string $manifestFile مسیر مطلق فایل engine.json
     * @param  array<string, mixed> $data  محتوای engine.json
     */
    public static function fromArray(string $basePath, string $manifestFile, array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? \basename($basePath)),
            name: (string) ($data['name'] ?? $data['id'] ?? \basename($basePath)),
            version: (string) ($data['version'] ?? '0.0.0'),
            path: $basePath,
            namespace: \rtrim((string) ($data['namespace'] ?? ''), '\\'),
            type: (string) ($data['type'] ?? 'engine'),
            priority: (string) ($data['priority'] ?? 'P1'),
            dependsOn: \array_values(\array_map('strval', $data['depends_on'] ?? $data['dependsOn'] ?? [])),
            providers: \array_values(\array_map('strval', $data['providers'] ?? [])),
            required: (bool) ($data['required'] ?? false),
            manifestFile: $manifestFile,
        );
    }

    public function id(): string { return $this->id; }
    public function name(): string { return $this->name; }
    public function version(): string { return $this->version; }
    public function path(): string { return $this->path; }
    public function namespace(): string { return $this->namespace; }
    public function type(): string { return $this->type; }
    public function priority(): string { return $this->priority; }
    public function dependsOn(): array { return $this->dependsOn; }
    public function providers(): array { return $this->providers; }
    public function isRequired(): bool { return $this->required; }
    public function manifestFile(): string { return $this->manifestFile; }
}
