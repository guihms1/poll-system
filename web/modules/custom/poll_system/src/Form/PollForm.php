<?php

namespace Drupal\poll_system\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for the poll entity create/edit forms.
 */
class PollForm extends ContentEntityForm
{

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state)
  {
    $form = parent::form($form, $form_state);
    $entity = $this->entity;

    if (isset($form['identifier']['widget'][0]['value'])) {
      $form['identifier']['widget'][0]['value']['#type'] = 'machine_name';
      $form['identifier']['widget'][0]['value']['#machine_name'] = [
        'exists' => [$this, 'exists'],
        'source' => ['title', 'widget', 0, 'value'],
      ];
      $form['identifier']['widget'][0]['value']['#disabled'] = !$entity->isNew();
      $form['identifier']['widget'][0]['value']['#title'] = $this->t('Identifier');
      $form['identifier']['widget'][0]['value']['#description'] = $this->t('A unique machine-readable name for this poll.');
    }

    unset($form['uid']);

    return $form;
  }

  /**
   * Checks if a poll with the specified identifier already exists.
   *
   * @param string $identifier
   *   The identifier to check.
   *
   * @return bool
   *   TRUE if a poll with the specified identifier exists, FALSE otherwise.
   */
  public function exists($identifier)
  {
    $entity_type_id = $this->entity->getEntityTypeId();
    $entity_storage = $this->entityTypeManager->getStorage($entity_type_id);
    $entity_query = $entity_storage->getQuery()->accessCheck(false);

    $id = $this->entity->id();
    $entity_query->condition('identifier', $identifier);

    if ($id) {
      $entity_query->condition('id', $id, '<>');
    }

    $result = $entity_query->range(0, 1)->execute();

    return !empty($result);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state)
  {
    $status = parent::save($form, $form_state);

    $entity = $this->getEntity();

    $form_state->setRedirect('poll_system.option_list', ['poll' => $entity->id()]);

    $this->messenger()->addStatus($this->t('The poll %title has been saved.', [
      '%title' => $entity->label(),
    ]));

    return $status;
  }
}
