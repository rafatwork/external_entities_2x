<?php

namespace Drupal\external_entities\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\external_entities\ExternalEntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Component\Utility\NestedArray;
use Drupal\external_entities\Event\ExternalEntitiesEvents;
use Drupal\external_entities\Event\ExternalEntityExtractRawDataEvent;
use Drupal\external_entities\Event\ExternalEntityMapRawDataEvent;
use Drupal\field\FieldConfigInterface;
use Drupal\external_entities\ExternalEntityTypeInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItem;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;

/**
 * Defines the external entity class.
 *
 * @see external_entities_entity_type_build()
 */
class ExternalEntity extends ContentEntityBase implements ExternalEntityInterface {

  /**
   * Defines the field name used to reference the optional annotation entity.
   */
  const ANNOTATION_FIELD = 'annotation';

  /**
   * Defines the prefix of annotation fields inherited by the external entity.
   */
  const ANNOTATION_FIELD_PREFIX = 'annotation_';

  /**
   * {@inheritdoc}
   */
  public function getExternalEntityType() {
    return $this
      ->entityTypeManager()
      ->getStorage('external_entity_type')
      ->load($this->getEntityTypeId());
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    return self::defaultBaseFieldDefinitions();
  }

  /**
   * Provides the default base field definitions for external entities.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface[]
   *   An array of base field definitions for the entity type, keyed by field
   *   name.
   */
  public static function defaultBaseFieldDefinitions() {
    $fields = [];

    $fields['id'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('ID'))
      ->setReadOnly(TRUE);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Title'))
      ->setReadOnly(TRUE)
      ->setDisplayOptions('view', [
        'type' => 'string',
        'label' => 'hidden',
      ])
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public static function bundleFieldDefinitions(EntityTypeInterface $entity_type, $bundle, array $base_field_definitions) {
    $fields = parent::bundleFieldDefinitions($entity_type, $bundle, $base_field_definitions);

    /* @var \Drupal\external_entities\ExternalEntityTypeInterface $external_entity_type */
    $external_entity_type = \Drupal::entityTypeManager()
      ->getStorage('external_entity_type')
      ->load($entity_type->id());
    if ($external_entity_type->isAnnotatable()) {
      // Add the annotation reference field.
      $fields[static::ANNOTATION_FIELD] = BaseFieldDefinition::create('entity_reference')
        ->setLabel(t('Annotation'))
        ->setDescription(t('The annotation entity.'))
        ->setSetting('target_type', $external_entity_type->getAnnotationEntityTypeId())
        ->setSetting('handler', 'default')
        ->setSetting('handler_settings', [
          'target_bundles' => [$external_entity_type->getAnnotationBundleId()],
        ])
        ->setDisplayOptions('form', [
          'type' => 'entity_reference_autocomplete',
          'weight' => 5,
          'settings' => [
            'match_operator' => 'CONTAINS',
            'size' => '60',
            'placeholder' => '',
          ],
        ])
        ->setDisplayConfigurable('form', TRUE)
        ->setDisplayOptions('view', [
          'label' => t('Annotation'),
          'type' => 'entity_reference_label',
          'weight' => 0,
        ])
        ->setDisplayConfigurable('view', TRUE);

      // Have the external entity inherit its annotation fields.
      if ($external_entity_type->inheritsAnnotationFields()) {
        $inherited_fields = static::getInheritedAnnotationFields($external_entity_type);
        $field_prefix = ExternalEntity::ANNOTATION_FIELD_PREFIX;
        foreach ($inherited_fields as $field) {
          $field_definition = BaseFieldDefinition::createFromFieldStorageDefinition($field->getFieldStorageDefinition())
            ->setName($field_prefix . $field->getName())
            ->setReadOnly(TRUE)
            ->setComputed(TRUE)
            ->setLabel($field->getLabel())
            ->setDisplayConfigurable('view', TRUE);
          $fields[$field_prefix . $field->getName()] = $field_definition;
        }
      }
    }

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function extractRawData() {
    $raw_data = [];

    foreach ($this->getExternalEntityType()->getFieldMappings() as $field_name => $properties) {
      $field_values = $this->get($field_name)->getValue();
      $field_cardinality = $this
        ->getFieldDefinition($field_name)
        ->getFieldStorageDefinition()
        ->getCardinality();

      foreach ($field_values as $key => $field_value) {
        foreach ($properties as $property_name => $mapped_key) {
          // The plus (+) character at the beginning of a mapping key indicates
          // the property doesn't have a mapping but a default value, so we
          // skip these.
          if (strpos($mapped_key, '+') === 0) {
            continue;
          }

          if (!empty($field_value[$property_name])) {
            $exploded_mapped_key = explode('/', $mapped_key);
            // If field cardinality is more than 1, we consider the field value
            // to be a separate array.
            if ($field_cardinality !== 1) {
              $exploded_mapped_key[1] = $key;
            }

            // TODO: What about dates and their original format?
            NestedArray::setValue($raw_data, $exploded_mapped_key, $field_value[$property_name]);
          }
        }
      }
    }

    // Allow other modules to perform custom extraction logic.
    $event = new ExternalEntityExtractRawDataEvent($this, $raw_data);
    \Drupal::service('event_dispatcher')->dispatch(ExternalEntitiesEvents::EXTRACT_RAW_DATA, $event);

    return $event->getRawData();
  }

  /**
   * {@inheritdoc}
   */
  public function mapRawData(array $raw_data) {
    foreach ($this->getExternalEntityType()->getFieldMappings() as $field_name => $properties) {
      $field_definition = $this->getFieldDefinition($field_name);
      $field_values = [];

      foreach ($properties as $property_name => $mapped_key) {
        // The plus (+) character at the beginning of a mapping key indicates
        // the property doesn't have a mapping but a default value. We process
        // default values after all the regular mappings have been processed.
        if (strpos($mapped_key, '+') === 0) {
          continue;
        }

        $exploded_mapped_key = explode('/', $mapped_key);
        // The asterisk (*) character indicates that we are dealing with a
        // multivalued field. We consider each individual field item to be in
        // its separate array.
        if (isset($exploded_mapped_key[1]) && $exploded_mapped_key[1] === '*') {
          $parents = array_slice($exploded_mapped_key, 2);
          foreach (array_values($raw_data[$exploded_mapped_key[0]]) as $key => $value) {
            $value = !is_array($value) ? [$value] : $value;
            $field_values[$key][$property_name] = NestedArray::getValue($value, $parents);
          }
        }
        else {
          $property_values = NestedArray::getValue($raw_data, $exploded_mapped_key);
          if (!is_array($property_values)) {
            $property_values = [$property_values];
          }

          foreach (array_values($property_values) as $key => $property_value) {
            $field_values[$key][$property_name] = $property_value;
          }
        }
      }

      // Process the default values.
      foreach ($properties as $property_name => $mapped_key) {
        if (strpos($mapped_key, '+') === 0) {
          foreach (array_keys($field_values) as $key) {
            $field_values[$key][$property_name] = substr($mapped_key, 1);
          }
        }
      }

      // Provide specific conversion for dates.
      if ($field_definition->getType() === 'datetime' && $property_name === 'value') {
        foreach ($field_values as $key => $item) {
          if (!empty($item['value'])) {
            $timestamp = !is_numeric($item['value'])
              ? strtotime($item['value'])
              : $item['value'];
            switch ($field_definition->getSetting('datetime_type')) {
              case DateTimeItem::DATETIME_TYPE_DATE:
                $format = DateTimeItemInterface::DATE_STORAGE_FORMAT;
                break;

              default:
                $format = DateTimeItemInterface::DATETIME_STORAGE_FORMAT;
                break;
            }
            $item['value'] = \Drupal::service('date.formatter')->format($timestamp, 'custom', $format);
            $field_values[$key] = $item;
          }
        }
      }

      $this->set($field_name, $field_values);
    }

    // Allow other modules to perform custom mapping logic.
    $event = new ExternalEntityMapRawDataEvent($this, $raw_data);
    \Drupal::service('event_dispatcher')->dispatch(ExternalEntitiesEvents::MAP_RAW_DATA, $event);

    return $event->getEntity();
  }

  /**
   * {@inheritdoc}
   */
  public function getAnnotation() {
    $external_entity_type = $this->getExternalEntityType();
    if ($external_entity_type->isAnnotatable()) {
      $annotation = $this->entityTypeManager()
        ->getStorage($external_entity_type->getAnnotationEntityTypeId())
        ->loadByProperties([
          $external_entity_type->getAnnotationFieldName() => $this->id(),
        ]);
      if (!empty($annotation)) {
        return array_shift($annotation);
      }
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function mapAnnotationFields() {
    $external_entity_type = $this->getExternalEntityType();
    if ($external_entity_type->isAnnotatable()) {
      $annotation = $this->getAnnotation();
      if ($annotation) {
        $this->set(static::ANNOTATION_FIELD, $annotation->id());
        if ($external_entity_type->inheritsAnnotationFields()) {
          $inherited_fields = static::getInheritedAnnotationFields($external_entity_type);
          $field_prefix = static::ANNOTATION_FIELD_PREFIX;
          foreach ($inherited_fields as $field_name => $inherited_field) {
            $value = $annotation->get($field_name)->getValue();
            if (!empty($value)) {
              $this->set($field_prefix . $field_name, $value);
            }
          }
        }
      }
    }

    return $this;
  }

  /**
   * Gets the fields that can be inherited by the external entity.
   *
   * @param \Drupal\external_entities\ExternalEntityTypeInterface $type
   *   The type of the external entity.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface[]
   *   An array of field definitions, keyed by field name.
   *
   * @see \Drupal\Core\Entity\EntityManagerInterface::getFieldDefinitions()
   */
  public static function getInheritedAnnotationFields(ExternalEntityTypeInterface $type) {
    $inherited_fields = [];

    $field_definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions($type->getAnnotationEntityTypeId(), $type->getAnnotationBundleId());
    foreach ($field_definitions as $field_name => $field_definition) {
      if ($field_definition instanceof FieldConfigInterface && $field_name !== $type->getAnnotationFieldName()) {
        $inherited_fields[$field_name] = $field_definition;
      }
    }

    return $inherited_fields;
  }

}
