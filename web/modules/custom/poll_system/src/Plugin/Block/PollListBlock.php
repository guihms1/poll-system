<?php

namespace Drupal\poll_system\Plugin\Block;

use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Block\BlockBase;

/**
 * Provides a block with the list of polls.
 *
 * @Block(
 *   id = "poll_system_list_block",
 *   admin_label = @Translation("Poll List Block")
 * )
 */
class PollListBlock extends BlockBase implements BlockPluginInterface
{

  public function build()
  {
    $polls = \Drupal::entityTypeManager()
      ->getStorage('poll_system')
      ->loadMultiple();

    $renderable_polls = [];

    foreach ($polls as $poll) {
      $results = NULL;

      if ($poll->showResults()) {
        $results = \Drupal::service('poll_system.poll_service')->getPollResults($poll->id());
      }

      $renderable_polls[] = [
        'poll' => $poll,
        'is_active' => $poll->isActive(),
        'show_results' => $poll->showResults(),
        'results' => $results,
      ];
    }

    return [
      '#theme' => 'poll_system_list',
      '#polls' => $renderable_polls,
      '#cache' => [
        'tags' => ['poll_system_list'],
        'contexts' => ['user'],
      ],
    ];
  }
}
