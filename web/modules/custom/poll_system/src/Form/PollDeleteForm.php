<?php

namespace Drupal\poll_system\Form;

use Drupal\Core\Url;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\ContentEntityDeleteForm;

/**
 * Form controller for deleting a Poll.
 */
class PollDeleteForm extends ContentEntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    $entity = $this->getEntity();
    return $this->t('Are you sure you want to delete the poll "%title"?', ['%title' => $entity->label()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return Url::fromRoute('poll_system.list');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $poll = $this->getEntity();

    $option_storage = \Drupal::entityTypeManager()->getStorage('poll_system_option');
    $options = $option_storage->loadByProperties(['poll_id' => $poll->id()]);
    foreach ($options as $option) {
      $option->delete();
    }

    parent::submitForm($form, $form_state);

    $this->messenger()->addStatus($this->t('The poll "%title" and all its options were deleted.', [
      '%title' => $poll->label(),
    ]));

    $form_state->setRedirect('poll_system.list');
  }
}
