<?php

namespace Drupal\poll_system\Form;

use Drupal\Core\Url;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\ContentEntityDeleteForm;

class PollOptionDeleteForm extends ContentEntityDeleteForm
{

  /**
   * {@inheritdoc}
   */
  public function getQuestion()
  {
    /** @var \Drupal\poll_system\Entity\PollOption $entity */
    $entity = $this->getEntity();
    return $this->t('Are you sure you want to delete the option "%title"?', ['%title' => $entity->label()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl()
  {
    $entity = $this->getEntity();

    $poll_id = $entity->getPollId();
    return $poll_id
      ? Url::fromRoute('poll_system.option_list', ['poll' => $poll_id])
      : parent::getCancelUrl();
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $entity = $this->getEntity();
    $poll_id = $entity->getPollId();

    parent::submitForm($form, $form_state);

    if ($poll_id) {
      $form_state->setRedirect('poll_system.option_list', ['poll' => $poll_id]);
    }
  }
}
