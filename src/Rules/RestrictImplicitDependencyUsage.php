<?php

declare(strict_types=1);

namespace Superscript\PHPStanRules\Rules;

use PHPStan\Analyser\ResultCache\ResultCacheMetaExtension;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\ClassNameUsageLocation;
use PHPStan\Rules\RestrictedUsage\RestrictedClassNameUsageExtension;
use PHPStan\Rules\RestrictedUsage\RestrictedUsage;
use Psl\Hash\Algorithm;
use Psl\Type;

use function Psl\Dict\filter;
use function Psl\Dict\from_entries;
use function Psl\Dict\sort_by;
use function Psl\File\read;
use function Psl\Hash\hash;
use function Psl\Iter\any;
use function Psl\Iter\first_key;
use function Psl\Json\decode;
use function Psl\Vec\flat_map;
use function Psl\Vec\keys;
use function Psl\Vec\map;
use function Superscript\PHPStanRules\basepath;

/**
 * @phpstan-type InstalledJson array{packages: list<array{name: string, autoload?: array{psr-4?: array<string, string|list<string>>}, replace?: array<string, string>}>}
 * @phpstan-type ComposerJson array{require?: array<string, string>, require-dev?: array<string, string>, autoload?: array{psr-4?: array<string, string|list<string>>}}
 */
final class RestrictImplicitDependencyUsage implements RestrictedClassNameUsageExtension, ResultCacheMetaExtension
{
    private const array KnownNamespaces = [
        "illuminate/auth" => ['Illuminate\\Auth\\'],
        "illuminate/broadcasting" => [ 'Illuminate\\Broadcasting\\'],
        "illuminate/bus" => [ 'Illuminate\\Bus\\'],
        "illuminate/cache" => [ 'Illuminate\\Cache\\'],
        "illuminate/collections" => [ 'Illuminate\\Collections\\'],
        "illuminate/concurrency" => [ 'Illuminate\\Concurrency\\'],
        "illuminate/conditionable" => [ 'Illuminate\\Conditionable\\'],
        "illuminate/config" => [ 'Illuminate\\Config\\'],
        "illuminate/console" => [ 'Illuminate\\Console\\'],
        "illuminate/container" => [ 'Illuminate\\Container\\'],
        "illuminate/contracts" => [ 'Illuminate\\Contracts\\'],
        "illuminate/cookie" => [ 'Illuminate\\Cookie\\'],
        "illuminate/database" => ['Illuminate\\Database\\'],
        "illuminate/encryption" => ['Illuminate\\Encryption\\'],
        "illuminate/events" => ['Illuminate\\Events\\'],
        "illuminate/filesystem" => ['Illuminate\\Filesystem\\'],
        "illuminate/hashing" => ['Illuminate\\Hashing\\'],
        "illuminate/http" => ['Illuminate\\Http\\'],
        "illuminate/log" => ['Illuminate\\Log\\'],
        "illuminate/macroable" => ['Illuminate\\Macroable\\'],
        "illuminate/mail" => ['Illuminate\\Mail\\'],
        "illuminate/notifications" => ['Illuminate\\Notifications\\'],
        "illuminate/pagination" => ['Illuminate\\Pagination\\'],
        "illuminate/pipeline" => ['Illuminate\\Pipeline\\'],
        "illuminate/process" => ['Illuminate\\Process\\'],
        "illuminate/queue" => ['Illuminate\\Queue\\'],
        "illuminate/redis" => ['Illuminate\\Redis\\'],
        "illuminate/routing" => ['Illuminate\\Routing\\'],
        "illuminate/session" => ['Illuminate\\Session\\'],
        "illuminate/support" => ['Illuminate\\Support\\'],
        "illuminate/testing" => ['Illuminate\\Testing\\'],
        "illuminate/translation" => ['Illuminate\\Translation\\'],
        "illuminate/validation" => ['Illuminate\\Validation\\'],
        "illuminate/view" => ['Illuminate\\View\\'],
    ];

    /**
     * @var ComposerJson
     */
    private array $composerJson;

    /**
     * @var InstalledJson
     */
    private array $installedJson;

    /**
     * @var array<string, list<string>>
     */
    private array $installedPackages;

    /**
     * @var list<string>
     */
    private array $allowedNamespaces;

    public function isRestrictedClassNameUsage(ClassReflection $classReflection, Scope $scope, ClassNameUsageLocation $location): ?RestrictedUsage
    {
        if ($classReflection->isBuiltin()) {
            return null;
        }

        if ($this->isInGlobalNamespace($classReflection->getName())) {
            return null;
        }

        if ($this->isInAllowedNamespace($classReflection->getName())) {
            return null;
        }

        $packageName = $this->getPackageNameForClass($classReflection->getName());
        $className = $classReflection->getClassTypeDescription() . ' ' . $classReflection->getDisplayName();

        $message = $location->createMessage("$className is not allowed because this dependency is not defined by this module") .
            "\n💡 If you believe this dependency should be allowed, add `$packageName` to `require` in this module's `composer.json`.";

        return RestrictedUsage::create(
            $message,
            $location->createIdentifier('restrictedDependency'),
        );
    }

    public function getKey(): string
    {
        return 'restrict-implicit-dependency-usage';
    }

    public function getHash(): string
    {
        return hash(serialize($this->getComposerJson()) . serialize($this->getInstalledJson()), Algorithm::Sha256);
    }

    public function isInGlobalNamespace(string $class): bool
    {
        return substr_count($class, '\\') <= 1;
    }

    public function isInAllowedNamespace(string $class): bool
    {
        return any($this->getAllowedNamespaces(), fn(string $namespace) => str_starts_with($class, $namespace));
    }

    public function getPackageNameForClass(string $class): ?string
    {
        $packages = filter($this->getInstalledPackagesWithNamespaces(), fn(array $namespaces) => any($namespaces, fn(string $namespace) => str_starts_with($class, $namespace)));
        $packages = sort_by($packages, fn(array $namespaces) => $namespaces ? -max(map($namespaces, fn(string $namespace) => substr_count($namespace, '\\'))) : 0);

        // Remove laravel/framework if there is a more specific package.
        if (count($packages) > 1 && array_key_exists('laravel/framework', $packages)) {
            unset($packages['laravel/framework']);
        }

        return first_key($packages);
    }

    /**
     * @return list<string>
     */
    private function getAllowedNamespaces(): array
    {
        return $this->allowedNamespaces ??= [
            ...$this->getOwnedNamespaces(),
            ...$this->getRequiredNamespaces(),
        ];
    }

    /**
     * @return list<string>
     */
    private function getOwnedNamespaces(): array
    {
        return keys($this->getComposerJson()['autoload']['psr-4'] ?? []);
    }

    /**
     * @return list<string>
     */
    private function getRequiredNamespaces(): array
    {
        $flattened = [];

        foreach ($this->getInstalledPackagesWithNamespaces() as $package => $namespaces) {
            if (in_array($package, $this->getRequiredPackages())) {
                foreach ($namespaces as $namespace) {
                    $flattened[] = $namespace;
                }
            }
        }

        return $flattened;
    }

    /**
     * @return list<string>
     */
    private function getRequiredPackages(): array
    {
        return keys([
            ...$this->getComposerJson()['require'] ?? [],
            ...$this->getComposerJson()['require-dev'] ?? [],
        ]);
    }

    /**
     * @return array<string, list<string>>
     */
    private function getInstalledPackagesWithNamespaces(): array
    {
        return $this->installedPackages ??= [
            ...from_entries(array_map(fn(array $package) => [$package['name'], keys($package['autoload']['psr-4'] ?? [])], $this->getInstalledJson()['packages'])),
            ...$this->getReplacedPackagesWithNamespaces(),
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private function getReplacedPackagesWithNamespaces(): array
    {
        return from_entries(map($this->getReplacedPackages(), fn(string $packageName) => [$packageName, self::KnownNamespaces[$packageName] ?? []]));
    }

    /**
     * @return list<string>
     */
    private function getReplacedPackages(): array
    {
        return flat_map($this->getInstalledJson()['packages'], fn(array $package) => keys($package['replace'] ?? []));
    }

    /**
     * @return InstalledJson
     */
    private function getInstalledJson(): array
    {
        return $this->installedJson ??= Type\shape([
            'packages' => Type\vec(Type\shape([
                'name' => Type\string(),
                'autoload' => Type\optional(Type\shape([
                    'psr-4' => Type\optional(Type\dict(Type\string(), Type\union(Type\string(), Type\vec(Type\string())))),
                ], allow_unknown_fields: true)),
                'replace' => Type\optional(Type\dict(Type\string(), Type\string())),
            ], allow_unknown_fields: true)),
        ], allow_unknown_fields: true)->assert(decode(read(basepath() . '/vendor/composer/installed.json')));
    }

    /**
     * @return ComposerJson
     */
    private function getComposerJson(): array
    {
        return $this->composerJson ??= Type\shape([
            'require' => Type\optional(Type\dict(Type\string(), Type\string())),
            'require-dev' => Type\optional(Type\dict(Type\string(), Type\string())),
            'autoload' => Type\optional(Type\shape([
                'psr-4' => Type\optional(Type\dict(Type\string(), Type\union(Type\string(), Type\vec(Type\string())))),
            ], allow_unknown_fields: true)),
        ], allow_unknown_fields: true)->assert(decode(read('composer.json')));
    }
}
