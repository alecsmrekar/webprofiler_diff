<?php

namespace Drupal\webprofiler_diff\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\webprofiler\DataCollector\DatabaseDataCollector;
use Drupal\webprofiler\DataCollector\ServiceDataCollector;
use Drupal\webprofiler_graphs\Database\WebprofilerPhpSqlParser;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Profiler\Profile;
use \Drupal\webprofiler\Profiler\Profiler;

/**
 * Class WebprofilerDiffController
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
      $container->get('profiler')
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
  public function diffAction(Profile $profile1, Profile $profile2) {
    $build = array();

    $this->profiler->disable();

    /** @var DatabaseDataCollector $database1 */
    $database1 = $profile1->getCollector('database');
    $query1 = $database1->getQueries();

    /** @var DatabaseDataCollector $database2 */
    $database2 = $profile2->getCollector('database');
    $query2 = $database2->getQueries();

    $matchedQueries = array();
    foreach ($query1 as $q1) {
      $sql1 = $q1['query'];
      $hash = hash('md5', $sql1);

      foreach ($query2 as $q2) {
        $sql2 = $q2['query'];

        if ($sql1 === $sql2) {
          $matchedQueries[$hash] = array(
            'query' => $sql1,
            'time1' => $q1['time'],
            'time2' => $q2['time'],
            'delta' => $q1['time'] - $q2['time'],
            'report' => ($q1['time'] === $q2['time']) ? 'equal' : ($q1['time'] > $q2['time']) ? 'better' : 'worse',
          );
        }
      }
    }

    $data1 = array($profile1->getToken());
    $data2 = array($profile2->getToken());
    $data3 = array('delta');
    $i = 0;
    foreach ($matchedQueries as &$query) {
      $data1[] = $query['time1'];
      $data2[] = $query['time2'];
      $data3[] = $query['delta'];

      $query['pos'] = $i;
      $i++;
    }

    $data = array($data1, $data2, $data3);

    $build['summary'] = array(
      '#type' => 'inline_template',
      '#template' => '<p>{{ message }}</p><br/>',
      '#context' => array(
        'message' => t('@numQuery1 queries has been executed in @token1. @numQuery2 queries has been executed in @token2.',
          array(
            '@numQuery1' => $database1->getQueryCount(),
            '@token1' => $profile1->getToken(),
            '@numQuery2' => $database2->getQueryCount(),
            '@token2' => $profile2->getToken(),
          )
        )
      )
    );

    $build['graph'] = array(
      '#type' => 'inline_template',
      '#template' => '<div id="chart"></div>',
      '#attached' => array(
        'library' => array(
          'webprofiler_diff/database',
        ),
        'js' => array(
          array(
            'data' => array('webprofiler_diff' => array('data' => $data)),
            'type' => 'setting'
          )
        )
      )
    );

    $build['tableTitle'] = array(
      '#type' => 'inline_template',
      '#template' => '<h3>{{ title }}</h3><p>{{ message }}</p>',
      '#context' => array(
        'title' => t('Queries in common'),
        'message' => t('@token1 and @token2 has @matchedQueries queries in common.',
          array(
            '@token1' => $profile1->getToken(),
            '@token2' => $profile2->getToken(),
            '@matchedQueries' => count($matchedQueries),
          )
        )
      )
    );

    $build['table'] = array(
      '#type' => 'table',
      '#header' => array(
        $this->t('Query'),
        $this->t('@token time', array('@token' => $profile1->getToken())),
        $this->t('@token time', array('@token' => $profile2->getToken())),
        $this->t('Delta'),
        $this->t('Report'),
        $this->t('Position'),
      ),
      '#rows' => $matchedQueries,
    );

    return $build;
  }

}
