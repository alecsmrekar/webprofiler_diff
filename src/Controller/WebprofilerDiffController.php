<?php

namespace Drupal\webprofiler_diff\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\webprofiler\Profiler\Profiler;

/**
 * Class WebprofilerDiffController.
 */
class WebprofilerDiffController extends ControllerBase {

  /**
   * @var \Drupal\webprofiler\Profiler\Profiler
   */
  private $profiler;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('webprofiler.profiler')
    );
  }

  /**
   * Constructs a new WebprofilerController.
   *
   * @param \Drupal\webprofiler\Profiler\Profiler $profiler
   */
  public function __construct(Profiler $profiler) {
    $this->profiler = $profiler;
  }

  /**
   * @param \Symfony\Component\HttpKernel\Profiler\Profile $profile1
   * @param \Symfony\Component\HttpKernel\Profiler\Profile $profile2
   *
   * @return array
   */
  public function diffAction(string $profile1, string $profile2) {
    $build = [];

    $this->profiler->disable();

    $profile1 = $this->profiler->loadProfile($profile1);
    $profile2 = $this->profiler->loadProfile($profile2);

    /** @var \Drupal\webprofiler\DataCollector\DatabaseDataCollector $database1 */
    $database1 = $profile1->getCollector('database');
    $query1 = $database1->getQueries();

    /** @var \Drupal\webprofiler\DataCollector\DatabaseDataCollector $database2 */
    $database2 = $profile2->getCollector('database');
    $query2 = $database2->getQueries();

    $matchedQueries = [];
    foreach ($query1 as $q1) {
      $sql1 = $q1['query'];
      $hash = hash('md5', $sql1);

      foreach ($query2 as $q2) {
        $sql2 = $q2['query'];

        if ($sql1 === $sql2) {
          $matchedQueries[$hash] = [
            'query' => $sql1,
            'time1' => $q1['time'],
            'time2' => $q2['time'],
            'delta' => $q1['time'] - $q2['time'],
            'report' => ($q1['time'] === $q2['time']) ? 'equal' : (($q1['time'] > $q2['time']) ? 'better' : 'worse'),
          ];
        }
      }
    }

    $data1 = [$profile1->getToken()];
    $data2 = [$profile2->getToken()];
    $data3 = ['delta'];
    $i = 0;
    foreach ($matchedQueries as &$query) {
      $data1[] = $query['time1'];
      $data2[] = $query['time2'];
      $data3[] = $query['delta'];

      $query['pos'] = $i;
      $i++;
    }

    $data = [$data1, $data2, $data3];

    $build['summary'] = [
      '#type' => 'inline_template',
      '#template' => '<p>{{ message }}</p><br/>',
      '#context' => [
        'message' => t('@numQuery1 queries has been executed in @token1. @numQuery2 queries has been executed in @token2.',
          [
            '@numQuery1' => $database1->getQueryCount(),
            '@token1' => $profile1->getToken(),
            '@numQuery2' => $database2->getQueryCount(),
            '@token2' => $profile2->getToken(),
          ]
        ),
      ],
    ];

    $build['graph'] = [
      '#type' => 'inline_template',
      '#template' => '<div id="chart"></div>',
      '#attached' => [
        'library' => [
          'webprofiler_diff/database',
        ],
        'drupalSettings' => ['webprofiler_diff' => ['data' => $data]],
      ],
    ];

    $build['tableTitle'] = [
      '#type' => 'inline_template',
      '#template' => '<h3>{{ title }}</h3><p>{{ message }}</p>',
      '#context' => [
        'title' => t('Queries in common'),
        'message' => t('@token1 and @token2 has @matchedQueries queries in common.',
          [
            '@token1' => $profile1->getToken(),
            '@token2' => $profile2->getToken(),
            '@matchedQueries' => count($matchedQueries),
          ]
        ),
      ],
    ];

    $build['table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Query'),
        $this->t('@token time', ['@token' => $profile1->getToken()]),
        $this->t('@token time', ['@token' => $profile2->getToken()]),
        $this->t('Delta'),
        $this->t('Report'),
        $this->t('Position'),
      ],
      '#rows' => $matchedQueries,
    ];

    return $build;
  }

}
