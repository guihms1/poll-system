<?php

namespace Drupal\poll_system\Controller;

use Drupal\Core\Url;
use Drupal\poll_system\Entity\Poll;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Drupal\poll_system\Service\PollSystemService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for the Poll System module.
 */
class PollSystemController extends ControllerBase {

  /**
   * The Poll System service.
   *
   * @var \Drupal\poll_system\Service\PollSystemService
   */
  protected $pollService;

  /**
   * Constructs a PollSystemController object.
   *
   * @param \Drupal\poll_system\Service\PollSystemService $poll_service
   *   The Poll System service.
   */
  public function __construct(PollSystemService $poll_service) {
    $this->pollService = $poll_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('poll_system.poll_service')
    );
  }

  /**
   * Displays a list of polls.
   *
   * @return array
   *   A render array for the poll list page.
   */
  public function pollList() {
    $build = [];

    $header = [
      'title' => $this->t('Title'),
      'identifier' => $this->t('Identifier'),
      'status' => $this->t('Status'),
      'show_results' => $this->t('Show Results'),
      'operations' => $this->t('Operations'),
    ];

    $rows = [];

    $polls = $this->entityTypeManager()->getStorage('poll_system')->loadMultiple();

    foreach ($polls as $poll) {
      $row = [];
      $row['title'] = $poll->getTitle();
      $row['identifier'] = $poll->getIdentifier();
      $row['status'] = $poll->isActive() ? $this->t('Active') : $this->t('Inactive');
      $row['show_results'] = $poll->showResults() ? $this->t('Yes') : $this->t('No');

      $operations = [];
      $operations['edit'] = [
        'title' => $this->t('Edit'),
        'url' => Url::fromRoute('poll_system.edit', ['poll' => $poll->id()]),
      ];
      $operations['options'] = [
        'title' => $this->t('Options'),
        'url' => Url::fromRoute('poll_system.option_list', ['poll' => $poll->id()]),
      ];
      $operations['results'] = [
        'title' => $this->t('Results'),
        'url' => Url::fromRoute('poll_system.results', ['poll' => $poll->id()]),
      ];
      $operations['delete'] = [
        'title' => $this->t('Delete'),
        'url' => Url::fromRoute('poll_system.delete', ['poll' => $poll->id()]),
      ];

      $row['operations'] = [
        'data' => [
          '#type' => 'operations',
          '#links' => $operations,
        ],
      ];

      $rows[] = $row;
    }

    $build['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No polls found.'),
    ];

    $build['add_link'] = [
      '#type' => 'link',
      '#title' => $this->t('Add new poll'),
      '#url' => Url::fromRoute('poll_system.add'),
      '#attributes' => ['class' => ['button', 'button--primary']],
    ];

    return $build;
  }

  public function publicPollList() {
    $poll_enabled = $this->pollService->isPollEnabled();

    $polls = $this->entityTypeManager()
      ->getStorage('poll_system')
      ->loadMultiple();

    $visible_polls = [];

    foreach ($polls as $poll) {
      $results = NULL;

      if ($poll->showResults()) {
        $results = $this->pollService->getPollResults($poll->id());
      }

      $visible_polls[] = [
        'poll' => $poll,
        'is_active' => $poll->isActive(),
        'show_results' => $poll->showResults(),
        'results' => $results,
      ];
    }

    return [
      '#theme' => 'poll_system_list',
      '#polls' => $visible_polls,
      '#attached' => [
        'library' => ['poll_system/poll_system'],
      ],
      '#poll_enabled' => $poll_enabled,
      '#cache' => [
        'tags' => ['poll_system_list'],
        'contexts' => ['user'],
      ],
    ];
  }

  /**
   * Displays a list of poll options.
   *
   * @param mixed $poll
   *   The poll entity.
   *
   * @return array
   *   A render array for the poll options list page.
   */
  public function optionList($poll) {
    $build = [];

    $build['poll_info'] = [
      '#type' => 'item',
      '#title' => $this->t('Poll'),
      '#markup' => $poll->getTitle(),
    ];

    $header = [
      'title' => $this->t('Title'),
      'description' => $this->t('Description'),
      'image' => $this->t('Image'),
      'weight' => $this->t('Weight'),
      'operations' => $this->t('Operations'),
    ];

    $rows = [];

    $options = $this->pollService->getPollOptions($poll->id());

    foreach ($options as $option) {
      $row = [];
      $row['title'] = $option->getTitle();
      $row['description'] = strip_tags($option->getDescription());

      $image = '';
      if ($option->getImageId()) {
        $file = $this->entityTypeManager()->getStorage('file')->load($option->getImageId());
        if ($file) {
          $image = [
            '#theme' => 'image_style',
            '#style_name' => 'thumbnail',
            '#uri' => $file->getFileUri(),
            '#width' => 100,
            '#height' => 100,
          ];
        }
      }
      $row['image'] = $image ? \Drupal::service('renderer')->render($image) : '';

      $row['weight'] = $option->getWeight();

      $operations = [];
      $operations['edit'] = [
        'title' => $this->t('Edit'),
        'url' => Url::fromRoute('poll_system.option_edit', [
          'poll' => $poll->id(),
          'option' => $option->id(),
        ]),
      ];
      $operations['delete'] = [
        'title' => $this->t('Delete'),
        'url' => Url::fromRoute('poll_system.option_delete', [
          'poll' => $poll->id(),
          'option' => $option->id(),
        ]),
      ];

      $row['operations'] = [
        'data' => [
          '#type' => 'operations',
          '#links' => $operations,
        ],
      ];

      $rows[] = $row;
    }

    $build['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No options found.'),
    ];

    $build['add_link'] = [
      '#type' => 'link',
      '#title' => $this->t('Add new option'),
      '#url' => Url::fromRoute('poll_system.option_add', ['poll' => $poll->id()]),
      '#attributes' => ['class' => ['button', 'button--primary']],
    ];

    $build['back_link'] = [
      '#type' => 'link',
      '#title' => $this->t('Back to polls'),
      '#url' => Url::fromRoute('poll_system.list'),
    ];

    return $build;
  }

  /**
   * Displays poll results.
   *
   * @param mixed $poll
   *   The poll entity.
   *
   * @return array
   *   A render array for the poll results page.
   */
  public function pollResults($poll) {
    $results = $this->pollService->getPollResults($poll->id());

    return [
      '#theme' => 'poll_system_results',
      '#poll' => $poll,
      '#results' => $results['options'],
      '#total_votes' => $results['total_votes'],
    ];
  }

  /**
   * Processes a vote.
   *
   * @param mixed $poll
   *   The poll entity.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response.
   */
  public function vote($poll, Request $request) {
    // Check if the poll system is enabled.
    if (!$this->pollService->isPollEnabled()) {
      $this->messenger()->addError($this->t('Poll is currently disabled.'));
      return $this->redirect('poll_system.display', ['poll' => $poll->id()]);
    }

    // Check if the poll is active.
    if (!$poll->isActive()) {
      $this->messenger()->addError($this->t('This poll is not active.'));
      return $this->redirect('poll_system.display', ['poll' => $poll->id()]);
    }

    $user_id = $this->currentUser()->id();

    // Get the selected option.
    $option_id = $request->request->get('option');
    if (!$option_id) {
      $this->messenger()->addError($this->t('No option selected.'));
      return $this->redirect('poll_system.display', ['poll' => $poll->id()]);
    }

    // Validate that the option belongs to this poll.
    $option = $this->pollService->getPollOption($option_id);
    if (!$option || $option->getPollId() != $poll->id()) {
      $this->messenger()->addError($this->t('Invalid option selected.'));
      return $this->redirect('poll_system.display', ['poll' => $poll->id()]);
    }

    // Record the vote.
    if ($this->pollService->recordVote($poll->id(), $option_id, $user_id)) {
      $this->messenger()->addStatus($this->t('Your vote has been recorded.'));
    }
    else {
      $this->messenger()->addError($this->t('There was an error recording your vote.'));
    }

    return $this->redirect('poll_system.display', ['poll' => $poll->id()]);
  }

  /**
   * Displays a poll for voting.
   *
   * @param mixed $poll
   *   The poll entity.
   *
   * @return array
   *   A render array for the poll display.
   */
  public function displayPoll($poll) {
    if (!$poll instanceof Poll) {
      $poll = Poll::load($poll);
      if (!$poll) {
        throw new NotFoundHttpException();
      }
    }

    $poll_enabled = $this->pollService->isPollEnabled();
    $options = $this->pollService->getPollOptions($poll->id());
    $user_id = $this->currentUser()->id();
    $has_voted = $this->pollService->hasUserVoted($poll->id(), $user_id);

    $results = NULL;
    if ($has_voted && $poll->showResults()) {
      $results = $this->pollService->getPollResults($poll->id());
    }

    return [
      '#theme' => 'poll_system',
      '#poll' => $poll,
      '#options' => $options,
      '#results' => $results,
      '#show_results' => $poll->showResults() && $has_voted,
      '#poll_enabled' => $poll_enabled,
      '#attached' => [
        'library' => ['poll_system/poll_system'],
      ],
      '#cache' => [
        'contexts' => ['user'],
        'tags' => ['poll_system:' . $poll->id()],
      ],
    ];
  }

  /**
   * Returns the title for the poll display page.
   *
   * @param mixed $poll
   *   The poll entity.
   *
   * @return string
   *   The title.
   */
  public function pollTitle($poll) {
    return $poll->getTitle();
  }

  /**
   * Access callback for voting.
   *
   * @param mixed $poll
   *   The poll entity.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public function voteAccess($poll) {
    if (!$this->pollService->isPollEnabled()) {
      return AccessResult::forbidden('Poll is disabled');
    }

    if (!$poll->isActive()) {
      return AccessResult::forbidden('Poll is not active');
    }

    return AccessResult::allowedIfHasPermission($this->currentUser(), 'vote in polls');
  }

}
