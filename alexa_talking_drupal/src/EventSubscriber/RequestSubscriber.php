<?php

namespace Drupal\alexa_talking_drupal\EventSubscriber;

use Drupal\alexa\AlexaEvent;
use Drupal\node\Entity\Node;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * An event subscriber for Alexa request events.
 */
class RequestSubscriber implements EventSubscriberInterface {

  /**
   * Gets the event.
   */
  public static function getSubscribedEvents() {
    $events['alexaevent.request'][] = ['onRequest', 0];
    return $events;
  }

  /**
   * Called upon a request event.
   *
   * @param \Drupal\alexa\AlexaEvent $event
   *   The event object.
   */
  public function onRequest(AlexaEvent $event) {
    $request = $event->getRequest();
    $response = $event->getResponse();

    switch ($request->intentName) {

      // Von Amazon festgelegter HELP-Intent.
      case 'AMAZON.HelpIntent':
        $response->respond('Dies ist die "Talking Drupal" Demo. Du kannst nach Neuigkeiten fragen oder im Drupal-Glossar suchen.');
        $response->shouldEndSession = TRUE;
        break;

      // Selbst erstellter News-Intent um die aktuellste vorlesen zu lassen.
      case 'NewsIntent':
        $response->respondSSML($this->getNewsOutput());
        $response->shouldEndSession = TRUE;
        break;

      // Selbst erstellter Glossar-Intent, der einen Slot verwendet.
      case 'GlossaryIntent':

        // Slot holen.
        $slot = $request->getSlot('term');

        // Checken, ob slot gesetzt wurde.
        if ($slot == FALSE && $request->data['request']['dialogState'] == "STARTED") {

          // Slot war leer, aber Intent wurde erkannt > Nachfragen.
          $response->respond('Zu welchem Element möchtest du Informationen erhalten?')
            ->reprompt('Bitte sag zum Beispiel "zum Block" oder "zum View"');
        }
        else {

          // Slot war gefüllt und wurde erkannt, hole die passende Entity als Response.
          $response->respondSSML($this->getGlossaryOutput($slot));
          $response->shouldEndSession = TRUE;
        }
        break;

      // MusicIntent.
      case 'MusicIntent':
        $response->respondSSML('<speak>Weiß nicht, ob ich das Musik nennen würde, aber gut... <audio src="soundbank://soundlibrary/musical/amzn_sfx_musical_drone_intro_01"/></speak>');
        $response->shouldEndSession = TRUE;
        break;

      // DoneIntent.
      case 'DoneIntent':
        $response->respond('Wir danken euch allen für eure Aufmerksamkeit und sind gespannt, was für Skills ihr so bauen werdet!');
        $response->shouldEndSession = TRUE;
        break;

      // Default wird getriggert, wenn Amazon keinen Intent erkennen konnte oder dieser oben nicht definiert wurde.
      default:
        $response->respond('Hallo, hier ist Drupal');
        $response->shouldEndSession = TRUE;
        break;
    }
  }

  /**
   * Called on NewsIntent, returns SSML.
   *
   * @return string
   */
  public function getNewsOutput() {
    $query = \Drupal::entityQuery('node');
    $query
      ->condition('status', 1)
      ->condition('type', 'article')
      ->sort('created', 'DESC')
      ->range(0, 1);
    $entity_ids = $query->execute();

    if (count($entity_ids) > 0) {

      foreach ($entity_ids as $nid) {
        $node = Node::load($nid);
        $title = $node->getTitle();
        $body = strip_tags(html_entity_decode($node->get('body')->value), '<p></p>');
      }

      return '<speak>Die aktuellste News ist "' . $title . '"<break strength="strong" />' . $body . '</speak>';
    }
    else {
      return '<speak>Drupal konnte keine News finden.</speak>';
    }
  }

  /**
   * Called on GlossaryIntent, returns SSML.
   *
   * @param string $term
   * The term the user asked for.
   *
   * @return string
   */
  public function getGlossaryOutput($term = '') {
    if (!empty($term)) {

      $query = \Drupal::entityQuery('node');
      $query
        ->condition('status', 1)
        ->condition('type', 'glossary_entry')
        ->condition('title', $term)
        ->range(0, 1);
      $entity_ids = $query->execute();

      foreach ($entity_ids as $nid) {
        $node = Node::load($nid);
        $title = $node->getTitle();
        $body = strip_tags($node->get('field_body')->value, '<p></p>');
      }

      return '<speak>Im Glossar wird "' . $title . '" beschrieben als:<break strength="x-strong" />' . $body . '</speak>';
    }
    else {
      return '<speak>Der Begriff, den du erklärt haben möchtest, kenne ich noch nicht.</speak>';
    }
  }

}
