<?php /**
 * @file
 * Contains \Drupal\composer_manager\EventSubscriber\BootSubscriber.
 */

namespace Drupal\composer_manager\EventSubscriber;

use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class BootSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [KernelEvents::REQUEST => ['onEvent', 0]];
  }

  public function onEvent(\Symfony\Component\HttpKernel\Event\GetResponseEvent $event) {
    try {
      composer_manager_register_autoloader();
    }
    
      catch (\RuntimeException $e) {
      if (!(PHP_SAPI === "cli")) {
        watchdog_exception('composer_manager', $e);
      }
    }
  }

}
