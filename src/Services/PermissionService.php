<?php

namespace App\Services;

use Symfony\Component\Yaml\Yaml;

class PermissionService
{
    private array $permissions = [];

    public function __construct(
        private string $projectDir
    ) {
        $this->loadPermissions();
    }

    private function loadPermissions(): void
    {
        $file = $this->projectDir . '/config/permissions.yaml';
        if (file_exists($file)) {
            $this->permissions = Yaml::parseFile($file)['modules'] ?? [];
        }
    }

    public function getModules(): array
    {
        return $this->permissions;
    }

    public function getModule(string $moduleKey): ?array
    {
        return $this->permissions[$moduleKey] ?? null;
    }

    public function getAllPermissions(): array
    {
        $all = [];
        foreach ($this->permissions as $moduleKey => $module) {
            if (isset($module['actions'])) {
                foreach ($module['actions'] as $actionKey => $actionLabel) {
                    $all[] = [
                        'module' => $moduleKey,
                        'action' => $actionKey,
                        'label' => $module['label'] . ' → ' . $actionLabel,
                        'key' => $moduleKey . ':' . $actionKey,
                    ];
                }
            }
            if (isset($module['submodules'])) {
                foreach ($module['submodules'] as $subKey => $submodule) {
                    foreach ($submodule['actions'] as $actionKey => $actionLabel) {
                        $all[] = [
                            'module' => $moduleKey,
                            'submodule' => $subKey,
                            'action' => $actionKey,
                            'label' => $module['label'] . ' → ' . $submodule['label'] . ' → ' . $actionLabel,
                            'key' => $moduleKey . '.' . $subKey . ':' . $actionKey,
                        ];
                    }
                }
            }
        }
        return $all;
    }

    public function getPermissionsForForm(): array
    {
        $grouped = [];
        foreach ($this->permissions as $moduleKey => $module) {
            $group = [
                'key' => $moduleKey,
                'label' => $module['label'],
                'permissions' => [],
            ];

            if (isset($module['actions'])) {
                foreach ($module['actions'] as $actionKey => $actionLabel) {
                    $group['permissions'][] = [
                        'key' => $moduleKey . ':' . $actionKey,
                        'label' => $actionLabel,
                    ];
                }
            }

            if (isset($module['submodules'])) {
                foreach ($module['submodules'] as $subKey => $submodule) {
                    foreach ($submodule['actions'] as $actionKey => $actionLabel) {
                        $group['permissions'][] = [
                            'key' => $moduleKey . '.' . $subKey . ':' . $actionKey,
                            'label' => $submodule['label'] . ' → ' . $actionLabel,
                        ];
                    }
                }
            }

            $grouped[] = $group;
        }
        return $grouped;
    }

    public function hasPermission(array $userPermissions, string $module, string $action, ?string $submodule = null): bool
    {
        $key = $submodule ? "$module.$submodule:$action" : "$module:$action";
        return in_array($key, $userPermissions);
    }

    public function getAccessibleMenuItems(array $userPermissions): array
    {
        $menu = [];
        foreach ($this->permissions as $moduleKey => $module) {
            $hasAccess = false;

            if (isset($module['actions'])) {
                foreach ($module['actions'] as $actionKey => $actionLabel) {
                    if (in_array("$moduleKey:$actionKey", $userPermissions)) {
                        $hasAccess = true;
                        break;
                    }
                }
            }

            if (!$hasAccess && isset($module['submodules'])) {
                foreach ($module['submodules'] as $subKey => $submodule) {
                    foreach ($submodule['actions'] as $actionKey => $actionLabel) {
                        if (in_array("$moduleKey.$subKey:$actionKey", $userPermissions)) {
                            $hasAccess = true;
                            break 2;
                        }
                    }
                }
            }

            if ($hasAccess) {
                $menu[] = $moduleKey;
            }
        }
        return $menu;
    }
}
