<?php

namespace Drupal\watts_migrate\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\watts_migrate\QueryService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class WattsController - A controller for doing random stuff.
 *
 * @package Drupal\watts_migrate\Controller
 */
class WattsController extends ControllerBase {

  /**
   * QueryService variable.
   *
   * @var \Drupal\watts_migrate\QueryService
   *   A query service for this controller.
   */
  private $queryService;

  /**
   * WattsController constructor.
   *
   * @param \Drupal\watts_migrate\QueryService $queryService
   *   A query service for this constructor.
   */
  public function __construct(QueryService $queryService) {
    $this->queryService = $queryService;
  }

  /**
   * A Query to the D7 database.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   This will return the query results.
   */
  public function query(): Response {
    $queryService = $this->queryService->getQuery();

    return new Response($queryService);
  }

  /**
   * Get QueryService from the container.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The Symfony container.
   *
   * @return \Drupal\watts_migrate\Controller\WattsController|static
   *   Returns the container item requested.
   */
  public static function create(ContainerInterface $container) {
    $queryService = $container->get('watts_migrate.query_service');

    return new static($queryService);
  }

}
