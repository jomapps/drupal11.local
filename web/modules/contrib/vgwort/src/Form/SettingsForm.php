<?php

namespace Drupal\vgwort\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\ConfigTarget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\ToConfig;
use Drupal\Core\Security\TrustedCallbackInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure VG Wort settings for this site.
 *
 * @todo Consider adding validation for publisher_id and domain.
 *
 * @internal
 */
class SettingsForm extends ConfigFormBase implements TrustedCallbackInterface {

  public function __construct(ConfigFactoryInterface $config_factory, TypedConfigManagerInterface $typedConfigManager, protected EntityTypeManagerInterface $entityTypeManager, protected EntityDisplayRepositoryInterface $entityDisplayRepository) {
    parent::__construct($config_factory, $typedConfigManager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('entity_type.manager'),
      $container->get('entity_display.repository'),
    );

  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'vgwort_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['vgwort.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('vgwort.settings');

    // Use config with overrides so any settings.php overrides are respected.
    if (empty($form_state->getUserInput()) && $this->configFactory->get('vgwort.settings')->get('test_mode')) {
      $this->messenger()->addWarning($this->t('The test mode is enabled. The 1x1 pixel will be added as HTML comment to the selected entity_types.'));
    }

    $form['account_details'] = [
      '#type' => 'fieldgroup',
      '#title' => $this->t('Account details'),
      '#theme_wrappers' => ['fieldset'],
    ];
    $form['account_details']['username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Username'),
      '#config_target' => 'vgwort.settings:username',
      '#description' => $this->t('Your login for VG Wort.'),
    ];

    $form['account_details']['password_wrapper'] = [
      '#type' => 'container',
      '#prefix' => '<div id="js-password-wrapper">',
      '#suffix' => '</div>',
      '#pre_render' => [[$this, 'preRenderPassword']],
    ];

    $trigger = $form_state->getTriggeringElement();
    if (isset($trigger['#parents'][0]) && $trigger['#parents'][0] === 'reset_password') {
      $config->set('password', '')->save();
    }

    $allow_password_entry = empty($config->get('password'));
    $form['account_details']['password_wrapper']['password'] = [
      '#type' => 'password',
      '#title' => $this->t('VG Wort password'),
      '#disabled' => !$allow_password_entry,
      // Never set a default value as this is only for setting new passwords.
      '#config_target' => new ConfigTarget('vgwort.settings', 'password', fn($value) => '', fn($value) => $allow_password_entry ? $value : ToConfig::NoOp),
      '#placeholder' => !$allow_password_entry ? $this->t('** Hidden for security reasons **') : '',
    ];

    $form['account_details']['password_wrapper']['reset_password'] = [
      '#type' => 'button',
      '#value' => $this->t('Reset'),
      '#attributes' => ['aria_label' => $this->t('Reset password')],
      '#access' => !$allow_password_entry,
      '#ajax' => [
        'callback' => '::removePassword',
        'wrapper' => 'js-password-wrapper',
      ],
      '#limit_validation_errors' => [],
    ];

    $form['account_details']['publisher_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Publisher ID (Karteinummer)'),
      '#config_target' => 'vgwort.settings:publisher_id',
      '#description' => $this->t('Your ID provided by VG Wort.'),
      '#maxlength' => 7,
    ];

    $form['account_details']['image_domain'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Counter domain'),
      '#config_target' => 'vgwort.settings:image_domain',
      '#description' => $this->t('The counter domain that is provided by VG Wort, for example: vg07.met.vgwort.de.'),
    ];

    // Entity types marked as "internal" are removed from the list.
    $entity_types = array_map(
      function (EntityTypeInterface $entity_type) {
        return $entity_type->getLabel();
      },
      array_filter($this->entityTypeManager->getDefinitions(), function (EntityTypeInterface $entity_type) {
        return !$entity_type->isInternal() &&
          $entity_type instanceof ContentEntityTypeInterface &&
          $entity_type->entityClassImplements(EntityPublishedInterface::class) &&
          $entity_type->hasLinkTemplate('canonical');
      })
    );

    asort($entity_types);

    $form['entity_types'] = [
      '#type' => 'table',
      '#title' => $this->t('Entity types'),
      '#open' => TRUE,
      '#tree' => TRUE,
      '#caption' => $this->t('Select entity types that can have a counter ID. The view mode is used to generate the text to send to VG Wort.'),
      '#header' => [$this->t('Entity type'), $this->t('View mode')],
      '#config_target' => new ConfigTarget('vgwort.settings', 'entity_types', toConfig: function ($values, FormStateInterface $form_state) use ($config) {
        $entity_types = $config->get('entity_types');
        foreach ($form_state->getValue('entity_types') as $entity_type_id => $entity_type_info) {
          if ($entity_type_info['enabled']) {
            // If the entity type is already set ensure we do not overwrite any
            // fields set.
            $entity_types[$entity_type_id]['view_mode'] = $entity_type_info['view_mode'];
          }
          else {
            unset($entity_types[$entity_type_id]);
          }
        }
        return $entity_types;
      }),
    ];

    foreach ($entity_types as $entity_type_id => $label) {
      $form['entity_types'][$entity_type_id] = [
        '#type' => 'container',
      ];
      $form['entity_types'][$entity_type_id]['enabled'] = [
        '#type' => 'checkbox',
        '#title' => $label,
        '#config_target' => new ConfigTarget('vgwort.settings', "entity_types.$entity_type_id", fn($value) => !is_null($value), fn() => ToConfig::NoOp),
      ];
      $form['entity_types'][$entity_type_id]['view_mode'] = [
        '#type' => 'select',
        '#title' => $this->t('View mode'),
        '#title_display' => 'invisible',
        '#options' => $this->entityDisplayRepository->getViewModeOptions($entity_type_id),
        '#states' => [
          'visible' => [
            'input[name="entity_types[' . $entity_type_id . '][enabled]"]' => ['checked' => TRUE],
          ],
        ],
        '#config_target' => new ConfigTarget('vgwort.settings', "entity_types.$entity_type_id.view_mode", toConfig: fn() => ToConfig::NoOp),
        // Stop the width from bouncing all over the place.
        '#wrapper_attributes' => ['width' => '50%'],
      ];
    }

    $form['legal_rights'] = [
      '#type' => 'details',
      '#title' => $this->t('Legal rights'),
      '#open' => TRUE,
      '#tree' => TRUE,
    ];
    $form['legal_rights']['distribution'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Distribution right (<a href="https://www.gesetze-im-internet.de/urhg/__17.html" target="_blank">§ 17 UrhG</a>)'),
      '#config_target' => 'vgwort.settings:legal_rights.distribution',
    ];
    $form['legal_rights']['public_access'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Right of public access (<a href="https://www.gesetze-im-internet.de/urhg/__19a.html" target="_blank">§ 19a UrhG</a>)'),
      '#config_target' => 'vgwort.settings:legal_rights.public_access',
    ];
    $form['legal_rights']['reproduction'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Reproduction Rights (<a href="https://www.gesetze-im-internet.de/urhg/__16.html" target="_blank">§ 16 UrhG</a>)'),
      '#config_target' => 'vgwort.settings:legal_rights.reproduction',
    ];
    $form['legal_rights']['declaration_of_granting'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Declaration of Granting of Rights.'),
      '#config_target' => 'vgwort.settings:legal_rights.declaration_of_granting',
    ];
    $form['legal_rights']['other_public_communication'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Other Public Communication Rights (§§ <a href="https://www.gesetze-im-internet.de/urhg/__19.html" target="_blank">19</a>, <a href="https://www.gesetze-im-internet.de/urhg/__20.html" target="_blank">20</a>, <a href="https://www.gesetze-im-internet.de/urhg/__21.html" target="_blank">21</a>, <a href="https://www.gesetze-im-internet.de/urhg/__22.html" target="_blank">22</a> UrhG)'),
      '#config_target' => 'vgwort.settings:legal_rights.other_public_communication',
    ];

    // Ensure the expected legal rights are granted as not doing so will cause
    // errors on VG Wort.
    $legal_rights = $config->get('legal_rights');
    if (empty($form_state->getUserInput()) && ($legal_rights['distribution'] !== TRUE || $legal_rights['public_access'] !== TRUE || $legal_rights['reproduction'] !== TRUE || $legal_rights['declaration_of_granting'] !== TRUE)) {
      $this->messenger()->addWarning($this->t('The right of reproduction (§ 16 UrhG), right of distribution (§ 17 UrhG), right of public access (§ 19a UrhG) and the declaration of granting rights must be confirmed.'));
    }
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $publisher_id = $form_state->getValue('publisher_id');
    if (strlen($publisher_id) > 0 && (!preg_match('/^[0-9]*$/', $publisher_id) || $publisher_id < 10 || $publisher_id > 9999999)) {
      $form_state->setErrorByName('publisher_id', $this->t('Publisher ID (Karteinummer) must be a numeric ID between 10 and 9999999.'));
    }

    $image_domain = $form_state->getValue('image_domain');
    if (!preg_match('/^([0-9]|[a-z]|\.)*$/i', $image_domain)) {
      $form_state->setErrorByName('image_domain', $this->t('Counter domain must be a valid counter domain. For example, vg07.met.vgwort.de.'));
    }
  }

  /**
   * AJAX callback for the delete password button.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   *
   * @return array
   *   The password form wrapper.
   */
  public function removePassword(array &$form, FormStateInterface $form_state): array {
    return $form['account_details']['password_wrapper'];
  }

  /**
   * Prepares the password element for rendering.
   *
   * @param array $element
   *   The element to transform.
   *
   * @return array
   *   The transformed element.
   *
   * @see ::formElement()
   */
  public function preRenderPassword(array $element): array {
    // Move the delete password button next to the text element.
    if (isset($element['reset_password'])) {
      $element['password']['#field_suffix']['reset_password'] = $element['reset_password'];
      unset($element['reset_password']);
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks(): array {
    return ['preRenderPassword'];
  }

}
