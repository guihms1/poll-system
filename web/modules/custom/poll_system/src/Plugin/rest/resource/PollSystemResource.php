<?php

namespace Drupal\poll_system\Plugin\rest\resource;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\poll_system\Service\PollSystemService;
use Psr\Log\LoggerInterface;

/**
 * Provides a REST Resource for Poll System.
 *
 * @RestResource(
 *   id = "poll_system_resource",
 *   label = @Translation("Poll System Resource"),
 *   uri_paths = {
 *     "canonical" = "/api/poll-system/{identifier}",
 *     "collection" = "/api/poll-system",
 *     "https://www.drupal.org/link-relations/create" = "/api/poll-system/{identifier}/vote"
 *   }
 * )
 */
class PollSystemResource extends ResourceBase
{

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The Poll System service.
   *
   * @var \Drupal\poll_system\Service\PollSystemService
   */
  protected $pollService;

  /**
   * Constructs a new PollSystemResource object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Drupal\poll_system\Service\PollSystemService $poll_service
   *   The Poll System service.
   */
  public function __construct(
    array                 $configuration,
                          $plugin_id,
                          $plugin_definition,
    array                 $serializer_formats,
    LoggerInterface       $logger,
    AccountProxyInterface $current_user,
    PollSystemService     $poll_service
  )
  {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->currentUser = $current_user;
    $this->pollService = $poll_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)
  {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('poll_system'),
      $container->get('current_user'),
      $container->get('poll_system.poll_service')
    );
  }

  /**
   * Responds to GET requests.
   *
   * @param string $identifier
   *   The poll identifier.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The response containing poll data.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   */
  public function get($identifier = NULL)
  {

    // If no identifier is provided, return a list of all polls.
    if ($identifier === NULL) {
      // Check if the poll system is enabled.
      if (!$this->pollService->isPollEnabled()) {
        throw new AccessDeniedHttpException('The poll system is currently disabled.');
      }

      // Check if the user has permission to vote in polls.
      if (!$this->currentUser->hasPermission('vote in polls')) {
        throw new AccessDeniedHttpException('You do not have permission to access poll data.');
      }

      // Load all polls.
      $polls = $this->pollService->getAllPolls();
      $data = [];
      foreach ($polls as $poll) {
        $data[] = [
          'id' => $poll->id(),
          'identifier' => $poll->getIdentifier(),
          'title' => $poll->getTitle(),
          'is_active' => $poll->isActive(),
          'show_results' => $poll->showResults(),
        ];
      }
      return new ResourceResponse($data);
    }

    // Check if the poll system is enabled.
    if (!$this->pollService->isPollEnabled()) {
      throw new AccessDeniedHttpException('The poll system is currently disabled.');
    }

    // Check if the user has permission to vote in polls.
    if (!$this->currentUser->hasPermission('vote in polls')) {
      throw new AccessDeniedHttpException('You do not have permission to access poll data.');
    }

    // Get the poll by identifier.
    $poll = $this->pollService->getPollByIdentifier($identifier);
    if (!$poll) {
      throw new NotFoundHttpException('Poll not found.');
    }

    // Check if the poll is active.
    if (!$poll->isActive()) {
      throw new AccessDeniedHttpException('This poll is not currently active.');
    }

    // Get poll options.
    $options = $this->pollService->getPollOptions($poll->id());

    // Check if user has voted.
    $has_voted = $this->pollService->hasUserVoted($poll->id(), $this->currentUser->id());

    // Get poll results if the user has voted and results should be shown.
    $results = NULL;
    if ($has_voted && $poll->showResults()) {
      $results = $this->pollService->getPollResults($poll->id());
    }

    // Build response data.
    $data = [
      'id' => $poll->id(),
      'identifier' => $poll->getIdentifier(),
      'title' => $poll->getTitle(),
      'show_results' => $poll->showResults(),
      'has_voted' => $has_voted,
      'options' => [],
    ];

    foreach ($options as $option) {
      $option_data = [
        'id' => $option->id(),
        'title' => $option->getTitle(),
        'description' => $option->getDescription(),
      ];

      // Add image URL if available.
      if ($option->getImageId()) {
        $file = \Drupal\file\Entity\File::load($option->getImageId());
        if ($file) {
          $option_data['image_url'] = $file->createFileUrl();
        }
      }

      $data['options'][] = $option_data;
    }

    // Add results if available.
    if ($results) {
      $data['results'] = $results;
    }

    $response = new ResourceResponse($data);
    $response->addCacheableDependency($poll);

    return $response;
  }

  /**
   * Responds to POST requests.
   *
   * @param string $identifier
   *   The poll identifier.
   * @param array $data
   *   The POST data.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The response containing poll data.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   */
  public function post($identifier, array $data)
  {
    // Check if the poll system is enabled.
    if (!$this->pollService->isPollEnabled()) {
      throw new AccessDeniedHttpException('The poll system is currently disabled.');
    }

    // Check if the user has permission to vote in polls.
    if (!$this->currentUser->hasPermission('vote in polls')) {
      throw new AccessDeniedHttpException('You do not have permission to vote in polls.');
    }

    // Get the poll by identifier.
    $poll = $this->pollService->getPollByIdentifier($identifier);
    if (!$poll) {
      throw new NotFoundHttpException('Poll not found.');
    }

    // Check if the poll is active.
    if (!$poll->isActive()) {
      throw new AccessDeniedHttpException('This poll is not currently active.');
    }

    // Validate option_id.
    if (empty($data['option_id'])) {
      throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('Missing option_id parameter.');
    }

    $option_id = $data['option_id'];

    // Validate that the option belongs to this poll.
    $option = $this->pollService->getPollOption($option_id);
    if (!$option || $option->getPollId() != $poll->id()) {
      throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('Invalid option ID.');
    }

    // Record the vote.
    $this->pollService->recordVote($poll->id(), $option_id, $this->currentUser->id());

    // Build response data.
    $response_data = [
      'message' => 'Vote recorded successfully.',
      'poll_id' => $poll->id(),
      'poll_title' => $poll->getTitle(),
      'option_id' => $option_id,
      'option_title' => $option->getTitle(),
    ];

    // Add results if they should be shown.
    if ($poll->showResults()) {
      $response_data['results'] = $this->pollService->getPollResults($poll->id());
    }

    return new ResourceResponse($response_data, 201);
  }

}
