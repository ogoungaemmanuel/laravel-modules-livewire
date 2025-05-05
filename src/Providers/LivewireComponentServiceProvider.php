<?php

namespace xslain\LaravelModulesLivewire\Providers;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Livewire;
use xslain\LaravelModulesLivewire\Support\Decomposer;
use ReflectionClass;
use Symfony\Component\Finder\SplFileInfo;

class LivewireComponentServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->registerModuleComponents();

        $this->registerCustomModuleComponents();
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [];
    }

    protected function registerModuleComponents()
    {
        if (Decomposer::checkDependencies()->type == 'error') {
            return false;
        }

        $modules = \Nwidart\Modules\Facades\Module::toCollection();

        $modulesLivewireNamespace = config('modules-livewire.namespace', 'Livewire');

        $modules->each(function ($module) use ($modulesLivewireNamespace): void {
            $directory = (string) Str::of($module->getPath())
                ->append('/'.$modulesLivewireNamespace)
                ->replace(['\\'], '/');

            $moduleNamespace = method_exists($module, 'getNamespace')
                ? $module->getNamespace()
                : config('modules.namespace', 'Modules');

            $namespace = $moduleNamespace.'\\'.$module->getName().'\\'.$modulesLivewireNamespace;

            $this->registerComponentDirectory($directory, $namespace, $module->getLowerName().'::');
        });
    }

    protected function registerCustomModuleComponents()
    {
        if (Decomposer::checkDependencies(['livewire/livewire'])->type == 'error') {
            return false;
        }

        $modules = collect(config('modules-livewire.custom_modules', []));

        $modules->each(function ($module, $moduleName): void {
            $moduleLivewireNamespace = $module['namespace'] ?? config('modules-livewire.namespace', 'Livewire');

            $directory = (string) Str::of($module['path'] ?? '')
                ->append('/'.$moduleLivewireNamespace)
                ->replace(['\\'], '/');

            $namespace = ($module['module_namespace'] ?? $moduleName).'\\'.$moduleLivewireNamespace;

            $lowerName = $module['name_lower'] ?? strtolower($moduleName);

            $this->registerComponentDirectory($directory, $namespace, $lowerName.'::');
        });
    }

    protected function registerComponentDirectory($directory, $namespace, $aliasPrefix = '')
    {
        $filesystem = new Filesystem();

        if (! $filesystem->isDirectory($directory)) {
            return false;
        }

        collect($filesystem->allFiles($directory))
            ->map(fn(SplFileInfo $file) => (string) Str::of($namespace)
                ->append('\\', $file->getRelativePathname())
                ->replace(['/', '.php'], ['\\', '']))
            ->filter(fn($class) => is_subclass_of($class, Component::class) && ! (new ReflectionClass($class))->isAbstract())
            ->each(function ($class) use ($namespace, $aliasPrefix): void {
                $alias = $aliasPrefix.Str::of($class)
                    ->after($namespace.'\\')
                    ->replace(['/', '\\'], '.')
                    ->explode('.')
                    ->map(Str::kebab(...))
                    ->implode('.');

                if (Str::endsWith($class, ['\Index', '\index'])) {
                    Livewire::component(Str::beforeLast($alias, '.index'), $class);
                }

                Livewire::component($alias, $class);
            });
    }
}
