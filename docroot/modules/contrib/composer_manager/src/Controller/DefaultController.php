<?php /**
 * @file
 * Contains \Drupal\composer_manager\Controller\DefaultController.
 */

namespace Drupal\composer_manager\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Default controller for the composer_manager module.
 */
class DefaultController extends ControllerBase {

  public function composer_manager_packages_page() {
    $build = [];
    $error = FALSE;

    $header = [
      'package' => t('Package'),
      'version' => t('Installed Version'),
      'requirement' => t('Version Required by Module'),
    ];

    try {
      $required = composer_manager_required_packages();
      $installed = composer_manager_installed_packages();
      $dependents = composer_manager_package_dependents();
      $combined = array_unique(array_merge(array_keys($required), array_keys($installed)));
    }
    
      catch (\RuntimeException $e) {
      $error = TRUE;
      drupal_set_message(\Drupal\Component\Utility\Xss::filterAdmin($e->getMessage()));
      watchdog_exception('composer_manager', $e);
      $combined = [];
    }

    // Whether a Composer update is needed.
    $update_needed = FALSE;
    // Whether different modules require different versions of the same package.
    $has_conflicts = FALSE;

    $rows = [];
    foreach ($combined as $package_name) {
      $is_installed = isset($installed[$package_name]);

      // If the package is installed but has no dependents and is not required by
      // any modules, then the module that required it has most likely been
      // disabled and the package will be uninstalled on the next Composer update.
      $not_required = $is_installed && !isset($dependents[$package_name]) && empty($required[$package_name]);

      // Get the package name and description.
      if ($is_installed && !empty($installed[$package_name]['homepage'])) {
        $options = ['attributes' => ['target' => '_blank']];
        $name = l($package_name, $installed[$package_name]['homepage'], $options);
      }
      else {
        $name = \Drupal\Component\Utility\SafeMarkup::checkPlain($package_name);
      }
      if ($is_installed && !empty($installed[$package_name]['description'])) {
        $name .= '<div class="description">' . \Drupal\Component\Utility\SafeMarkup::checkPlain($installed[$package_name]['description']) . '</div>';
      }

      // Get the version required by the module.
      $has_conflict = FALSE;
      if ($not_required) {
        $update_needed = TRUE;
        $requirement = t('No longer required');
        $requirement .= '<div class="description">' . t('Package will be removed on the next Composer update') . '</div>';
      }
      elseif (isset($required[$package_name])) {

        // Sets message based on whether there is a potential version conflict.
        $has_conflict = count($required[$package_name]) > 1;
        if ($has_conflict) {
          $has_conflicts = TRUE;
          $requirement = t('Potential version conflict');
        }
        else {
          $requirement = \Drupal\Component\Utility\SafeMarkup::checkPlain(key($required[$package_name]));
        }

        // Build the list of modules that require this package.
        $modules = [];
        $requirement .= '<div class="description">';
        foreach ($required[$package_name] as $version => $module_names) {
          foreach ($module_names as $module_name) {
            $module_info = system_get_info('module', $module_name);
            $modules[] = \Drupal\Component\Utility\SafeMarkup::checkPlain($module_info['name']);
          }
        }
        $requirement .= t('Required by: ') . join(', ', $modules);
        $requirement .= '</div>';
      }
      else {
        // This package is a dependency of a package directly required by a
      // module. Therefore we cannot detect the required version without using
      // the Composer tool which is expensive and too slow for the web.
        $requirement = t('N/A');
        $requirement .= '<div class="description">' . t('Dependency for other packages') . '</div>';
      }

      // Get the version that is installed.
      if ($is_installed) {
        $instaled_version = \Drupal\Component\Utility\SafeMarkup::checkPlain($installed[$package_name]['version']);
      }
      else {
        $update_needed = TRUE;
        $instaled_version = t('Not installed');
      }

      // Set the row status.
      if (!$is_installed) {
        $class = ['error'];
      }
      elseif ($has_conflict || $not_required) {
        $class = ['warning'];
      }
      else {
        $class = ['ok'];
      }

      $rows[$package_name] = [
        'class' => $class,
        'data' => [
          'package' => $name,
          'installed' => $instaled_version,
          'requirement' => $requirement,
        ],
      ];
    }

    ksort($rows);
    $build['packages'] = [
      '#theme' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#caption' => t('Status of Packages Managed by Composer'),
      '#attributes' => [
        'class' => [
          'system-status-report'
          ]
        ],
    ];

    $build['refresh_form'] = \Drupal::formBuilder()->getForm('composer_manager_rebuild_form');

    // Set status messages.
    module_load_install('composer_manager');
    $requirements = module_invoke('composer_manager', 'requirements', 'runtime');

    if (REQUIREMENT_OK != $requirements['composer_manager']['severity']) {
      drupal_set_message($requirements['composer_manager']['description'], 'error');
    }
    elseif ($update_needed) {
      $args = [
        '!command' => 'update',
        '@url' => url('http://drupal.org/project/composer_manager', [
          'absolute' => TRUE
          ]),
      ];
      drupal_set_message(t('Packages need to be installed or removed by running Composer\'s <code>!command</code> command.<br/>Refer to the instructions on the <a href="@url" target="_blank">Composer Manager project page</a> for updating packages.', $args), 'warning');
    }
    if ($has_conflicts) {
      drupal_set_message(t('Potentially conflicting versions of the same package are required by different modules.'), 'warning');
    }

    return $build;
  }

}
