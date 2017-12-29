<?php

/**
 * @file
 * Contains \Drupal\composer_manager\Form\ComposerManagerSettingsForm.
 */

namespace Drupal\composer_manager\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;

class ComposerManagerSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'composer_manager_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('composer_manager.settings');

    foreach (Element::children($form) as $variable) {
      $config->set($variable, $form_state->getValue($form[$variable]['#parents']));
    }
    $config->save();

    if (method_exists($this, '_submitForm')) {
      $this->_submitForm($form, $form_state);
    }

    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['composer_manager.settings'];
  }

  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state) {

    // Don't break sites that upgraded from <= 7.x-1.0-beta5.
    composer_manager_beta5_compatibility();

    $form['composer_manager_vendor_dir'] = [
      '#title' => 'Vendor Directory',
      '#type' => 'textfield',
      '#default_value' => variable_get('composer_manager_vendor_dir', 'sites/all/vendor'),
      '#description' => t('The relative or absolute path to the vendor directory containing the Composer packages and autoload.php file.'),
    ];

    $form['composer_manager_file_dir'] = [
      '#title' => 'Composer File Directory',
      '#type' => 'textfield',
      '#default_value' => composer_manager_file_dir(),
      '#description' => t('The directory containing the composer.json file and where Composer commands are run.'),
    ];

    $form['composer_manager_autobuild_file'] = [
      '#title' => 'Automatically build the composer.json file when enabling or disabling modules',
      '#type' => 'checkbox',
      '#default_value' => variable_get('composer_manager_autobuild_file', 1),
      '#description' => t('Automatically build the consolidated composer.json for all contributed modules file in the vendor directory above when modules are enabled or disabled. Disable this setting if you want to maintain the composer.json file manually.'),
    ];

    $form['composer_manager_autobuild_packages'] = [
      '#title' => 'Automatically update Composer dependencies when enabling or disabling modules with Drush',
      '#type' => 'checkbox',
      '#default_value' => variable_get('composer_manager_autobuild_packages', 1),
      '#description' => t('Automatically build the consolidated composer.json file and run Composer\'s <code>!command</code> command when enabling or disabling modules with Drush. Disable this setting to manage the composer.json and dependencies manually.', [
        '!command' => 'update'
        ]),
    ];

    $form['#validate'] = ['composer_manager_settings_form_validate'];

    return parent::buildForm($form, $form_state);
  }

  public function validateForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
    module_load_include('inc', 'composer_manager', 'composer_manager.writer');
    $autobuild_file = $form_state->getValue(['composer_manager_autobuild_file']);
    $file_dir = $form_state->getValue(['composer_manager_file_dir']);
    if ($autobuild_file && !composer_manager_prepare_directory($file_dir)) {
      $form_state->setErrorByName('composer_manager_file_dir', t('Composer file directory must be writable'));
    }
  }

}
