<?php

namespace Drupal\Tests\poll_system\Functional\Rest;

use Drupal\Component\Serialization\Json;
use Drupal\user\Entity\User;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\rest\Functional\AnonResourceTestTrait;

/**
 * Test Poll System REST Resource.
 *
 * @group poll_system
 */
class PollSystemResourceTest extends BrowserTestBase {

  use AnonResourceTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'poll_system',
    'rest',
    'user',
    'basic_auth',
    'serialization',
    'image',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Enable the REST resource for the test site.
    \Drupal::service('config.factory')->getEditable('rest.resource.poll_system_resource')
      ->set('status', true)
      ->set('configuration.methods', ['GET', 'POST'])
      ->set('configuration.formats', ['json'])
      ->set('configuration.authentication', ['basic_auth', 'cookie'])
      ->save();
    \Drupal::service('plugin.manager.rest')->clearCachedDefinitions();
    \Drupal::service('router.builder')->rebuild();
    $this->drupalGet('/admin/config/services/rest');
    $this->drupalGet('/user/login');
  }

  /**
   * Test GET poll list and single poll.
   */
  public function testGetPolls() {
    // Create a user with permission to access the resource.
    $user = User::create([
      'name' => 'apiuser',
      'mail' => 'apiuser@example.com',
      'status' => 1,
      'pass' => 'testpass',
    ]);
    $user->save();
    $role = \Drupal\user\Entity\Role::load('authenticated');
    $role->grantPermission('vote in polls');
    $role->grantPermission('restful get poll_system_resource');
    $role->save();

    // Create a poll entity (or use an existing one).
    $poll = \Drupal::entityTypeManager()->getStorage('poll_system')->create([
      'title' => 'Test Poll',
      'identifier' => 'test_poll',
      'status' => 1,
      'show_results' => 1,
    ]);
    $poll->save();

    // Create a poll option.
    $option = \Drupal::entityTypeManager()->getStorage('poll_system_option')->create([
      'poll_id' => $poll->id(),
      'title' => 'Option 1',
      'description' => 'Test option',
      'weight' => 0,
    ]);
    $option->save();

    // Log in as the created user for GET requests.
    $this->drupalLogin($user);

    // Test GET /api/poll-system (list)
    $this->drupalGet('/api/poll-system', [], ['Accept' => 'application/json']);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains('identifier');

    // Test GET /api/poll-system/{identifier}
    $identifier = $poll->identifier->value;
    $title = $poll->title->value;
    $this->drupalGet('/api/poll-system/' . $identifier, [], ['Accept' => 'application/json']);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains($title);
  }

  /**
   * Test POST vote endpoint.
   */
  public function testPostVote() {
    $user = User::create([
      'name' => 'apiuser2',
      'mail' => 'apiuser2@example.com',
      'status' => 1,
      'pass' => 'testpass',
    ]);
    $user->save();
    $role = \Drupal\user\Entity\Role::load('authenticated');
    $role->grantPermission('vote in polls');
    $role->grantPermission('restful get poll_system_resource');
    $role->grantPermission('restful post poll_system_resource');
    $role->save();

    $poll = \Drupal::entityTypeManager()->getStorage('poll_system')->create([
      'title' => 'Test Poll 2',
      'identifier' => 'test_poll_2',
      'status' => 1,
      'show_results' => 1,
    ]);
    $poll->save();

    $option = \Drupal::entityTypeManager()->getStorage('poll_system_option')->create([
      'poll_id' => $poll->id(),
      'title' => 'Option 2',
      'description' => 'Test option 2',
      'weight' => 0,
    ]);
    $option->save();

    $admin = \Drupal\user\Entity\User::load(1);
    $this->drupalLogin($admin);

    $data = ['option_id' => $option->id()];
    $identifier = $poll->identifier->value;
    // Get session cookie for admin user
    $simpletest_cookie = $this->getSession()->getCookie('SIMPLETEST_SESSION');
    $cookieString = 'SIMPLETEST_SESSION=' . $simpletest_cookie;
    $response = \Drupal::httpClient()->post('/api/poll-system/' . $identifier . '/vote', [
      'headers' => [
        'Content-Type' => 'application/json',
        'Cookie' => $cookieString,
      ],
      'body' => Json::encode($data),
      'http_errors' => false,
    ]);
    $this->assertEquals(201, $response->getStatusCode());
    $this->assertStringContainsString('Vote recorded successfully.', (string) $response->getBody());
  }

}
