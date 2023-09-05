<?php

namespace Drupal\news_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class NewsApiController extends ControllerBase {

  public function displayData(Request $request) {
    // Query nodes of type 'news' that are published and disable access check.
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'news')
      ->condition('status', 1) // Published nodes
      ->accessCheck(FALSE); // Disable access check

    // Specific tags parameter by name.
    $specificTags = $request->query->get('specific_tags');
    if (empty($specificTags)) {
      // If no specific tags provided, return a response message.
      return new JsonResponse(['message' => 'No news tags provided.'], 400);
    }

    // Get the IDs of matching tags.
    $tagIds = $this->getTagIdsByTagNames($specificTags);
    if (empty($tagIds)) {
      // If no matching tag IDs found, return a response message.
      return new JsonResponse(['message' => 'No news for the provided tags was found.'], 404);
    }

    // Filter nodes by the matching tag IDs.
    $query->condition('field_category', $tagIds, 'IN');

    // Execute the query and load matching nodes.
    $nodeIds = $query->execute();
    $nodes = \Drupal\node\Entity\Node::loadMultiple($nodeIds);

    // Prepare the response data.
    $responseData = [];
    foreach ($nodes as $node) {
      // Load taxonomy term tags.
      $publishedDate = \Drupal::service('date.formatter')->format($node->getCreatedTime(), 'custom', 'd-m-Y');

      $tags = [];
      $termEntities = $node->get('field_category')->referencedEntities();
      foreach ($termEntities as $termEntity) {
        $tags[] = $termEntity->getName();
      }

      $responseData[] = [
        'title' => $node->getTitle(),
        'body' => $node->get('field_body')->value,
        'image' => $node->get('field_image')->entity->getFileUri(),
        'view_count' => $node->get('field_viewcount')->value,
        'published_date' => $publishedDate,
        'tags' => $tags,
      ];
    }

    // Return the response as JSON.
    $response = new JsonResponse($responseData);
    return $response;
  }

  /**
   * Get taxonomy term IDs by tag names.
   */
  protected function getTagIdsByTagNames($tagNames) {
    // Query taxonomy term IDs by tag names and vocabulary 'tags'.
    $query = \Drupal::entityQuery('taxonomy_term')
      ->condition('name', $tagNames, 'IN')
      ->condition('vid', 'tags') 
      ->accessCheck(FALSE);
    return $query->execute();
  }
}
