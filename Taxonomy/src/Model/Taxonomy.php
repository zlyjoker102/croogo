<?php

namespace Croogo\Taxonomy\Model;

use Taxonomy\Model\TaxonomyAppModel;

/**
 * Taxonomy
 *
 * @category Taxonomy.Model
 * @package  Croogo.Taxonomy.Model
 * @since    1.3.1
 * @author   Fahad Ibnay Heylaal <contact@fahad19.com>
 * @license  http://www.opensource.org/licenses/mit-license.php The MIT License
 * @link     http://www.croogo.org
 */
class Taxonomy extends TaxonomyAppModel
{

/**
 * Model name
 *
 * @var string
 * @access public
 */
    public $name = 'Taxonomy';

/**
 * Behaviors used by the Model
 *
 * @var array
 * @access public
 */
    public $actsAs = [
        'Tree',
        'Croogo.Cached' => [
            'groups' => [
                'nodes',
                'taxonomy',
            ],
        ],
    ];

/**
 * Model associations: belongsTo
 *
 * @var array
 * @access public
 */
    public $belongsTo = [
        'Term' => [
            'className' => 'Taxonomy.Term',
            'foreignKey' => 'term_id',
            'conditions' => '',
            'fields' => '',
            'order' => '',
        ],
        'Vocabulary' => [
            'className' => 'Taxonomy.Vocabulary',
            'foreignKey' => 'vocabulary_id',
            'conditions' => '',
            'fields' => '',
            'order' => '',
        ],
    ];

/**
 * Generates a tree of terms for a vocabulary
 *
 * @param  string $alias   Vocabulary alias (e.g., categories)
 * @param  array  $options
 * @return array
 */
    public function getTree($alias, $options = [])
    {
        $_options = [
            'key' => 'slug', // Term.slug
            'value' => 'title', // Term.title
            'taxonomyId' => false,
            'cache' => false,
        ];
        $options = array_merge($_options, $options);

        // Check if cached
        if ($this->useCache && isset($options['cache']['config'])) {
            if (isset($options['cache']['prefix'])) {
                $cacheName = $options['cache']['prefix'] . '_' . md5($alias . serialize($options));
            } elseif (isset($options['cache']['name'])) {
                $cacheName = $options['cache']['name'];
            }

            if (isset($cacheName)) {
                $cacheName .= '_' . Configure::read('Config.language');
                $cachedResult = Cache::read($cacheName, $options['cache']['config']);
                if ($cachedResult) {
                    return $cachedResult;
                }
            }
        }

        $vocabulary = $this->Vocabulary->findByAlias($alias);
        if (!isset($vocabulary['Vocabulary']['id'])) {
            return false;
        }
        $this->Behaviors->attach('Tree', [
            'scope' => [
                $this->alias . '.vocabulary_id' => $vocabulary['Vocabulary']['id'],
            ],
        ]);
        $treeConditions = [
            $this->alias . '.vocabulary_id' => $vocabulary['Vocabulary']['id'],
        ];
        $tree = $this->generateTreeList($treeConditions, '{n}.' . $this->alias . '.term_id', '{n}.' . $this->alias . '.id');
        $termsIds = array_keys($tree);
        $terms = $this->Term->find('list', [
            'conditions' => [
                'Term.id' => $termsIds,
            ],
            'fields' => [
                $options['key'],
                $options['value'],
                'id',
            ],
        ]);

        $termsTree = [];
        foreach ($tree as $termId => $tvId) {
            if (isset($terms[$termId])) {
                $term = $terms[$termId];
                $key = array_keys($term);
                $key = $key['0'];
                $value = $term[$key];
                if (strstr($tvId, '_')) {
                    $tvIdN = str_replace('_', '', $tvId);
                    $tvIdE = explode($tvIdN, $tvId);
                    $value = $tvIdE['0'] . $value;
                }

                if (!$options['taxonomyId']) {
                    $termsTree[$key] = $value;
                } else {
                    $termsTree[str_replace('_', '', $tvId)] = $value;
                }
            }
        }

        // Write cache
        if (isset($cacheName)) {
            Cache::write($cacheName, $termsTree, $options['cache']['config']);
        }

        return $termsTree;
    }

/**
 * Check if Term HABTM Vocabulary.
 *
 * If yes, return Taxonomy ID
 * otherwise, return false
 *
 * @param int$termId
 * @param int$vocabularyId
 * @return boolean
 */
    public function termInVocabulary($termId, $vocabularyId)
    {
        $taxonomy = $this->find('first', [
            'conditions' => [
                $this->alias . '.term_id' => $termId,
                $this->alias . '.vocabulary_id' => $vocabularyId,
            ],
        ]);
        if (isset($taxonomy[$this->alias]['id'])) {
            return $taxonomy[$this->alias]['id'];
        }
        return false;
    }
}