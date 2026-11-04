<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — Engine Loader
 *
 * بارگذار موتورها از filesystem. این کلاس پوشهٔ engines/ را اسکن می‌کند،
 * engine.json هر موتور را می‌خواند، و EngineManifest می‌سازد.
 *
 * سپس EngineRegistry این manifests را می‌گیرد و در صورت نیاز نمونه‌های
 * Engine می‌سازد (lazy).
 *
 * الگو: Discovery + Lazy Instantiation
 */

namespace Tavaan\EngineCore;

use Tavaan\EngineCore\Contracts\EngineManifestInterface;
use Tavaan\EngineCore\Exceptions\EngineNotInstalledException;

final class EngineLoader
{
    /**
     * @param string $enginesPath مسیر مطلق به پوشهٔ engines/
     */
    public function __construct(
        private readonly string $enginesPath
    ) {
    }

    /**
     * کشف و بارگذاری همهٔ manifests از پوشهٔ engines/.
     *
     * هر زیرپوشهٔ engines/<id>/ که شامل engine.json باشد، به‌عنوان یک موتور
     * شناسایی می‌شود.
     *
     * @return list<EngineManifestInterface>
     */
    public function discover(): array
    {
        if (!\is_dir($this->enginesPath)) {
            return [];
        }

        $manifests = [];

        foreach (new \DirectoryIterator($this->enginesPath) as $entry) {
            if ($entry->isDot() || !$entry->isDir()) {
                continue;
            }
            $enginePath = $entry->getPathname();
            $manifestFile = $enginePath . '/engine.json';
            if (!\is_file($manifestFile) || !\is_readable($manifestFile)) {
                continue;
            }

            $data = \json_decode((string) \file_get_contents($manifestFile), true);
            if (!\is_array($data)) {
                continue;
            }

            $manifests[] = EngineManifest::fromArray($enginePath, $manifestFile, $data);
        }

        return $manifests;
    }

    /**
     * بارگذاری manifest یک موتور مشخص با شناسه.
     *
     * @param  string $id  شناسهٔ موتور
     * @return EngineManifestInterface
     * @throws EngineNotInstalledException  اگر موتور موجود نباشد
     */
    public function load(string $id): EngineManifestInterface
    {
        foreach ($this->discover() as $manifest) {
            if ($manifest->id() === $id) {
                return $manifest;
            }
        }
        throw EngineNotInstalledException::forEngine($id);
    }

    /**
     * بررسی وجود یک موتور در filesystem.
     */
    public function exists(string $id): bool
    {
        $enginePath = $this->enginesPath . '/' . $id;
        $manifestFile = $enginePath . '/engine.json';
        return \is_dir($enginePath) && \is_file($manifestFile);
    }

    /**
     * مسیر پوشهٔ engines/.
     */
    public function enginesPath(): string
    {
        return $this->enginesPath;
    }
}
