<?php
/*
 * Copyright 2018 Jérôme Gasperi
 *
 * Licensed under the Apache License, version 2.0 (the "License");
 * You may not use this file except in compliance with the License.
 * You may obtain a copy of the License at:
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
 * License for the specific language governing permissions and limitations
 * under the License.
 */

/**
 * RESTo PostgreSQL facets functions
 */
class FacetsFunctions
{
    
    /*
     * Relative an absolute coverages minimum percentage value
     */
    private $minRelCov = 20;
    private $minAbsCov = 20;
    
    private $dbDriver = null;

    /**
     * Constructor
     *
     * @param RestoDatabaseDriver $dbDriver
     * @throws Exception
     */
    public function __construct($dbDriver)
    {
        $this->dbDriver = $dbDriver;
    }

    /**
     * Get facet from $id
     *
     * @param string $facetId
     */
    public function getFacet($facetId)
    {
        $results = $this->dbDriver->fetch($this->dbDriver->pQuery('SELECT id, collection, value, type, pid, to_iso8601(created) as created, creator  FROM resto.facet WHERE normalize(id)=($1) LIMIT 1', array(
            $facetId
        )));
        if (isset($results[0])) {
            return FormatUtil::facet($results[0]);
        }
        
        return null;
    }

    /**
     * Store facet within database (i.e. add 1 to the counter of facet if exist)
     *
     * !! THIS FUNCTION IS THREAD SAFE !!
     *
     * Input facet structure :
     *      array(
     *          array(
     *              'name' =>
     *              'type' =>
     *              'id' =>
     *              'parentId' =>
     *          ),
     *          ...
     *      )
     * 
     *  Or
     *      array(
     *          'id',
     *          
     *      )
     *
     * @param array $facets
     */
    public function storeFacets($facets)
    {

        // Empty facets - do nothing
        if (!isset($facets) || count($facets) === 0) {
            return;
        }

        foreach (array_values($facets) as $facetElement) {

            /*
             * Support for direct hashtag (i.e. not an array)
             */
            if (!is_array($facetElement)) {
                $facetElement = array(
                    'id' => $facetElement,
                    'value' => $facetElement,
                    'type' => 'hashtag',
                    'isLeaf' => true
                );
            }
            
            /*
             * Thread safe ingestion using upsert - guarantees that counter is correctly incremented during concurrent transactions
             */
            $insert = 'INSERT INTO resto.facet (id, collection, value, type, pid, creator, created, counter, isleaf) SELECT $1,$2,$3,$4,$5,$6,now(),1,$7';
            $upsert = 'UPDATE resto.facet SET counter=counter+1 WHERE normalize(id)=normalize($1) AND normalize(collection)=normalize($2)';
            $this->dbDriver->pQuery('WITH upsert AS (' . $upsert . ' RETURNING *) ' . $insert . ' WHERE NOT EXISTS (SELECT * FROM upsert)', array(
                $facetElement['id'],
                $facetElement['collection'] ?? '*',
                $facetElement['value'],
                $facetElement['type'],
                $facetElement['parentId'] ?? null,
                $facetElement['creator'] ?? null,
                $facetElement['isLeaf'] ? 1 : 0,
            ), 500, 'Cannot insert facet ' . $facetElement['id']);

        }

    }

    /**
     * Remove facet for collection i.e. decrease by one counter
     *
     * @param string $facetId
     * @param string $collectionName
     */
    public function removeFacet($facetId, $collectionName)
    {
        $this->dbDriver->pQuery('UPDATE resto.facet SET counter = GREATEST(counter - 1) WHERE normalize(id)=normalize($1) AND normalize(collection)=normalize($2)', array($facetId, $collectionName), 500, 'Cannot delete facet for ' . $collectionName);
    }

    /**
     * Return facets elements from a type for a given collection
     *
     * Returned array structure if collectionName is set
     *
     *      array(
     *          'type#' => array(
     *              'value1' => count1,
     *              'value2' => count2,
     *              'parent' => array(
     *                  'value3' => count3,
     *                  ...
     *              )
     *              ...
     *          ),
     *          'type2' => array(
     *              ...
     *          ),
     *          ...
     *      )
     *
     * Or an array of array indexed by collection name if $collectionName is null
     *
     * @param RestoCollection $collection
     * @param array $facetFields
     * @param string $id
     *
     * @return array
     */
    public function getStatistics($collection, $facetFields)
    {
        if (isset($collection)) {
            $collectionName = $collection->name;
            $facetCategories = $collection->model->facetCategories;
        } else {
            $collectionName = null;
            $facetCategories = (new DefaultModel())->facetCategories;
        }

        /*
         * Retrieve pivot for each input facet fields
         */
        if (!isset($facetFields)) {
            $facetFields = array();
            foreach (array_values($facetCategories) as $facetCategory) {
                $facetFields[] = $facetCategory[0];
            }
        }
       
        return $this->getCounts($this->getFacetsPivots($collectionName, $facetFields, null), $collectionName);

    }

    /**
     * Get facets from keywords
     *
     * @param array $keywords
     * @param array $facetCategories
     * @param string $collectionName
     * @param array $options
     */
    public function getFacetsFromKeywords($keywords, $facetCategories, $collectionName, $options = array())
    {
       
        /*
         * One facet per keyword
         */
        $facets = array();
        for ($i = count($keywords); $i--;) {
            $facetCategory = $this->getFacetCategory($facetCategories, $keywords[$i]['type']);
            if (isset($facetCategory)) {

                /*
                 * Compute  facets if relative coverage is greater than 20 %
                 * and absolute coverage is greater than 20%
                 */
                if (isset($keywords[$i]['value']) && $keywords[$i]['value'] < ($options['minRelCov'] ?? $this->minRelCov)) {
                    if (!isset($keywords[$i]['gcover']) || $keywords[$i]['gcover'] < ($options['minAbsCov'] ??  $this->minAbsCov)) {
                        continue;
                    }
                }
                
                $facets[] = array(
                    'id' => $keywords[$i]['id'],
                    'parentId' => $keywords[$i]['parentId'] ?? null,
                    'value' => $keywords[$i]['name'] ?? null,
                    'type' => $keywords[$i]['type'],
                    'collection' => $collectionName,
                    'isLeaf' => $facetCategory['isLeaf']
                );
            }
        }

        return $facets;
    }

    /**
     * Return an array of hashtags from an array of facets
     */
    public function getHashtagsFromFacets($facets)
    {
        $hashtags = array();
        for ($i = count($facets); $i--;) {
            $hashtags[] = is_array($facets[$i]) ? $facets[$i]['id'] : $facets[$i];
        }
        return $hashtags;
    }

    /**
     * Remove feature facets from database
     *
     * @param array $hashtags
     * @param string $collectionName
     */
    public function removeFacetsFromHashtags($hashtags, $collectionName)
    {
        for ($i = count($hashtags); $i--;) {
            $this->removeFacet($hashtags[$i], strpos($hashtags[$i], Resto::TAG_SEPARATOR) !== false ? $collectionName : '*');
        }
    }

    /**
     * Return facet category
     *
     * @param array $facetCategories
     * @param string $type
     */
    private function getFacetCategory($facetCategories, $type)
    {
        if (! isset($type)) {
            return null;
        }
        for ($i = count($facetCategories); $i--;) {
            $categoryLength = count($facetCategories[$i]);
            for ($j = $categoryLength; $j--;) {
                if ($facetCategories[$i][$j] === $type) {
                    return array(
                        'category' => $facetCategories[$i],
                        'isLeaf' => $j == $categoryLength - 1
                    );
                }
            }
        }
        
        /*
         * Otherwise return $type as a new facet category
         */
        return array(
            'category' => $type,
            'isLeaf' => true
        );

    }

    /**
     * Return facet pivots (SOLR4 like)
     *
     * @param string $collectionName
     * @param array $fields
     * @param string $parentId : parent hash
     * @return array
     */
    private function getFacetsPivots($collectionName, $fields, $parentId)
    {
        
        $pivots = array();
        
        /*
         * Facets for one collection
         */
        $query = 'SELECT id,collection,value,type,pid,counter,to_iso8601(created) as created,creator FROM resto.facet WHERE counter > 0 AND ';
        if (isset($collectionName)) {
            $results = $this->dbDriver->query($query . 'normalize(collection)=normalize(\'' . pg_escape_string($collectionName) . '\') AND type IN(\'' . join('\',\'', $fields) . '\')' . (isset($parentId) ? ' AND normalize(pid)=normalize(\'' . pg_escape_string($parentId) . '\')' : '') . ' ORDER BY type ASC, value DESC');
        }
        /*
         * Facets for all collections
         */
        else {
            $results = $this->dbDriver->query($query . 'type IN(\'' . join('\',\'', $fields) . '\')' . (isset($parentId) ? ' AND normalize(pid)=normalize(\'' . pg_escape_string($parentId) . '\')' : '') . ' ORDER BY type ASC, value DESC');
        }
        
        while ($result = pg_fetch_assoc($results)) {
            if (!isset($pivots[$result['type']])) {
                $pivots[$result['type']] = array();
            }
            $create = true;
            if (!isset($collectionName)) {
                for ($i = count($pivots[$result['type']]); $i--;) {
                    if ($pivots[$result['type']][$i]['value'] === $result['value']) {
                        $pivots[$result['type']][$i]['count'] += (integer) $result['counter'];
                        $create = false;
                        break;
                    }
                }
            }
            if ($create) {
                $pivots[$result['type']][] = FormatUtil::facet($result);
            }
        }
        
        return $pivots;
    }

    /**
     * Return counts for all pivots elements
     *
     * @param array $pivots
     * @param string $collectionName
     * @return array
     */
    private function getCounts($pivots, $collectionName)
    {
        $facets = array();
        foreach ($pivots as $pivotName => $pivotValue) {
            if (isset($pivotValue) && count($pivotValue) > 0) {
                for ($j = count($pivotValue); $j--;) {
                    if (isset($facets[$pivotName][$pivotValue[$j]['value']])) {
                        $facets[$pivotName][$pivotValue[$j]['value']] += (integer) $pivotValue[$j]['count'];
                    } else {
                        $facets[$pivotName][$pivotValue[$j]['value']] = (integer) $pivotValue[$j]['count'];
                    }
                }
            }
        }

        /*
         * Total count
         */
        $count = 0;
        if (isset($facets['collection'])) {
            foreach (array_values($facets['collection']) as $collectionCount) {
                $count += $collectionCount;
            }

            if (isset($collectionName)) {
                unset($facets['collection']);
            }
        }

        return array(
            'count' => $count,
            'facets' => $facets
        );
    }

}
